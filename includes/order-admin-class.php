<?php 

class SnappBoxOrderAdmin
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_leaflet_scripts']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_location_in_order_admin']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_map_in_admin_order'], 20);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_snappbox_order_button']);
        add_action('wp_ajax_create_snappbox_order', [$this, 'handle_create_snappbox_order']);
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
        $orderID = get_post_meta($order->get_id(), '_snappbox_order_id', true);
        print_r('<strong>Order ID: </strong>' . $orderID);
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
                document.addEventListener('DOMContentLoaded', function () {
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

    public function display_snappbox_order_button($order)
    {
        echo('<a href="#">Cancel Order</a>');
    }
}