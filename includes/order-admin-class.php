<?php 
require_once(SNAPPBOX_DIR . 'includes/create-order-class.php');

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
        $snappboxOrder = get_post_meta($order->get_id(), '_snappbox_order_id', true);
        if(!$snappboxOrder){
            ?>
            <div class="snappbox-order-container">
                <button id="snappbox-create-order" data-order-id="<?php echo esc_attr($order->get_id()); ?>" class="button button-primary">
                    <?php _e('Send to SnappBox', 'sb-delivery');?>
                </button>
                <span id="snappbox-response"></span>
            </div>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#snappbox-create-order').on('click', function(event) {
                        var orderId = $(this).data('order-id');
    
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'create_snappbox_order',
                                order_id: orderId
                            },
                            beforeSend: function() {
                                $('#snappbox-response').text('Sending...');
                            },
                            success: function(response) {
                                if (response.response.status_code == 201) {
                                    $('#snappbox-response').html('<span style="color:green;">' + response.response.data.finalCustomerFare + '</span>');
                                } else {
                                    $('#snappbox-response').html('<span style="color:red;">Error: ' + response.response.message + '</span>');
                                }
                            },
                            error: function() {
                                $('#snappbox-response').text('Error sending order.');
                                console.log(response);
                            }
                        });
                        event.preventDefault();
                    });
                });
            </script>
            <?php
        }
        else{
            echo('<a href="">Cancle order</a>');
        }
        
    }
    public function handle_create_snappbox_order()
    {
        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Order ID missing');
        }

        $order_id = intval($_POST['order_id']);

        if (!$order_id) {
            wp_send_json_error('Invalid Order ID');
        }

        $snappbox_order = new SnappBoxCreateOrder();
        $response = $snappbox_order->handleCreateOrder($order_id);
        if ($response['success']) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error($response['message']);
        }
    }
}