<?php
require_once(SNAPPBOX_DIR . 'includes/create-order-class.php');
require_once(SNAPPBOX_DIR . 'includes/cancel-order-class.php');
require_once(SNAPPBOX_DIR . 'includes/status-check-class.php');

class SnappBoxOrderAdmin
{

    public function __construct($accessToken = SNAPPBOX_API_TOKEN)
    {
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_location_in_order_admin']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_map_in_admin_order'], 20);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_snappbox_order_button']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'check_order_status']);
        add_action('wp_ajax_create_snappbox_order', [$this, 'handle_create_snappbox_order']);
        add_action('wp_ajax_cancel_snappbox_order', [$this, 'handle_cancel_snappbox_order']);
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
        <link rel="stylesheet" id="leaflet-css-css" href="https://unpkg.com/leaflet/dist/leaflet.css?ver=6.7.2" media="all">
        <script src="https://unpkg.com/leaflet/dist/leaflet.js" id="leaflet-js-js"></script>
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

    public function display_snappbox_order_button($order)
    {
        $snappboxOrder = get_post_meta($order->get_id(), '_snappbox_order_id', true);
        if (!$snappboxOrder) {
        ?>
            <div class="snappbox-order-container">
                <button id="snappbox-create-order" data-order-id="<?php echo esc_attr($order->get_id()); ?>" class="button button-primary">
                    <?php _e('Send to SnappBox', 'sb-delivery'); ?>
                </button>
                <span id="snappbox-response"></span>
            </div>
        <?php
        } else {
        ?>
            <div class="snappbox-cancel-container">
                <button id="snappbox-cancel-order" data-order-id="<?php echo esc_attr($snappboxOrder); ?>" class="button button-secondary">
                    <?php _e('Cancel Order', 'sb-delivery'); ?>
                </button>
                <span id="snappbox-cancel-response"></span>
            </div>
        <?php
        }
        ?>
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
                            var data = response.response.data;
                            if (response.response.status_code == 201) {
                                $('#snappbox-response').html('<span style="color:green;">'+ response.response.message+ ' ' + data.finalCustomerFare + '</span>');
                            } else {
                                $('#snappbox-response').html('<span style="color:red;">Error: ' + response.response.message + '</span>');
                            }
                        },
                        error: function() {
                            $('#snappbox-response').text('Error sending order.');
                        }
                    });
                    event.preventDefault();
                });

                $('#snappbox-cancel-order').on('click', function(event) {
                    var orderId = $(this).data('order-id');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'cancel_snappbox_order',
                            order_id: orderId,
                            woo_order_id: <?php echo $order->get_id();?>
                        },
                        beforeSend: function() {
                            $('#snappbox-cancel-response').text('Cancelling...');
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#snappbox-cancel-response').html('<span style="color:green;">' + response.data + '</span>');
                                location.reload(); 
                            } else {
                                $('#snappbox-cancel-response').html('<span style="color:red;">Error: ' + response.data + '</span>');
                            }
                        },
                        error: function() {
                            $('#snappbox-cancel-response').text('Error cancelling order.');
                        }
                    });
                    event.preventDefault();
                });
            });
        </script>
<?php
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
    public function handle_cancel_snappbox_order()
    {
        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Order ID missing');
        }
        $order_id = sanitize_text_field($_POST['order_id']);
        $woo_order_id = sanitize_text_field($_POST['woo_order_id']);
        $snappbox_api = new SnappBoxCancelOrder();
        $response = $snappbox_api->cancel_order($order_id);
        if ($response['success'] === false) {
            delete_post_meta($woo_order_id, '_snappbox_order_id'); 
            delete_post_meta($woo_order_id, '_snappbox_last_api_response'); 
            delete_post_meta($woo_order_id, '_snappbox_last_api_call'); 
            wp_send_json_success($response['message']);
        } else {
            wp_send_json_error($response['message']);
        }
    }
    public function check_order_status(){
        
        $orderID = get_post_meta($_GET['id'], '_snappbox_order_id', true);
        $getResponse = get_post_meta($orderID,'_snappbox_last_api_response', true);
        $last_called = get_post_meta($orderID, '_snappbox_last_api_call', true);
        if($getResponse){
            echo('<p>Status: '.$getResponse->status.'</p>');
        }
        
        if (!$last_called || (time() - (int)$last_called) > 300) {            
            $statusCehck = new SnappOrderStatus();
            $response = $statusCehck->get_order_status($orderID);
            if (is_wp_error($response)) {
                error_log('API Error: ' . $response->get_error_message());
            } else {
                update_post_meta($orderID, '_snappbox_last_api_response', $response);
                update_post_meta($orderID, '_snappbox_last_api_call', time());
            }
        }

    }
}
