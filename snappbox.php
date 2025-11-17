<?php
/*
 * Plugin Name:  snappbox
 * Plugin URI: http://snapp-box.com/
 * Description: Official SnappBox WooCommerce Delivery Plugin
 * Version: 1.0
 * Author: SnappBox Team
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://snapp-box.com/wordpress-plugin
 * Text Domain: snappbox
 * Domain Path: /languages/
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0.0
 * WC tested up to: 9.7.0
 */

namespace Snappbox;

use WpOrg\Requests\Response;

if (! defined('ABSPATH')) {
    exit;
}

define('SNAPPBOX_DIR', plugin_dir_path(__FILE__));
define('SNAPPBOX_URL', plugin_dir_url(__FILE__));
define('SNAPPBOX_VERSION', '0.1.1');
define('SNAPPBOX_API_BASE_URL_STAGING', 'https://customer-stg.snapp-box.com/');
define('SNAPPBOX_API_BASE_URL_PRODUCTION', 'https://customer.snapp-box.com/');

global $snappb_api_base_url;

$snappb_settings_serialized = get_option('woocommerce_snappbox_shipping_method_settings');
$settings = is_array($snappb_settings_serialized) ? $snappb_settings_serialized : maybe_unserialize($snappb_settings_serialized);

if (is_array($settings)) {
    define('SNAPPBOX_SANDBOX', isset($settings['sandbox']) ? $settings['sandbox'] : false);
    define('SNAPPBOX_ONDELIVERY', isset($settings['ondelivery']) ? $settings['ondelivery'] : false);
} else {
    define('SNAPPBOX_SANDBOX', false);
    define('SNAPPBOX_ONDELIVERY', false);
}

$snappb_api_base_url = (SNAPPBOX_SANDBOX === 'yes')
    ? SNAPPBOX_API_BASE_URL_STAGING
    : SNAPPBOX_API_BASE_URL_PRODUCTION;

$snappb_api_key = $settings['snappbox_api'] ?? '';
define('SNAPPBOX_API_TOKEN', $snappb_api_key);


require_once SNAPPBOX_DIR . 'includes/woo-checkout-map.php';
require_once SNAPPBOX_DIR . 'includes/api/cities-class.php';
require_once SNAPPBOX_DIR . 'includes/order-admin-class.php';
require_once SNAPPBOX_DIR . 'includes/schedule-modal.php';
require_once SNAPPBOX_DIR . 'includes/add-meta-orderlist-class.php';
require_once SNAPPBOX_DIR . 'includes/quick-setup-wizard.php';
require_once SNAPPBOX_DIR . 'includes/api/near-by-class.php';
require_once SNAPPBOX_DIR . 'includes/api/snapp-reverse-class.php';



function snappbox_init()
{
    $currentUser = wp_get_current_user();

    if (class_exists('\Snappbox\SnappBoxOrderAdmin')) {
        new \Snappbox\SnappBoxOrderAdmin();
    }
    if (class_exists('\SnappBoxCities')) {
        new \Snappbox\Api\SnappBoxCities();
    }
    if (class_exists('\Snappbox\SnappBoxCheckout')) {
        new \Snappbox\SnappBoxCheckout();
    }
    if (class_exists('\Snappbox\SnappBoxWcOrderColumn')) {
        new \Snappbox\SnappBoxWcOrderColumn();
    }
    if (class_exists('\Snappbox\SnappBoxScheduleModal')) {
        new \Snappbox\SnappBoxScheduleModal();
    }

    if (class_exists('\WC_Shipping_Method')) {
        require_once SNAPPBOX_DIR . 'includes/shipping-method-class.php';
        add_action('woocommerce_shipping_init', function () {
            \Snappbox\SnappBoxShippingMethod::register();
        });
    }
    if (class_exists('\Snappbox\Api\SnappBoxNearBy')) {
        new \Snappbox\Api\SnappBoxNearBy();
    }
    if (! function_exists('register_block_type')) {
        return;
    }
}
add_action('plugins_loaded', __NAMESPACE__ . '\\snappbox_init');

add_action('wp_ajax_snapp_nearby',  __NAMESPACE__ . '\snappb_ajax_nearby');
add_action('wp_ajax_nopriv_snapp_nearby',  __NAMESPACE__ . '\\snappb_ajax_nearby');

function snappb_ajax_nearby()
{
    $lat = isset($_POST['lat']) ? floatval(sanitize_text_field(wp_unslash($_POST['lat']))) : null;
    $lng = isset($_POST['lng']) ? floatval(sanitize_text_field(wp_unslash($_POST['lng']))) : null;
    
    if ($lat === null || $lng === null) {
        wp_send_json_error(['message' => 'Invalid coordinates']);
    }

    $api = new \Snappbox\Api\SnappBoxNearBy();

    $response = $api->snappb_check_nearby([
        'latitude'  => $lat,
        'longitude' => $lng,
        'zoom'      => 15,
    ]);

    $items = $response['response'] ?? [];
    $found_valid = false;

    foreach ($items as $res) {
        if (
            isset($res['apiValue'], $res['count']) &&
            $res['apiValue'] === 'bike-without-box' &&
            $res['count'] > -1
        ) {
            $found_valid = true;
            break;
        }
    }
    if (!class_exists('\Snappbox\Api\SnappMapsReverseGeocoder')) {
        wp_send_json_error(['message' => 'Reverse geocoder class not found']);
    }
    
    if (!$found_valid) {
        wp_send_json_error(['message' => __('Your location is NOT supported by SnappBox', 'snappbox')]);
    }
    else{
        snappbox_store_city( $lat, $lng);
    }
    
}
function snappbox_store_city($lat, $lng)
{
    $settings_serialized = get_option('woocommerce_snappbox_shipping_method_settings');
    $settings = maybe_unserialize($settings_serialized);
    $settings['snappbox_latitude'] = $lat;
    $settings['snappbox_longitude'] = $lng;
    update_option('woocommerce_snappbox_shipping_method_settings', $settings);
}



function snappbox_activate()
{
    update_option('snappbox_qs_do_activation_redirect', 'yes', false);
    delete_transient('woocommerce_shipping_zones_cache');
}
\register_activation_hook(__FILE__, __NAMESPACE__ . '\\snappbox_activate');


add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});


add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\snappbox_enqueue_leaflet_map_js');
function snappbox_enqueue_leaflet_map_js()
{
    if (! is_checkout()) {
        return;
    }
    
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

    wp_enqueue_script(
        'snappbox-map-checkout',
        trailingslashit(SNAPPBOX_URL) . 'assets/js/gutenberg-map.js',
        ['leaflet'],
        '1.0',
        true
    );
}


add_action('woocommerce_after_order_notes', function () {
    wp_nonce_field('snappbox_geo_meta', 'snappbox_geo_nonce');
});


add_action('woocommerce_checkout_create_order', function ($order, $data) {
    if (
        empty($_POST['snappbox_geo_nonce'])
        || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['snappbox_geo_nonce'])), 'snappbox_geo_meta')
    ) {
        return;
    }

    if (isset($_POST['customer_latitude'], $_POST['customer_longitude'])) {
        $lat = (float) sanitize_text_field(wp_unslash($_POST['customer_latitude']));
        $lng = (float) sanitize_text_field(wp_unslash($_POST['customer_longitude']));

        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            $order->update_meta_data('_customer_latitude',  $lat);
            $order->update_meta_data('_customer_longitude', $lng);
        }
    }
}, 10, 2);


function snappbox_admin_notice()
{
    static $notice_displayed = false;

    if ($notice_displayed || ! is_admin()) {
        return;
    }
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        return;
    }

    $screen = get_current_screen();
    if (isset($screen->id) && ($screen->id === 'dashboard' || $screen->id === 'woocommerce_page_wc-settings')) {
        $notice_displayed = true;

        if (class_exists('\Snappbox\SnappBoxShippingMethod') && class_exists('\Snappbox\Api\SnappBoxWalletBalance')) {
            $newNoticeObj = new  \Snappbox\SnappBoxShippingMethod();
            $walletObj = new \Snappbox\Api\SnappBoxWalletBalance();
            $walletObjResult = $walletObj->snappb_check_balance();
            $newNoticeObj->snappb_admin_alert($walletObjResult);
        }
    }
}
add_action('admin_notices', __NAMESPACE__ . '\\snappbox_admin_notice');


add_filter('plugin_action_links_' . plugin_basename(__FILE__), __NAMESPACE__ . '\\snappbox_settings_link');
function snappbox_settings_link($links)
{
    $settings_link = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=wc-settings&tab=shipping&section=snappbox_shipping_method')) . '">' . esc_html__('Settings', 'snappbox') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

add_action('add_meta_boxes', __NAMESPACE__ . '\\snappbox_remove_shipping_address_admin_order_page', 100);
function snappbox_remove_shipping_address_admin_order_page()
{
    remove_action('woocommerce_admin_order_data_after_shipping_address', 'woocommerce_admin_shipping_address');
}
