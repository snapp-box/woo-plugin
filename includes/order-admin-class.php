<?php

namespace Snappbox;

if (!\defined('ABSPATH')) {
    exit;
}

require_once(\trailingslashit(SNAPPBOX_DIR) . 'includes/api/create-order-class.php');
require_once(\trailingslashit(SNAPPBOX_DIR) . 'includes/api/cancel-order-class.php');
require_once(\trailingslashit(SNAPPBOX_DIR) . 'includes/api/status-check-class.php');
require_once(\trailingslashit(SNAPPBOX_DIR) . 'includes/api/pricing-class.php');
require_once(\trailingslashit(SNAPPBOX_DIR) . 'includes/convert-woo-cities-to-snappbox.php');

class SnappBoxOrderAdmin
{
    public function __construct($accessToken = SNAPPBOX_API_TOKEN)
    {
        \add_action('admin_enqueue_scripts', [$this, 'snappb_enqueue_assets']);
        \add_action('woocommerce_admin_order_data_after_order_details', [$this, 'snappb_display_order_admin_box'], 20, 1);
        \add_action('wp_ajax_snappb_create_order', [$this, 'snappb_handle_create_snappbox_order']);
        \add_action('wp_ajax_snappb_cancel_order', [$this, 'snappb_handle_cancel_snappbox_order']);
        \add_action('wp_ajax_snappb_get_pricing',          [$this, 'snappb_handle_get_pricing']);
    }


    public function snappb_enqueue_assets()
{
    \wp_enqueue_style(
        'maplibre-gl',
        \trailingslashit(SNAPPBOX_URL) . 'assets/css/leaflet.css',
        [],
        '1.9.4'
    );
    \wp_enqueue_script(
        'maplibre-gl',
        \trailingslashit(SNAPPBOX_URL) . 'assets/js/leaflet.js',
        [],
        '1.9.4',
        true
    );

    \wp_enqueue_style(
        'snappbox-style',
        \trailingslashit(SNAPPBOX_URL) . 'assets/css/style.css',
        [],
        \filemtime(\trailingslashit(SNAPPBOX_DIR) . 'assets/css/style.css')
    );

    \wp_enqueue_style(
        'snappbox-admin',
        \trailingslashit(SNAPPBOX_URL) . 'assets/css/admin-snappbox.css',
        ['snappbox-style'],
        \filemtime(\trailingslashit(SNAPPBOX_DIR) . 'assets/css/admin-snappbox.css')
    );

    \wp_enqueue_script(
        'snappbox-admin',
        \trailingslashit(SNAPPBOX_URL) . 'assets/js/admin-snappbox.js',
        ['jquery', 'maplibre-gl'],
        \filemtime(\trailingslashit(SNAPPBOX_DIR) . 'assets/js/admin-snappbox.js'),
        true
    );

    \wp_localize_script('snappbox-admin', 'SNAPPBOX_GLOBAL', [
        'ajaxUrl'      => \admin_url('admin-ajax.php'),
        'nonce'        => \wp_create_nonce('snappbox_admin_actions'),
        'rtlPluginUrl' => \trailingslashit(SNAPPBOX_URL) . 'assets/js/mapbox-gl-rtl-text.js',
        'mapStyleUrl'  => 'https://tile.snappmaps.ir/styles/snapp-style-v4.1.2/style.json',
        'i18n'         => [
            'priceFetching' => 'در حال دریافت قیمت...',
            'priceError'    => 'خطا در دریافت قیمت.',
            'requestError'  => 'خطا در ارسال درخواست.',
            'unknownError'  => 'Unknown error',
            'cancelError'   => 'Error cancelling order.',
            'orderSendErr'  => 'Error sending order.',
            'popupCustomer' => 'موقعیت مشتری',
            'close'         => \__('Close', 'snappbox'),
            'created'       => 'Created',
        ],
    ]);
}



    public function snappb_display_order_admin_box($order)
    {
        $nonce = \wp_create_nonce('snappbox_admin_actions');
?>
        </div>
        <div class="order_data_column_fullwidth">
            <?php \wp_nonce_field('snappbox_admin_actions', 'nonce'); ?>

            <h3><?php \esc_html_e('SnappBox', 'snappbox'); ?></h3>

            <?php
            $this->snappb_display_map_in_admin_order($order);
            $this->snappb_display_location_in_order_admin($order);

            echo '<b>' . \esc_html__('Address', 'snappbox') . '</b> : ' . \esc_html($order->get_shipping_address_1());

            $free_delivery = $order->get_meta('_free_delivery');
            if ($free_delivery) {
                echo '<div><b>' . \esc_html($free_delivery) . '</b></div>';
            }

            $this->snappb_check_order_status($order);

            echo '<div id="snappbox-admin-context"
                     data-nonce="' . \esc_attr($nonce) . '"
                     data-currency="' . \esc_attr(\get_woocommerce_currency()) . '"
                     data-woo-order-id="' . (int) $order->get_id() . '"
                   ></div>';

            $this->snappb_display_snappbox_order_button($order, $nonce);
            ?>
        </div>
        <div>
            <?php
        }


        public function snappb_display_location_in_order_admin($order)
        {
            $orderID = \get_post_meta($order->get_id(), '_snappbox_order_id', true);
            if ($orderID) {
                echo '<div class="sb-mb-20"><strong>' . \esc_html__('Order ID', 'snappbox') . ': </strong>' . \esc_html($orderID) . '</div>';
            }
        }


        public function snappb_display_map_in_admin_order($order)
        {
            $latitude  = \get_post_meta($order->get_id(), '_customer_latitude',  true);
            $longitude = \get_post_meta($order->get_id(), '_customer_longitude', true);

            if ($latitude && $longitude) {
                $lat = (float) $latitude;
                $lng = (float) $longitude;

                echo '<div id="admin-osm-map"
                     class="sb-admin-map"
                     data-lat="' . \esc_attr($lat) . '"
                     data-lng="' . \esc_attr($lng) . '"
                  ></div>';
            }
        }


        public function snappb_display_snappbox_order_button($order, $nonce)
        {
            $snappboxOrder = \get_post_meta($order->get_id(), '_snappbox_order_id', true);
            $day           = $order->get_meta('_snappbox_day');
            $time          = $order->get_meta('_snappbox_time');
            $getResponse   = $snappboxOrder ? \get_post_meta($snappboxOrder, '_snappbox_last_api_response', true) : null;
            $onDeliver = \maybe_unserialize(\get_option('woocommerce_snappbox_shipping_method_settings'));
            if ($day && $time) {
                $ts        = $day ? \strtotime($day . ' 12:00:00') : false;
                $dateLabel = $ts ? \wp_date('l j F Y', $ts) : $day;
            ?>
                <div class="snappbox-order-container clearfix">
                    <p><b><?php \esc_html_e('Delivery Date and Time', 'snappbox'); ?> :</b>
                        <?php echo \esc_html($dateLabel); ?> - <?php echo \esc_html($time); ?>
                    </p>
                </div>
            <?php
            }
            if ($onDeliver['ondelivery'] == 'yes'){?>
            <div class="snappbox-order-container clearfix">
                    <p><b><?php \esc_html_e('SnappBox Payment after delivery', 'snappbox'); ?></b></p>
                </div>
            <?php
            }
            if (! $snappboxOrder || $getResponse->status == 'CANCELLED' ) :
            ?>
                <div class="sb-modal" id="sb-pricing-modal" hidden>
                    <div class="sb-modal__box">
                        <div class="sb-modal__header">
                            <h3><?php \esc_html_e('SnappBox Price', 'snappbox'); ?></h3>
                        </div>

                        <div class="sb-modal__content">
                            <p id="pricing-message"><?php \esc_html_e('Calculating Price', 'snappbox'); ?>...</p>

                            <div class="voucher-code-wrapper">
                                <input type="text" id="sb-voucher-code" name="voucher_code" placeholder="<?php \esc_html_e('Enter Your Voucher Code', 'snappbox'); ?>" />
                                <button data-order-id="<?php echo \esc_attr($order->get_id()); ?>" id="add-voucher-code"><?php \esc_html_e('Operate', 'snappbox'); ?></button>
                            </div>

                            <div class="snappbox-order-container">
                                <button id="snappbox-create-order"
                                    data-order-id="<?php echo \esc_attr($order->get_id()); ?>"
                                    class="snappbox-btn button button-primary"
                                    hidden>
                                    <?php \esc_html_e('Send to SnappBox', 'snappbox'); ?>
                                </button>
                            </div>

                            <img class="ct-order-loading" src="<?php echo \esc_url(\trailingslashit(SNAPPBOX_URL) . 'assets/img/ld.svg'); ?>" alt="" hidden />
                            <span id="snappbox-response"></span>
                        </div>

                        <div class="vds-content" hidden>
                            <img class="vds-image" src="<?php echo \esc_url(\trailingslashit(SNAPPBOX_URL) . 'assets/img/success.png'); ?>" alt="" />
                            <span id="snappbox-response-victory"></span>
                        </div>

                        <a href="#" class="sb-modal__close"><?php \esc_html_e('Close', 'snappbox'); ?></a>
                    </div>
                </div>

                <div class="snappbox-order-container clearfix sb-actions-row">
                    <button id="snappbox-pricing-order"
                        data-order-id="<?php echo \esc_attr($order->get_id()); ?>"
                        class="snappbox-btn button button-primary">
                        <?php \esc_html_e('Get SnappBox Price', 'snappbox'); ?>
                    </button>
                    <img class="loading" src="<?php echo \esc_url(\trailingslashit(SNAPPBOX_URL) . 'assets/img/ld.svg'); ?>" alt="" hidden />
                </div>
                <?php
            else :
                if ($getResponse && isset($getResponse->canCancel) && (int) $getResponse->canCancel === 1) : ?>
                    <div class="snappbox-cancel-container sb-actions-row">
                        <button id="snappbox-cancel-order"
                            data-order-id="<?php echo \esc_attr($snappboxOrder); ?>"
                            class="cancel-order button button-secondary">
                            <?php \esc_html_e('Cancel Order', 'snappbox'); ?>
                        </button>
                        <img class="cancel-order-loading" src="<?php echo \esc_url(\trailingslashit(SNAPPBOX_URL) . 'assets/img/ld.svg'); ?>" alt="" hidden />
                        <span id="snappbox-cancel-response"></span>
                    </div>
    <?php
                endif;
            endif;
        }

        public function snappb_handle_create_snappbox_order()
        {
            \check_ajax_referer('snappbox_admin_actions', 'nonce');

            if (empty($_POST['order_id'])) {
                \wp_send_json_error(\__('Order ID missing', 'snappbox'));
            }

            $order_id = \absint(\wp_unslash($_POST['order_id']));
            if (! $order_id) {
                \wp_send_json_error(\__('Invalid Order ID', 'snappbox'));
            }

            if (! \current_user_can('edit_shop_order', $order_id) && ! \current_user_can('manage_woocommerce')) {
                \wp_send_json_error(\__('Permission denied.', 'snappbox'), 403);
            }

            $voucherCode = isset($_POST['voucher_code']) ? sanitize_text_field(wp_unslash($_POST['voucher_code'])) : '';
            $snappboxOrder = new \Snappbox\Api\SnappBoxCreateOrder();
            $response      = $snappboxOrder->snappb_handle_create_order($order_id, $voucherCode);

            if (! \is_array($response)) {
                \wp_send_json_error(\__('Unexpected response', 'snappbox'));
            }

            if (! empty($response['success'])) {
                \wp_send_json_success(['response' => $response]);
            } else {
                $msg = isset($response['message']) ? $response['message'] : \__('Create order failed', 'snappbox');
                \wp_send_json_error($msg);
            }
        }

   
        public function snappb_handle_get_pricing()
        {
            \check_ajax_referer('snappbox_admin_actions', 'nonce');

            if (empty($_POST['order_id'])) {
                \wp_send_json_error(\__('Order ID missing', 'snappbox'));
            }

            $order_id = \absint(\wp_unslash($_POST['order_id']));
            if (! $order_id) {
                \wp_send_json_error(\__('Invalid Order ID', 'snappbox'));
            }

            if (! \current_user_can('edit_shop_order', $order_id) && ! \current_user_can('manage_woocommerce')) {
                \wp_send_json_error(\__('Permission denied.', 'snappbox'), 403);
            }

            $order = \wc_get_order($order_id);
            if (! $order) {
                \wp_send_json_error(\__('Order not found', 'snappbox'));
            }

            $city        = \get_post_meta($order->get_id(), 'customer_city', true);
            $state       = \strtolower($city);
            $voucherCode = isset($_POST['voucher_code']) ? sanitize_text_field(wp_unslash($_POST['voucher_code'])) : '';
            $allCities   = new \Snappbox\SnappBoxCityHelper();
            $city_map    = $allCities->snappb_get_city_to_state_map();
            $state_code  = isset($city_map[\strtoupper($state)]) ? $city_map[\strtoupper($state)] : null;

            $settings      = \maybe_unserialize(\get_option('woocommerce_snappbox_shipping_method_settings'));
            $stored_cities = isset($settings['snappbox_cities']) ? (array) $settings['snappbox_cities'] : [];
            
            // if (empty($state)) {
            //     \wp_send_json_error('استان / شهر فعال نیست');
            // }
            // if (! \in_array($state, $stored_cities, true)) {
            //     \wp_send_json_error('ارسال اسنپ‌باکس برای این شهر فعال نیست.');
            // }

            $pricing_api = new \Snappbox\Api\SnappBoxPriceHandler();
            $response    = $pricing_api->snappb_get_pricing($order_id, $state_code, '', '', '',  $voucherCode);
            if (! empty($response['success']) && isset($response['data']['finalCustomerFare'])) {
                \wp_send_json_success([
                    'finalCustomerFare' => $response['data']['finalCustomerFare'],
                    'totalFare'         => $response['data']['totalFare'] ?? null,
                ]);
            }

            $msg = isset($response['message']) ? $response['message'] : 'خطا در دریافت قیمت.';
            \wp_send_json_error($msg);
        }

        public function snappb_handle_cancel_snappbox_order()
        {
            \check_ajax_referer('snappbox_admin_actions', 'nonce');

            if (empty($_POST['order_id'])) {
                \wp_send_json_error(\__('Order ID missing', 'snappbox'));
            }
            if (empty($_POST['woo_order_id'])) {
                \wp_send_json_error(\__('Woo order ID missing', 'snappbox'));
            }

            $order_id     = \sanitize_text_field(\wp_unslash($_POST['order_id']));
            $woo_order_id = \absint(\wp_unslash($_POST['woo_order_id']));
            if (! $woo_order_id) {
                \wp_send_json_error(\__('Invalid Woo order ID', 'snappbox'));
            }

            if (! \current_user_can('edit_shop_order', $woo_order_id) && ! \current_user_can('manage_woocommerce')) {
                \wp_send_json_error(\__('Permission denied.', 'snappbox'), 403);
            }

            $snappbox_api = new \Snappbox\Api\SnappBoxCancelOrder();
            $response     = $snappbox_api->snappb_cancel_order($order_id);

            if (isset($response['success']) && $response['success'] === false) {
                \delete_post_meta($woo_order_id, '_snappbox_order_id');
                \delete_post_meta($woo_order_id, '_snappbox_last_api_response');
                \delete_post_meta($woo_order_id, '_snappbox_last_api_call');
                $msg = isset($response['message']) ? $response['message'] : \__('Cancelled', 'snappbox');
                \wp_send_json_success($msg);
            } else {
                $msg = isset($response['message']) ? $response['message'] : \__('Cancel failed', 'snappbox');
                \wp_send_json_error($msg);
            }
        }

        public function snappb_check_order_status($order)
        {
            $meta_order_id = \get_post_meta($order->get_id(), '_snappbox_order_id', true);
            $getResponse   = $meta_order_id ? \get_post_meta($meta_order_id, '_snappbox_last_api_response', true) : null;

            if ($getResponse && isset($getResponse->statusText)) {
                echo '<p><b>' . \esc_html__('Status', 'snappbox') . '</b>: ' . \esc_html($getResponse->statusText) . '</p>';
            }

            if ($meta_order_id) {
                $statusCheck = new \Snappbox\Api\SnappOrderStatus();
                $response    = $statusCheck->get_order_status($meta_order_id);
                if (! \is_wp_error($response)) {
                    \update_post_meta($meta_order_id, '_snappbox_last_api_response', $response);
                    \update_post_meta($meta_order_id, '_snappbox_last_api_call', \time());
                } else {
                    echo \esc_html('API Error: ' . $response->get_error_message());
                }
            }
        }
    }
