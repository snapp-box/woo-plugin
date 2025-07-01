<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
    return;
}

require_once(SNAPPBOX_DIR . 'includes/cities-class.php');
require_once(SNAPPBOX_DIR . 'includes/wallet-balance-class.php');

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
        add_action('admin_notices', [$this, 'admin_alert']);
    }

    public function enqueue_leafles_scripts() {
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet/dist/leaflet.css');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet/dist/leaflet.js', array(), null, true);
        
    }

    public function init_form_fields() {
        $latitude = get_option('snappbox_latitude', '35.8037761');
        $longitude = get_option('snappbox_longitude', '51.4152466');
        $settings_serialized = get_option('woocommerce_snappbox_shipping_method_settings');
        $settings = maybe_unserialize($settings_serialized);

        $stored_cities = $settings['snappbox_cities'];
        $snappBoxAPIKey = $this->get_option('snappbox_api');
    
        $transient_key = 'snappbox_cities_' . md5($latitude . '_' . $longitude);
        $cities = get_transient($transient_key);
    
        if ($cities === false) {
            $citiesObj = new SnappBoxCities();
            $cities = $citiesObj->get_delivery_category($latitude, $longitude);
    
            if (!empty($cities) && isset($cities->cities)) {
                set_transient($transient_key, $cities, DAY_IN_SECONDS); 
            }
        }
    
        $city_options = [];
        if (!empty($cities->cities) && is_array($cities->cities)) {
            $filtered_cities = array_filter($cities->cities, function($city) {
                return !empty($city->cityName);
            });
    
            $city_options = array_column($filtered_cities, 'cityName', 'cityKey');
        }
    
        $this->form_fields = [
            'enabled' => [
                'title'       => __( 'Enable', 'sb-delivery' ),
                'type'        => 'checkbox',
                'description' => __( 'Enable this shipping method', 'sb-delivery' ),
                'default'     => 'yes',
            ],
            'sandbox' => [
                'title'       => __( 'Enable Test Mode', 'sb-delivery' ),
                'type'        => 'checkbox',
                'description' => __( 'Enable test mode for this plugin', 'sb-delivery' ),
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
                'default'     => $snappBoxAPIKey,
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
            'ondelivery' => [
                'title'       => __( 'Enable payment on delivery', 'sb-delivery' ),
                'type'        => 'checkbox',
                'description' => __( 'Pay SnappBox payment on delivery', 'sb-delivery' ),
                'default'     => 'no',
            ],
            'snappbox_cities' => [
                'title'       => __( 'Cities', 'sb-delivery' ),
                'type'        => 'multiselect',
                'options'     => $city_options,
                'default'     => $stored_cities,
            ]
        ];
    }
    

    public function admin_options() {
        $walletObj = new SnappBoxWalletBalance();
        $walletObjResult = $walletObj->check_balance();
        $this->admin_alert($walletObjResult);
        echo('<div class="snappbox-panel right">');
        parent::admin_options();
        echo('</div>');
        $lat = $this->get_option('snappbox_latitude', '35.8037761');
        $lng = $this->get_option('snappbox_longitude', '51.4152466');
        ?>
        <link rel="stylesheet" href="<?php echo(SNAPPBOX_URL);?>assets/css/style.css" />
        <?php 
        echo '<div style="margin-bottom: 5px;float:left;">';
        echo '<a href="#" id="snappbox-launch-modal" class="button colorful-button button-secondary">';
        echo esc_html__('Show Setup Guide', 'sb-delivery');
        echo '</a>';
        echo '</div>';
        ?>
        <?php $this->wallet_information();?>

        <div class="snappbox-panel">
            <h4><?php _e('Set Store Location', 'sb-delivery');?></h4>
            <div id="map" style="height:400px;"></div>
            <link rel="stylesheet" id="leaflet-css-css" href="https://unpkg.com/leaflet/dist/leaflet.css?ver=6.7.2" media="all">
            <script src="https://unpkg.com/leaflet/dist/leaflet.js" id="leaflet-js-js"></script>
            <!-- <link rel="stylesheet" id="leaflet-css-css" href="https://assets.snapp-box.com/static/box/scripts/leaflet/v1.9.3/leaflet.css" media="all">
            <script src="https://assets.snapp-box.com/static/box/scripts/leaflet/v1.9.4/leaflet.js" id="leaflet-js-js"></script> -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var map = L.map('map').setView([<?php echo($lat);?>, <?php echo($lng)?>], 16);
                    L.tileLayer('https://raster.snappmaps.ir/styles/snapp-style/{z}/{x}/{y}{r}.png', {
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
        </div>
        <?php $this->add_modal_box();?>
        

        <?php
    }
    public function admin_alert($walletObjResult){
        if (!$walletObjResult) {
            $walletObj = new SnappBoxWalletBalance();
            $walletObjResult = $walletObj->check_balance();
        }
        
        if(!empty($walletObjResult)){
            $currentBalance = isset($walletObjResult['response']['currentBalance']) ? $walletObjResult['response']['currentBalance'] : 0;
            if($currentBalance > 0){ ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <?php _e('Your wallet balance is too low. Please contact Snappbox', 'sb-delivery');?> 
                        <a href="https://woocommerce.com/document/ssl-and-https/" target="_blank"><?php _e('Learn more.', 'sb-delivery');?></a>
                    </p>
                </div>
            <?php }
        }
    }
    
    public function wallet_information(){?>
        <div class="snappbox-panel">
            <h4><?php _e('wallet Information', 'sb-delivery');?></h4>
            <?php $walletObj = new SnappBoxWalletBalance();
            $walletObjResult = $walletObj->check_balance();
            if (!empty($walletObjResult) && isset($walletObjResult['response']['currentBalance'])) {
                echo( '<p>'.__('Your current balance is: ', 'sb-delivery') . $walletObjResult['response']['currentBalance'] . ' ' . __('Rials', 'sb-delivery').'</p>' );
            } else {
                echo( __('Unable to fetch wallet balance.', 'sb-delivery') );
            }
            ?>
        </div>
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
            $cost = $base_cost;
        } else if(!empty($free_delivery) && $free_delivery < $totalCard){
            $cost = 0;
        } else if(empty($free_delivery) && empty($base_cost) && empty($cost_per_kg)) {
            $cost = 0;
        }
        else {
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

        if ( current_user_can( 'administrator' ) ) {
            $methods['snappbox_shipping_method'] = __CLASS__;
        }
        return $methods;
    }

    public function add_modal_box(){
        ?>
        <script type="text/javascript" src="<?php echo(SNAPPBOX_URL);?>/assets/js/scripts.js"></script>
        <!-- Multi-step modal -->
        <div id="snappbox-setup-modal" class="snappbox-modal">
            <div class="snappbox-modal-content">
                <span class="snappbox-close">&times;</span>
                <div class="snappbox-slide active">
                    <h2><?php _e('Enable and Disable!', 'sb-delivery'); ?></h2>
                    <p><?php _e('You can enable and disable the method here', 'sb-delivery'); ?></p>
                    <img src="<?php echo(SNAPPBOX_URL);?>/assets/screens/1.png" />
                    <button class="snappbox-next button colorful-button"><?php _e('Next', 'sb-delivery'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php _e('Step 1: Enter Your API Key', 'sb-delivery'); ?></h2>
                    <p><?php _e('Put your API key in this field. you can aquire this API key by contacting SnappBox team', 'sb-delivery'); ?></p>
                    <img src="<?php echo(SNAPPBOX_URL);?>/assets/screens/2.png" />
                    <button class="snappbox-next button colorful-button"><?php _e('Next', 'sb-delivery'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php _e('Step 2: Set Your Location', 'sb-delivery'); ?></h2>
                    <p><?php _e('Drag the map marker to your store’s location.', 'sb-delivery'); ?></p>
                    <img src="<?php echo(SNAPPBOX_URL);?>/assets/screens/5.png" />
                    <button class="snappbox-next button colorful-button"><?php _e('Next', 'sb-delivery'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php _e('Step 3: Set Stores data', 'sb-delivery'); ?></h2>
                    <p><?php _e('Set your stores name and your Mobile Number.', 'sb-delivery'); ?></p>
                    <img src="<?php echo(SNAPPBOX_URL);?>/assets/screens/7.png" />
                    <button class="snappbox-next button colorful-button"><?php _e('Next', 'sb-delivery'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php _e('Step 3: Set Stores city', 'sb-delivery'); ?></h2>
                    <p><?php _e('Set the city that your store can send products by SnappBox.', 'sb-delivery'); ?></p>
                    <img src="<?php echo(SNAPPBOX_URL);?>/assets/screens/4.png" />
                    <button class="snappbox-next button colorful-button"><?php _e('Next', 'sb-delivery'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php _e('Final Step: Save Settings', 'sb-delivery'); ?></h2>
                    <div class="holder">
                        <p><?php _e('Scroll down and click "Save changes" to activate SnappBox.', 'sb-delivery'); ?></p>
                        <img src="<?php echo(SNAPPBOX_URL);?>/assets/screens/6.png" />
                    </div>
                    <button class="snappbox-close button colorful-button"><?php _e('Got it!', 'sb-delivery'); ?></button>
                </div>
            </div>
        </div>
        <?php 
    }
    
}

