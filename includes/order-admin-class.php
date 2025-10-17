<?php
if (! defined('ABSPATH')) {
    exit;
}

require_once(SNAPPBOX_DIR . 'includes/create-order-class.php');
require_once(SNAPPBOX_DIR . 'includes/cancel-order-class.php');
require_once(SNAPPBOX_DIR . 'includes/status-check-class.php');
require_once(SNAPPBOX_DIR . 'includes/pricing-class.php');
require_once(SNAPPBOX_DIR . 'includes/convert-woo-cities-to-snappbox.php');

class SnappBoxOrderAdmin
{
    public function __construct($accessToken = SNAPPBOX_API_TOKEN)
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_leaflet']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_order_admin_box'], 20, 1);

        // Admin-ajax handlers
        add_action('wp_ajax_create_snappbox_order', [$this, 'handle_create_snappbox_order']);
        add_action('wp_ajax_cancel_snappbox_order', [$this, 'handle_cancel_snappbox_order']);
        add_action('wp_ajax_get_pricing', [$this, 'handle_get_pricing']);
    }

    public function enqueue_leaflet()
    {
        wp_enqueue_style(
            'leaflet',
            trailingslashit(SNAPPBOX_URL) . 'assets/css/leaflet.css',
            [],
            '1.9.4'
        );

        wp_enqueue_script(
            'leaflet',
            trailingslashit(SNAPPBOX_URL) . 'assets/js/leaflet.js',
            [],
            '1.9.4',
            true
        );

        wp_enqueue_style(
            'snappbox-style',
            trailingslashit(SNAPPBOX_URL) . 'assets/css/style.css',
            [],
            filemtime(trailingslashit(SNAPPBOX_DIR) . 'assets/css/style.css')
        );
    }

    public function display_location_in_order_admin($order)
    {
        $orderID = get_post_meta($order->get_id(), '_snappbox_order_id', true);
        if ($orderID) {
            echo '<div style="margin-bottom:20px;"><strong>' . esc_html__('Order ID', 'sb-delivery') . ': </strong>' . esc_html($orderID) . '</div>';
        }
    }

    public function display_map_in_admin_order($order)
    {
        $latitude  = get_post_meta($order->get_id(), '_customer_latitude', true);
        $longitude = get_post_meta($order->get_id(), '_customer_longitude', true);

        if ($latitude && $longitude) {
            $lat = (float) $latitude;
            $lng = (float) $longitude;

            echo '<div id="admin-osm-map" style="height: 400px; margin-top: 20px;"></div>';
?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof maplibregl === 'undefined') {
                        console.error('MapLibre not loaded');
                        return;
                    }

                    maplibregl.setRTLTextPlugin(
                        'https://unpkg.com/@mapbox/mapbox-gl-rtl-text@0.3.0/dist/mapbox-gl-rtl-text.js',
                        null,
                        true
                    );

                    var lat = <?php echo wp_json_encode($lat); ?>;
                    var lng = <?php echo wp_json_encode($lng); ?>;

                    var map = new maplibregl.Map({
                        container: 'admin-osm-map',
                        style: 'https://tile.snappmaps.ir/styles/snapp-style-v4.1.2/style.json',
                        center: [lng, lat],
                        zoom: 15,
                        attributionControl: true
                    });

                    map.addControl(new maplibregl.NavigationControl({
                        visualizePitch: true
                    }), 'top-right');

                    new maplibregl.Marker().setLngLat([lng, lat]).addTo(map);

                    var popup = new maplibregl.Popup({
                            closeOnClick: false
                        })
                        .setLngLat([lng, lat])
                        .setHTML('<div style="direction:rtl;unicode-bidi:plaintext;">موقعیت مشتری</div>')
                        .addTo(map);
                });
            </script>
        <?php
        }
    }


    public function display_order_admin_box($order)
    {
        $nonce = wp_create_nonce('snappbox_admin_actions');
        ?>
        </div>
        <div class="order_data_column_fullwidth">
            <?php wp_nonce_field('snappbox_admin_actions', 'nonce'); ?>
            <h3><?php esc_html_e('SnappBox', 'sb-delivery'); ?></h3>
            <?php
            $this->display_map_in_admin_order($order);
            $this->display_location_in_order_admin($order);

            echo '<b>' . esc_html__('Address', 'sb-delivery') . '</b> : ' . esc_html($order->get_shipping_address_1());

            $free_delivery = $order->get_meta('_free_delivery');
            if ($free_delivery) {
                echo '<div><b>' . esc_html($free_delivery) . '</b></div>';
            }

            $this->check_order_status($order);

            $this->display_snappbox_order_button($order, $nonce);
            ?>
        </div>
        <?php
    }

    public function display_snappbox_order_button($order, $nonce)
    {
        $snappboxOrder = get_post_meta($order->get_id(), '_snappbox_order_id', true);
        $day           = $order->get_meta('_snappbox_day');
        $time          = $order->get_meta('_snappbox_time');

        if ($day && $time) {
            $ts        = $day ? strtotime($day . ' 12:00:00') : false;
            $dateLabel = $ts ? wp_date('l j F Y', $ts) : $day;
        ?>
            <div class="snappbox-order-container clearfix">
                <p><b><?php esc_html_e('Delivery Date and Time', 'sb-delivery'); ?> :</b> <?php echo esc_html($dateLabel); ?> - <?php echo esc_html($time); ?></p>
            </div>
        <?php
        }

        if (! $snappboxOrder) {
        ?>
            <div class="modal" style="display:none;justify-content:center;align-items:center;background-color:rgba(255,255,255,0.7);z-index:1100;left:0;right:0;top:0;bottom:0;position:absolute;">
                <div class="modal-box" style="width:40%;text-align:center;overflow:hidden;display:flex;flex-direction:column;background-color:#fff;padding-bottom:20px;border:1px solid #ebebeb;border-radius:15px;">
                    <div class="modal-header" style="height:100px;background-color:#22a958;width:100%;">
                        <h3 style="padding-top:15px;color:#fff;"><?php esc_html_e('SnappBox Price', 'sb-delivery'); ?></h3>
                    </div>
                    <div class="modal-content" style="margin-top: 30px;">
                        
                        <p id="pricing-message"><?php esc_html_e('Calculating Price', 'sb-delivery') ?>...</p>
                        <div class="voucher-code-wrapper">
                            <input type="text" id="sb-voucher-code" name="voucher_code" placeholder="<?php esc_html_e('Enter Your Voucher Code', 'sb-delivery'); ?>" />
                            <button data-order-id="<?php echo esc_attr($order->get_id()); ?>" id="add-voucher-code"><?php esc_html_e('Operate', 'sb-delivery'); ?></button>
                        </div>

                        <div class="snappbox-order-container">
                            <button id="snappbox-create-order"
                                data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                                class="snappbox-btn button button-primary"
                                style="display:none">
                                <?php esc_html_e('Send to SnappBox', 'sb-delivery'); ?>
                            </button>
                        </div>
                        <img class="ct-order-loading" style="visibility:hidden" src="<?php echo esc_url(trailingslashit(SNAPPBOX_URL) . 'assets/img/ld.svg'); ?>" />
                        <span id="snappbox-response"></span>
                    </div>

                    <div class="vds-content" style="display:none">
                        <img style="width:50%" src="<?php echo esc_url(trailingslashit(SNAPPBOX_URL) . 'assets/img/success.png');?>" />
                        <span id="snappbox-response-victory"></span>
                    </div>

                    <a href="#" class="close" style="margin-top:15px;"><?php esc_html_e('Close', 'sb-delivery'); ?></a>
                </div>
            </div>

            <div class="snappbox-order-container clearfix" style="clear: both;margin-top: 20px;float: left;width: 100%;">
                <button id="snappbox-pricing-order"
                    data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                    class="snappbox-btn button button-primary">
                    <?php esc_html_e('Get SnappBox Price', 'sb-delivery'); ?>
                </button>
                <img class="loading" style="display:none" src="<?php echo esc_url(trailingslashit(SNAPPBOX_URL) . 'assets/img/ld.svg'); ?>" />
            </div>
            <?php
        } else {

            $order_meta_id = get_post_meta($order->get_id(), '_snappbox_order_id', true);
            $getResponse   = $order_meta_id ? get_post_meta($order_meta_id, '_snappbox_last_api_response', true) : null;

            if ($getResponse && isset($getResponse->canCancel) && (int) $getResponse->canCancel === 1) {
            ?>
                <div class="snappbox-cancel-container" style="clear: both;margin-top: 20px;float: left;width: 100%;">
                    <button id="snappbox-cancel-order"
                        data-order-id="<?php echo esc_attr($snappboxOrder); ?>"
                        class="cancel-order button button-secondary">
                        <?php esc_html_e('Cancel Order', 'sb-delivery'); ?>
                    </button>
                    <img class="cancel-order-loading" style="visibility:hidden" src="<?php echo esc_url(trailingslashit(SNAPPBOX_URL) . 'assets/img/ld.svg'); ?>" />
                    <span id="snappbox-cancel-response"></span>
                </div>
        <?php
            }
        }

        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var SNAPPBOX_AJAX = {
                    url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                    nonce: '<?php echo esc_js($nonce); ?>',
                    wooCurrency: '<?php echo esc_js(get_woocommerce_currency()); ?>',
                    wooOrderId: <?php echo (int) $order->get_id(); ?>
                };

                function snappboxRialToToman(currency) {
                    return parseInt(currency, 10) / 10;
                }

                $('.close').on('click', function(e) {
                    e.preventDefault();
                    $('#sb-voucher-code').val('');
                    $('.modal').hide();
                });

                $('#snappbox-pricing-order, #add-voucher-code').on('click', function(e) {
                    e.preventDefault();

                    var orderId = $(this).data('order-id');
                    var voucherCode = $('#sb-voucher-code').val();
                    $('.loading').css('display', 'inline-block');

                    $.ajax({
                        url: SNAPPBOX_AJAX.url,
                        type: 'POST',
                        data: {
                            action: 'get_pricing',
                            order_id: orderId,
                            voucher_code: voucherCode,
                            nonce: SNAPPBOX_AJAX.nonce
                        },
                        beforeSend: function() {
                            $('#pricing-message').text('در حال دریافت قیمت...');
                            $('#snappbox-create-order').attr('disabled', 'disabled');
                        },
                        success: function(response) {
                            $('.modal').css('display', 'flex');
                            $('#snappbox-create-order').removeAttr('disabled');

                            if (response && response.success) {
                                // Raw values from backend
                                var fare = Number(response.data.finalCustomerFare); 
                                var totalFare = Number(response.data.totalFare);

                                $('#snappbox-create-order').css('display', 'block');

                                var finalFare, totalFareDisplay, simbol;
                                var hasTotal = totalFare != null && !isNaN(totalFare);

                                if (SNAPPBOX_AJAX.wooCurrency === 'IRT') {
                                    finalFare = snappboxRialToToman(fare);
                                    totalFareDisplay = hasTotal ? snappboxRialToToman(totalFare) : undefined;
                                    simbol = 'تومان';
                                } else {
                                    finalFare = fare;
                                    totalFareDisplay = hasTotal ? totalFare : undefined;
                                    simbol = 'ریال';
                                }

                                var fmt = function(n) {
                                    return new Intl.NumberFormat('en-IR', {
                                        maximumSignificantDigits: 3
                                    }).format(n);
                                };

                                $('.loading').css('display', 'none');

                                var htmlMsg;
                                if (hasTotal && !isNaN(totalFareDisplay) && totalFareDisplay > 0 && totalFareDisplay !== finalFare) {
                                    htmlMsg =
                                        'قیمت کل: <span style="text-decoration:line-through;">' + fmt(totalFareDisplay) + ' ' + simbol + '</span>' +
                                        '<br>' +
                                        'قیمت با تخفیف: ' + fmt(finalFare) + ' ' + simbol;
                                } else {
                                    htmlMsg = 'قیمت تخمینی: ' + fmt(finalFare) + ' ' + simbol;
                                }

                                $('#pricing-message').html(htmlMsg);

                            } else {
                                $('.loading').css('display', 'none');
                                var msg;
                                if (response && response.data && response.data.voucherMessage) {
                                    msg = response.data.voucherMessage;
                                } else {
                                    msg = (response && response.data && response.data.message) ? response.data.message : 'خطا در دریافت قیمت.';
                                    $('#snappbox-create-order').css('display', 'none');
                                }
                                $('#pricing-message').text(msg);
                                $('#snappbox-create-order').attr('disabled', 'disabled');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('AJAX error:', textStatus, errorThrown, jqXHR);
                            $('#pricing-message').text('خطا در ارسال درخواست.');
                            $('.loading').css('display', 'none');
                        }
                    });
                });


                $('#snappbox-create-order').on('click', function(e) {
                    e.preventDefault();
                    var orderId = $(this).data('order-id');
                    var voucherCode = $('#sb-voucher-code').val();
                    $.ajax({
                        url: SNAPPBOX_AJAX.url,
                        type: 'POST',
                        data: {
                            action: 'create_snappbox_order',
                            order_id: orderId,
                            voucher_code: voucherCode,
                            nonce: SNAPPBOX_AJAX.nonce
                        },
                        beforeSend: function() {
                            $('.ct-order-loading').css('visibility', 'visible');
                        },
                        success: function(response) {
                            if (response && response.success && response.response && response.response.data && (response.response.status_code === 201 || response.response.status_code === '201')) {
                                $('.modal-content').hide();
                                $('.vds-content').show();
                                $('#snappbox-response-victory').html('<span style="color:green;">' + (response.response.message || 'Created') + '</span>');
                                window.location.reload();
                            } else {
                                var errMsg = (response && response.response) ? response.response.message : 'Unknown error';
                                $('#snappbox-response').html('<span style="color:red;">Error: ' + errMsg + '</span>');
                            }
                            $('.ct-order-loading').css('visibility', 'hidden');
                        },
                        error: function() {
                            $('#snappbox-response').text('Error sending order.');
                            $('.ct-order-loading').css('visibility', 'hidden');
                        }
                    });
                });

                $('#snappbox-cancel-order').on('click', function(e) {
                    e.preventDefault();
                    var orderId = $(this).data('order-id');

                    $.ajax({
                        url: SNAPPBOX_AJAX.url,
                        type: 'POST',
                        data: {
                            action: 'cancel_snappbox_order',
                            order_id: orderId,
                            woo_order_id: SNAPPBOX_AJAX.wooOrderId,
                            nonce: SNAPPBOX_AJAX.nonce
                        },
                        beforeSend: function() {
                            $('.cancel-order-loading').css('visibility', 'visible');
                        },
                        success: function(response) {
                            if (response && response.success) {
                                $('#snappbox-cancel-response').html('<span style="color:green;">' + response.data + '</span>');
                                $('.cancel-order-loading').css('visibility', 'hidden');
                                location.reload();
                            } else {
                                var msg = (response && response.data) ? response.data : 'خطا';
                                $('#snappbox-cancel-response').html('<span style="color:red;">Error: ' + msg + '</span>');
                                $('.cancel-order-loading').css('visibility', 'hidden');
                            }
                        },
                        error: function() {
                            $('#snappbox-cancel-response').text('Error cancelling order.');
                            $('.cancel-order-loading').css('visibility', 'hidden');
                        }
                    });
                });
            });
        </script>
<?php
    }


    public function handle_create_snappbox_order()
    {
        check_ajax_referer('snappbox_admin_actions', 'nonce');

        if (empty($_POST['order_id'])) {
            wp_send_json_error(__('Order ID missing', 'sb-delivery'));
        }

        $order_id = absint(wp_unslash($_POST['order_id']));
        if (! $order_id) {
            wp_send_json_error(__('Invalid Order ID', 'sb-delivery'));
        }

        if (! current_user_can('edit_shop_order', $order_id) && ! current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'sb-delivery'), 403);
        }
        $voucherCode = wp_unslash($_POST['voucher_code']);
        $snappbox_order = new SnappBoxCreateOrder();
        $response       = $snappbox_order->handleCreateOrder($order_id, $voucherCode);

        if (! is_array($response)) {
            wp_send_json_error(__('Unexpected response', 'sb-delivery'));
        }

        if (! empty($response['success'])) {
            wp_send_json_success(['response' => $response]);
        } else {
            $msg = isset($response['message']) ? $response['message'] : __('Create order failed', 'sb-delivery');
            wp_send_json_error($msg);
        }
    }

    public function handle_get_pricing()
    {
        check_ajax_referer('snappbox_admin_actions', 'nonce');

        if (empty($_POST['order_id'])) {
            wp_send_json_error(__('Order ID missing', 'sb-delivery'));
        }

        $order_id = absint(wp_unslash($_POST['order_id']));
        if (! $order_id) {
            wp_send_json_error(__('Invalid Order ID', 'sb-delivery'));
        }

        if (! current_user_can('edit_shop_order', $order_id) && ! current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'sb-delivery'), 403);
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            wp_send_json_error(__('Order not found', 'sb-delivery'));
        }


        
        $city = get_post_meta($order->get_id(), 'customer_city', true);
        $state      = strtolower($city);
        $voucherCode = wp_unslash($_POST['voucher_code']);
        $allCities  = new SnappBoxCityHelper();
        $city_map   = $allCities->get_city_to_state_map();
        $state_code = isset($city_map[strtoupper($state)]) ? $city_map[strtoupper($state)] : null;

        $settings      = maybe_unserialize(get_option('woocommerce_snappbox_shipping_method_settings'));
        $stored_cities = isset($settings['snappbox_cities']) ? (array) $settings['snappbox_cities'] : [];

        if (! $state) {
            wp_send_json_error('استان / شهر فعال نیست');
        }
        if (! in_array($state, $stored_cities, true)) {
            wp_send_json_error('ارسال اسنپ‌باکس برای این شهر فعال نیست.');
        }

        $pricing_api = new SnappBoxPriceHandler();
        $response    = $pricing_api->get_pricing($order_id, $state_code, $voucherCode);

        if (! empty($response['success']) && isset($response['data']['finalCustomerFare'])) {
            wp_send_json_success([
                'finalCustomerFare' => $response['data']['finalCustomerFare'],
            ]);
        }

        $msg = isset($response['message']) ? $response['message'] : 'خطا در دریافت قیمت.';
        wp_send_json_error($msg);
    }

    public function handle_cancel_snappbox_order()
    {
        check_ajax_referer('snappbox_admin_actions', 'nonce');

        if (empty($_POST['order_id'])) {
            wp_send_json_error(__('Order ID missing', 'sb-delivery'));
        }
        if (empty($_POST['woo_order_id'])) {
            wp_send_json_error(__('Woo order ID missing', 'sb-delivery'));
        }

        $order_id     = sanitize_text_field(wp_unslash($_POST['order_id']));
        $woo_order_id = absint(wp_unslash($_POST['woo_order_id']));
        if (! $woo_order_id) {
            wp_send_json_error(__('Invalid Woo order ID', 'sb-delivery'));
        }

        if (! current_user_can('edit_shop_order', $woo_order_id) && ! current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'sb-delivery'), 403);
        }

        $snappbox_api = new SnappBoxCancelOrder();
        $response     = $snappbox_api->cancel_order($order_id);

        if (isset($response['success']) && $response['success'] === false) {
            delete_post_meta($woo_order_id, '_snappbox_order_id');
            delete_post_meta($woo_order_id, '_snappbox_last_api_response');
            delete_post_meta($woo_order_id, '_snappbox_last_api_call');
            $msg = isset($response['message']) ? $response['message'] : __('Cancelled', 'sb-delivery');
            wp_send_json_success($msg);
        } else {
            $msg = isset($response['message']) ? $response['message'] : __('Cancel failed', 'sb-delivery');
            wp_send_json_error($msg);
        }
    }

    public function check_order_status($order)
    {
        $meta_order_id = get_post_meta($order->get_id(), '_snappbox_order_id', true);
        $getResponse   = $meta_order_id ? get_post_meta($meta_order_id, '_snappbox_last_api_response', true) : null;

        if ($getResponse && isset($getResponse->statusText)) {
            echo '<p><b>' . esc_html__('Status', 'sb-delivery') . '</b>: ' . esc_html($getResponse->statusText) . '</p>';
        }

        if ($meta_order_id) {
            $statusCheck = new SnappOrderStatus();
            $response    = $statusCheck->get_order_status($meta_order_id);
            if (! is_wp_error($response)) {
                update_post_meta($meta_order_id, '_snappbox_last_api_response', $response);
                update_post_meta($meta_order_id, '_snappbox_last_api_call', time());
            } else {
                echo esc_html('API Error: ' . $response->get_error_message());
            }
        }
    }
}
