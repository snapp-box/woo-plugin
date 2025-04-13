<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
    return;
}

require_once(SNAPPBOX_DIR . 'includes/cities-class.php');

class SnappBoxShippingMethod extends WC_Shipping_Method {

    public function __construct() {
        $this->id                 = 'snappbox_shipping_method'; 
        $this->method_title       = __( 'SnappBox Shipping Method', 'sb-delivery' );
        $this->method_description = __( 'A SnappBox shipping method with dynamic pricing.', 'sb-delivery' );
        $this->enabled            = "yes";
        $this->title              = "Snappbox Shipping";
        $this->init();
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();
        $this->enabled = $this->get_option( 'enabled' );
        $this->title   = $this->get_option( 'title' );
        add_action('admin_enqueue_scripts', array($this, 'enqueue_leafles_scripts'));
        add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
        
    }

    public function enqueue_leafles_scripts() {
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet/dist/leaflet.css');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet/dist/leaflet.js', array(), null, true);
    }

    public function init_form_fields() {
        $latitude = get_option('snappbox_latitude', '35.8037761');
        $longitude = get_option('snappbox_longitude', '51.4152466');
        $stored_cities = get_option('snappbox_cities', []);

        $citiesObj = new SnappBoxCities();
        $cities = $citiesObj->get_delivery_category($latitude, $longitude);

        $this->form_fields = [
            'enabled' => [
                'title'       => __( 'Enable', 'sb-delivery' ),
                'type'        => 'checkbox',
                'description' => __( 'Enable this shipping method', 'sb-delivery' ),
                'default'     => 'yes',
            ],
            'title' => [
                'title'       => __( 'Title', 'sb-delivery' ),
                'type'        => 'text',
                'description' => __( 'Title to display during checkout', 'sb-delivery' ),
                'default'     => __( 'SnappBox Shipping', 'sb-delivery' ),
            ],
            'fixed_price' => [
                'title'       => __( 'Fixed Price', 'sb-delivery' ),
                'type'        => 'number',
                'description' => __( 'Leave it empty for canceling fixed price', 'sb-delivery' ),
            ],
            'free_delivery' => [
                'title'       => __( 'Free Delivery', 'sb-delivery' ),
                'type'        => 'number',
                'description' => __( 'Minimum basket price for free delivery', 'sb-delivery' ),
            ],
            'base_cost' => [
                'title'       => __( 'Base Shipping Cost', 'sb-delivery' ),
                'type'        => 'number',
                'description' => __( 'Base shipping cost for this method', 'sb-delivery' ),
                'default'     => '5',
                'desc_tip'    => true,
            ],
            'cost_per_kg' => [
                'title'       => __( 'Cost per KG', 'sb-delivery' ),
                'type'        => 'number',
                'description' => __( 'Shipping cost per kilogram', 'sb-delivery' ),
                'default'     => '2',
                'desc_tip'    => true,
            ],
            'snappbox_api' => [
                'title'       => __( 'SnappBox API Key', 'sb-delivery' ),
                'type'        => 'text',
                'default'     => get_option('snappbox_api', '')
            ],
            'snappbox_latitude' => [
                'title'       => __( 'Latitude', 'sb-delivery' ),
                'type'        => 'text',
                'default'     => $latitude,
                'custom_attributes' => ['readonly' => 'readonly']
            ],
            'snappbox_longitude' => [
                'title'       => __( 'Longitude', 'sb-delivery' ),
                'type'        => 'text',
                'default'     => $longitude,
                'custom_attributes' => ['readonly' => 'readonly']
            ],
            'snappbox_store_phone' => [
                'title'       => __( 'Phone Number', 'sb-delivery' ),
                'type'        => 'text',
                'default'     => get_option('snappbox_store_phone', '')
            ],
            'snappbox_store_name' => [
                'title'       => __( 'Store Name', 'sb-delivery' ),
                'type'        => 'text',
                'default'     => get_option('snappbox_store_name', '')
            ],
            'snappbox_cities' => [
                'title'       => __( 'Cities', 'sb-delivery' ),
                'type'        => 'multiselect',
                'options'     => array_column($cities->cities, 'cityName', 'cityKey'),
                'default'     => $stored_cities
            ]
        ];
    }

    public function admin_options() {
        parent::admin_options();
        $lat = $this->get_option('snappbox_latitude', '35.8037761');
        $lng = $this->get_option('snappbox_longitude', '51.4152466');
        echo '<h4>' . __('Set Store Location', 'sb-delivery') . '</h4>';
        ?>
        <div id="map" style="height:400px;"></div>
        <link rel="stylesheet" id="leaflet-css-css" href="https://unpkg.com/leaflet/dist/leaflet.css?ver=6.7.2" media="all">
        <script src="https://unpkg.com/leaflet/dist/leaflet.js" id="leaflet-js-js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var map = L.map('map').setView([<?php echo($lat);?>, <?php echo($lng)?>], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);
                var marker = L.marker([<?php echo($lat);?>, <?php echo($lng)?>], {draggable: true}).addTo(map);
                marker.on('dragend', function(e) {
                    var latLng = marker.getLatLng();
                    document.querySelector('[name="woocommerce_snappbox_shipping_method_snappbox_latitude"]').value = latLng.lat.toFixed(9);
                    document.querySelector('[name="woocommerce_snappbox_shipping_method_snappbox_longitude"]').value = latLng.lng.toFixed(9);
                });
            });
        </script>
        <?php
    }

    public function calculate_shipping( $package = [] ) {
        global $woocommerce;
        $base_cost = $this->get_option( 'base_cost' );
        $cost_per_kg = $this->get_option( 'cost_per_kg' );
        $fixed_price = $this->get_option( 'fixed_price' );
        $free_delivery = $this->get_option('free_delivery');
        $totalCard = floatval( preg_replace( '#[^\d.]#', '', $woocommerce->cart->get_cart_total() ) );
        $weight = 0;

        if(empty($fixed_price)){
            foreach ( $package['contents'] as $item ) {
                $product = $item['data'];
                // $weight += $product->get_weight() * $item['quantity']; 
            }
            $cost = $base_cost + ( $weight * $cost_per_kg );
        } else if(!empty($free_delivery) && $free_delivery < $totalCard){
            $cost = 0;
        } else {
            $cost = $fixed_price;
        }

        $rate = [
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $cost,
            'calc_tax' => 'per_item', 
        ];
        $this->add_rate( $rate );
    }

    public static function register() {
        add_filter( 'woocommerce_shipping_methods', [ __CLASS__, 'add_method' ] );
    }

    public static function add_method( $methods ) {
        $methods['snappbox_shipping_method'] = __CLASS__;
        return $methods;
    }
}

function snappbox_shipping_method_init() {
    if ( class_exists( 'SnappBoxShippingMethod' ) ) {
        SnappBoxShippingMethod::register();
    }
}

add_action( 'woocommerce_shipping_init', 'snappbox_shipping_method_init' );