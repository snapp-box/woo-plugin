<?php

if (! defined('ABSPATH')) {
    exit;
}

class SnappBoxCheckout
{

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_leaflet_scripts']);
        add_action('woocommerce_before_checkout_billing_form', [$this, 'display_osm_map']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_customer_location']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_location_in_order_admin']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_leaflet_scripts']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_map_in_admin_order'], 20);
    }


    public function enqueue_leaflet_scripts()
    {
        wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], null, true);
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
        wp_enqueue_script('custom-osm-js',  SNAPPBOX_URL . 'assets/js/osm-map.js', ['leaflet'], null, true);
    }


    public function display_osm_map()
    {
        echo '<h3>' . __('Select your location', 'sb-delivery') . '</h3>';
        echo '<div id="osm-map" style="height: 400px;"></div>';
        echo '<input type="hidden" id="customer_latitude" name="customer_latitude" />';
        echo '<input type="hidden" id="customer_longitude" name="customer_longitude" />';
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

    public function enqueue_admin_leaflet_scripts($hook)
    {
        if ('post.php' !== $hook) {
            return;
        }

        $screen = get_current_screen();
        if ('shop_order' === $screen->post_type) {
            wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
            wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], null, true);
        }
    }
    public function display_location_in_order_admin($order)
    {
        $latitude = get_post_meta($order->get_id(), '_customer_latitude', true);
        $longitude = get_post_meta($order->get_id(), '_customer_longitude', true);
        if ($latitude && $longitude) {
            echo '<p><strong>' . __('Customer Location', 'sb-delivery') . ':</strong> ' . $latitude . ', ' . $longitude . '</p>';
        }
    }

    public function display_map_in_admin_order($order)
    {
        $latitude = get_post_meta($order->get_id(), '_customer_latitude', true);
        $longitude = get_post_meta($order->get_id(), '_customer_longitude', true);
        if ($latitude && $longitude) {
            echo '<div id="admin-osm-map" style="height: 400px; margin-top: 20px;"></div>';
?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var latitude = <?php echo esc_js($latitude); ?>;
                    var longitude = <?php echo esc_js($longitude); ?>;

                    var map = L.map('admin-osm-map').setView([latitude, longitude], 15);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(map);

                    L.marker([latitude, longitude]).addTo(map)
                        .bindPopup('Customer Location')
                        .openPopup();
                });
            </script>
<?php
        }
    }
}
