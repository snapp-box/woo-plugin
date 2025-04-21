<?php
require_once(SNAPPBOX_DIR . 'includes/create-order-class.php');
require_once(SNAPPBOX_DIR . 'includes/cancel-order-class.php');
require_once(SNAPPBOX_DIR . 'includes/status-check-class.php');
require_once(SNAPPBOX_DIR . 'includes/pricing-class.php');

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
        add_action('wp_ajax_get_pricing', [$this, 'handle_get_pricing']); // ✅ Correct action hook for pricing
    }

    public function display_location_in_order_admin($order)
    {
        $latitude = get_post_meta($order->get_id(), '_customer_latitude', true);
        $longitude = get_post_meta($order->get_id(), '_customer_longitude', true);
        $orderID = get_post_meta($order->get_id(), '_snappbox_order_id', true);
        echo '<strong>Order ID: </strong>' . esc_html($orderID);
        if ($latitude && $longitude) {
            echo '<p><strong>' . __('Customer Location', 'sb-delivery') . ':</strong> ' . esc_html($latitude) . ', ' . esc_html($longitude) . '</p>';
        }
    }

    public function display_map_in_admin_order($order)
    {
        $latitude = get_post_meta($order->get_id(), '_customer_latitude', true);
        $longitude = get_post_meta($order->get_id(), '_customer_longitude', true);
        if ($latitude && $longitude) {
            echo '<div id="admin-osm-map" style="height: 400px; margin-top: 20px;"></div>';
?>
            <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
            <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
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
        if (!$snappboxOrder) {
?>
            <div class="modal" style="display:none;justify-content:center;align-items:center;background-color:rgba(255,255,255,0.7);z-index:1100;left:0;right:0;top:0;bottom:0;position:absolute;">
                <div class="modal-box" style="text-align:center;display:flex;flex-direction:column;background-color:#fff;padding:20px;border:1px solid #ebebeb;border-radius:15px;">
                    <p id="pricing-message">در حال دریافت قیمت...</p>
                    <div class="snappbox-order-container">
                        <button id="snappbox-create-order" data-order-id="<?php echo esc_attr($order->get_id()); ?>" class="button button-primary">
                            <?php _e('Send to SnappBox', 'sb-delivery'); ?>
                        </button>
                    </div>
                    <span id="snappbox-response"></span>
                    <a href="#" class="close" style="margin-top:15px;">بستن</a>
                </div>
            </div>

            <div class="snappbox-order-container">
                <button id="snappbox-pricing-order" data-order-id="<?php echo esc_attr($order->get_id()); ?>" class="button button-primary">
                    <?php _e('Get SnappBox Price', 'sb-delivery'); ?>
                </button>
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
            jQuery(document).ready(function ($) {
                $('.close').on('click', function (e) {
                    e.preventDefault();
                    $('.modal').hide();
                });

                $('#snappbox-pricing-order').on('click', function (e) {
                    e.preventDefault();
                    let orderId = $(this).data('order-id');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'get_pricing',
                            order_id: orderId
                        },
                        beforeSend: function () {
                            $('#pricing-message').text('در حال دریافت قیمت...');
                        },
                        success: function (response) {
                            $('.modal').css('display', 'flex');
                            $('#pricing-message').text('قیمت تخمینی: ' + response.data.finalCustomerFare + ' تومان');
                        },
                        error: function () {
                            $('#pricing-message').text('خطا در ارسال درخواست.');
                        }
                    });
                });

                $('#snappbox-create-order').on('click', function (e) {
                    e.preventDefault();
                    let orderId = $(this).data('order-id');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'create_snappbox_order',
                            order_id: orderId
                        },
                        beforeSend: function () {
                            $('#snappbox-response').text('Sending...');
                        },
                        success: function (response) {
                            if (response.success) {
                                let data = response.data.response.data;
                                $('#snappbox-response').html('<span style="color:green;">' + response.data.response.message + ' ' + data.finalCustomerFare + '</span>');
                            } else {
                                $('#snappbox-response').html('<span style="color:red;">Error: ' + response.data + '</span>');
                            }
                        },
                        error: function () {
                            $('#snappbox-response').text('Error sending order.');
                        }
                    });
                });

                $('#snappbox-cancel-order').on('click', function (e) {
                    e.preventDefault();
                    let orderId = $(this).data('order-id');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'cancel_snappbox_order',
                            order_id: orderId,
                            woo_order_id: <?php echo $order->get_id(); ?>
                        },
                        beforeSend: function () {
                            $('#snappbox-cancel-response').text('Cancelling...');
                        },
                        success: function (response) {
                            if (response.success) {
                                $('#snappbox-cancel-response').html('<span style="color:green;">' + response.data + '</span>');
                                location.reload();
                            } else {
                                $('#snappbox-cancel-response').html('<span style="color:red;">Error: ' + response.data + '</span>');
                            }
                        },
                        error: function () {
                            $('#snappbox-cancel-response').text('Error cancelling order.');
                        }
                    });
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

    public function handle_get_pricing()
    {
        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Order ID missing');
        }

        $order_id = intval($_POST['order_id']);
        if (!$order_id) {
            wp_send_json_error('Invalid Order ID');
        }

        $pricing_api = new SnappBoxPriceHandler();
        $response = $pricing_api->get_pricing($order_id); 
        print_r($response);
        die();
        if ($response['success']) {
            wp_send_json_success([
                'fare' => $response['data']['finalCustomerFare']
            ]);
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

    public function check_order_status()
    {
        $orderID = get_post_meta($_GET['id'], '_snappbox_order_id', true);
        $getResponse = get_post_meta($orderID, '_snappbox_last_api_response', true);
        $last_called = get_post_meta($orderID, '_snappbox_last_api_call', true);

        if ($getResponse) {
            echo ('<p>Status: ' . esc_html($getResponse->status) . '</p>');
        }

        if (!$last_called || (time() - (int)$last_called) > 300) {
            $statusCehck = new SnappOrderStatus();
            $response = $statusCehck->get_order_status($orderID);

            if (!is_wp_error($response)) {
                update_post_meta($orderID, '_snappbox_last_api_response', $response);
                update_post_meta($orderID, '_snappbox_last_api_call', time());
            } else {
                error_log('API Error: ' . $response->get_error_message());
            }
        }
    }
}
