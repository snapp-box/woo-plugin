<?php
/*
 * Plugin Name:  snappbox
 * Plugin URI: http://snapp-box.com/
 * Description: Official SnappBox WooCommerce Delivery Plugin
 * Version: 1.1.3
 * Author: SnappBox Team
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://snapp-box.com/wordpress-plugin
 * Text Domain: snappbox
 * Domain Path: /languages/
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0.0
 * WC tested up to: 10.7.0
 */

namespace Snappbox;

use WpOrg\Requests\Response;

if (! defined('ABSPATH')) {
    exit;
}

define('SNAPPBOX_DIR', plugin_dir_path(__FILE__));
define('SNAPPBOX_URL', plugin_dir_url(__FILE__));
require_once SNAPPBOX_DIR . 'includes/env-config.php';
\Snappbox\EnvConfig::load(SNAPPBOX_DIR);

define('SNAPPBOX_API_BASE_URL_STAGING', \Snappbox\EnvConfig::get('SNAPPBOX_API_BASE_URL_STAGING'));
define('SNAPPBOX_API_BASE_URL_PRODUCTION', \Snappbox\EnvConfig::get('SNAPPBOX_API_BASE_URL_PRODUCTION'));
define('SNAPPBOX_SMAPP_TOKEN', \Snappbox\EnvConfig::get('SNAPPBOX_SMAPP_AUTHORIZATION'));
define('SNAPPBOX_SMAPP_KEY', \Snappbox\EnvConfig::get('SNAPPBOX_SMAPP_KEY'));

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
require_once SNAPPBOX_DIR . 'includes/order-admin-class.php';
require_once SNAPPBOX_DIR . 'includes/schedule-modal.php';
require_once SNAPPBOX_DIR . 'includes/add-meta-orderlist-class.php';
require_once SNAPPBOX_DIR . 'includes/quick-setup-wizard.php';
require_once SNAPPBOX_DIR . 'includes/api/near-by-class.php';
require_once SNAPPBOX_DIR . 'includes/api/snapp-reverse-class.php';
require_once SNAPPBOX_DIR . 'includes/plugin-activation.php';
require_once SNAPPBOX_DIR . 'includes/branches/branch-list-class.php';
require_once SNAPPBOX_DIR . 'includes/api/config-class.php';


$configSettings = new \Snappbox\Api\SnappBoxConfig();
$config = $configSettings->snappb_get_config();
($config && !empty($config->tileAddress)) ? $mapTile = $config->tileAddress : $mapTile = \Snappbox\EnvConfig::get('SNAPPBOX_MAP_STYLE_URL');
($config && !empty($config->reversApiUrl)) ? $reverseUrl = $config->reversApiUrl : $reverseUrl = \Snappbox\EnvConfig::get('SNAPPBOX_MAP_REVERSE_URL');
define('SNAPPBOX_MAP_URL', $mapTile);
define('SNAPPBOX_REVERSE_URL', $reverseUrl);
define('SNAPPBOX_NOMINATIM_URL', \Snappbox\EnvConfig::get('SNAPPBOX_MAP_NOMINATIM_URL'));
define('SNAPPBOX_BUSINESS_TOKEN', 'eyJhbGciOiJIUzUxMiJ9.eyJjaWQiOjE4MzI4OTA5LCJjcmlkIjoiMjA0NTUxMDgyMSIsImUiOiIiLCJ3ZSI6ZmFsc2UsInN1YiI6IjA5MTI1Nzg0NTA3IiwiaXNfYjJiIjpmYWxzZSwiYXV0aCI6IlJPTEVfQ1VTVE9NRVIiLCJ0eXBlIjoiY3VzdG9tZXIifQ.zwAFAIqN-fGxmVrtDdRaVywUco6s8sA5Qub76VHwmnOfFI42AFC51jQN40f-z9UWcksRbGcAYyDUhSs6KpPlpA');
($config && !empty($config->reversApiUrl)) ? $reverseUrl = $config->reversApiUrl : $reverseUrl = "https://app-stg.snapp-box.com/api/v1/customer/nearby_biker_locations";


($config && !empty($config->nearByApiStage)) ? $nearByStgURL = $config->nearByApiStage : $nearByStgURL = \Snappbox\EnvConfig::get('SNAPPBOX_NEARBY_API_STAGING');
($config && !empty($config->nearByApiProd)) ? $nearByProdURL = $config->nearByApiProd : $nearByProdURL = \Snappbox\EnvConfig::get('SNAPPBOX_NEARBY_API_PRODUCTION');
$snappb_nearby_url = (isset($settings['sandbox']))
    ? $nearByStgURL
    : $nearByProdURL;

define('SNAPPBOX_NEARBY_URL', $snappb_nearby_url);

register_activation_hook(SNAPPBOX_DIR, [SnappboxActivator::class, 'snappbox_activate']);
register_deactivation_hook(SNAPPBOX_DIR, [SnappboxActivator::class, 'snappbox_deactivate']);

add_action('admin_init', [SnappboxActivator::class, 'snappbox_maybe_redirect']);
add_action('admin_head', [SnappboxActivator::class, 'snappbox_goal_script']);


function snappbox_init()
{
    $currentUser = wp_get_current_user();

    if (class_exists('\Snappbox\SnappBoxOrderAdmin')) {
        new \Snappbox\SnappBoxOrderAdmin();
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
    if (class_exists("\Snappbox\Branches\BranchListPage")) {
        new \Snappbox\Branches\BranchListPage();
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
    } else {
        snappbox_store_city($lat, $lng);
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


\register_deactivation_hook(SNAPPBOX_DIR, __NAMESPACE__ . '\\snappbox_deactivation_hook');

function snappbox_deactivation_hook()
{
    update_option('snappbox_yandex_deactivation_goal', 1);
}
add_action('wp_footer', __NAMESPACE__ . '\\snappbox_yandex_deactivation_goal_script', 99);

function snappbox_yandex_deactivation_goal_script()
{
    if (! get_option('snappbox_yandex_deactivation_goal')) {
        return;
    }
    delete_option('snappbox_yandex_deactivation_goal');
?>
    <script type="text/javascript">
        if (typeof ym === 'function') {
            ym(105087875, 'reachGoal', 'deactivation');
        }
    </script>
<?php
}

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
    wp_localize_script('snappbox-map-checkout', 'SNAPPBOX_LEAFLET', [
        'rasterTileUrl' => \Snappbox\EnvConfig::get('SNAPPBOX_MAP_RASTER_TILE_URL'),
    ]);
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
            $walletObjResult = $walletObj->snappb_check_balance(\SNAPPBOX_API_TOKEN);
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




add_action('admin_head',  __NAMESPACE__ . '\\snappbox_yandex_script');
function snappbox_yandex_script()
{
?>
    <!-- Yandex.Metrika counter -->
    <script defer type="text/javascript">
        (function(m, e, t, r, i, k, a) {
            m[i] = m[i] || function() {
                (m[i].a = m[i].a || []).push(arguments)
            };
            m[i].l = 1 * new Date();
            for (var j = 0; j < document.scripts.length; j++) {
                if (document.scripts[j].src === r) {
                    return;
                }
            }
            k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
        })(window, document, 'script', <?php echo wp_json_encode(\Snappbox\EnvConfig::yandex_script_url()); ?>, 'ym');

        ym(<?php echo (int) \Snappbox\EnvConfig::get('SNAPPBOX_YANDEX_METRIKA_ID'); ?>, 'init', {
            ssr: true,
            webvisor: true,
            clickmap: true,
            ecommerce: "dataLayer",
            accurateTrackBounce: true,
            trackLinks: true
        });
    </script>
    <noscript>
        <div><img src="<?php echo esc_url(\Snappbox\EnvConfig::yandex_watch_url()); ?>" style="position:absolute; left:-9999px;" alt="" /></div>
    </noscript>
    <!-- /Yandex.Metrika counter -->
<?php
}
