<?php

if (!defined('ABSPATH')) {
    exit;
}
require_once(SNAPPBOX_DIR . 'includes/cities-class.php');
require_once(SNAPPBOX_DIR . 'includes/create-order-class.php');

class SnappBoxCheckout
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_leaflet_scripts']);
        add_action('woocommerce_before_checkout_billing_form', [$this, 'display_osm_map']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_customer_location']);
        add_action('woocommerce_checkout_process', [$this, 'validate_customer_location']);
        add_shortcode('snappbox_checkout_map', [$this, 'render_map_shortcode']);
    }



    public function enqueue_leaflet_scripts()
    {
        wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], null, true);
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
    }

    public function display_osm_map()
    {
        $settings_serialized = get_option('woocommerce_snappbox_shipping_method_settings');
        $settings = maybe_unserialize($settings_serialized);
        
        if($settings['enabled'] == 'yes' ) {
        ?>
            <h3><?php _e('Select your location', 'sb-delivery'); ?></h3>
            <div id="osm-map" style="height: 400px;"></div>
            <input type="hidden" id="customer_latitude" name="customer_latitude" />
            <input type="hidden" id="customer_longitude" name="customer_longitude" />
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var defaultLat = 35.6892;
                    var defaultLng = 51.3890;
                    var map = L.map('osm-map').setView([defaultLat, defaultLng], 12);
                    L.tileLayer('https://raster.snappmaps.ir/styles/snapp-style/{z}/{x}/{y}{r}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(map);
                    var marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
                    marker.on('dragend', function (event) {
                        var position = marker.getLatLng();
                        document.getElementById('customer_latitude').value = position.lat;
                        document.getElementById('customer_longitude').value = position.lng;
                    });
                    document.getElementById('customer_latitude').value = defaultLat;
                    document.getElementById('customer_longitude').value = defaultLng;
                });
            </script>
        <?php }
    }


    public function save_customer_location($order_id)
    {
        if (isset($_POST['customer_latitude']) && isset($_POST['customer_longitude'])) {
            $latitude = sanitize_text_field($_POST['customer_latitude']);
            $longitude = sanitize_text_field($_POST['customer_longitude']);
            update_post_meta($order_id, '_customer_latitude', $latitude);
            update_post_meta($order_id, '_customer_longitude', $longitude);
        }
    }
    public function validate_customer_location()
    {
        if (empty($_POST['customer_latitude']) || empty($_POST['customer_longitude'])) {
            wc_add_notice(__('Please select your location on the map.'), 'error');
        }
    }

}
