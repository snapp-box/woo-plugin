<?php

/*
 * Plugin Name:  SnappBox
 * Plugin URI: http://snapp-box.com/
 * Description: Official SnappBox WooCommerce Delivery Plugin
 * Version: 1.0
 * Author: SnappBox Team
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: http://uikar.com
 * Text Domain: sb-delivery
 * Domain Path: /languages/
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0.0
 * WC tested up to: 9.7.0
 */

define('SNAPPBOX_DIR', plugin_dir_path(__FILE__));
define('SNAPPBOX_URL', plugin_dir_url(__FILE__));
define('SNAPPBOX_API_BASE_URL_STAGING', 'https://customer-stg.snapp-box.com/');
define('SNAPPBOX_API_BASE_URL_PRODUCTION', 'https://customer.snapp-box.com/');
global $api_base_url;
$settings_serialized = get_option('woocommerce_snappbox_shipping_method_settings');

$settings = is_array($settings_serialized) ? $settings_serialized : maybe_unserialize($settings_serialized);

if (is_array($settings)) {
    define('SNAPPBOX_SANDBOX', isset($settings['sandbox']) ? $settings['sandbox'] : false);
    define('ONDELIVERY', isset($settings['ondelivery']) ? $settings['ondelivery'] : false);
} else {
    define('SNAPPBOX_SANDBOX', false);
    define('ONDELIVERY', false);
}

(SNAPPBOX_SANDBOX == 'yes') ?  $api_base_url = SNAPPBOX_API_BASE_URL_STAGING :  $api_base_url = SNAPPBOX_API_BASE_URL_PRODUCTION;
$api_key = $settings['snappbox_api'] ?? '';

define('SNAPPBOX_API_TOKEN', $api_key);

require_once(SNAPPBOX_DIR . 'includes/woo-checkout-map.php');
require_once(SNAPPBOX_DIR . 'includes/cities-class.php');
require_once(SNAPPBOX_DIR . 'includes/order-admin-class.php');
require_once(SNAPPBOX_DIR . 'includes/schedule-modal.php');
require_once(SNAPPBOX_DIR . 'includes/add-meta-orderlist-class.php');




function snappbox_init() {
    $currentUser = wp_get_current_user();
    if( class_exists('SnappBoxOrderAdmin')) {
        new SnappBoxOrderAdmin();
    }
    if( class_exists('SnappBoxCities') ){
        new SnappBoxCities();
    }

    if(in_array('administrator', $currentUser->roles) && SNAPPBOX_SANDBOX == 'yes'){
        if ( class_exists('SnappBoxCheckout') ) {
            new SnappBoxCheckout();
        }

        if(class_exists('SnappBoxWcOrderColumn')){
            new SnappBoxWcOrderColumn();
        }

        if ( class_exists('WC_Payment_Gateway') && ONDELIVERY == 'yes') {
            require_once(SNAPPBOX_DIR . 'includes/payment-method.php');
            SnappBoxOnDeliveryGateway::register();
        }

        if(class_exists('SnappBoxScheduleModal')){
            new SnappBoxScheduleModal();
        }
       
    }
    else{
        if(SNAPPBOX_SANDBOX == 'no'){
            if ( class_exists('SnappBoxCheckout') ) {
                new SnappBoxCheckout();
            }
            if(class_exists('SnappBoxScheduleModal')){
                new SnappBoxScheduleModal();
            }
            if(class_exists('SnappBoxWcOrderColumn')){
                new SnappBoxWcOrderColumn();
            }
            if ( class_exists('WC_Payment_Gateway') && ONDELIVERY == 'yes') {
                require_once(SNAPPBOX_DIR . 'includes/payment-method.php');
                SnappBoxOnDeliveryGateway::register();
            }
        }
    }
    
    
    
    if ( class_exists('WC_Shipping_Method') ) {
        require_once SNAPPBOX_DIR . 'includes/shipping-method-class.php';
        add_action('woocommerce_shipping_init', function() {
            SnappBoxShippingMethod::register();
        });
    }
    if (!function_exists('register_block_type')) {
        return;
    }
}
add_action('plugins_loaded', 'snappbox_init');



function snappbox_activate() {
    delete_transient( 'woocommerce_shipping_zones_cache' );
}

register_activation_hook( __FILE__, 'snappbox_activate' );


add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );


add_action('wp_enqueue_scripts', 'snappbox_enqueue_leaflet_map_js');

function snappbox_enqueue_leaflet_map_js() {
    if (!is_checkout()) return;
    wp_enqueue_script(
        'leaflet',
        trailingslashit( SNAPPBOX_URL ) . 'assets/js/leaflet.js',
        [],
        '1.9.4',
        true
    );
    wp_enqueue_style(
        'snappbox-style',
        trailingslashit( SNAPPBOX_URL ) . 'assets/css/style.css',
        [],
        filemtime( trailingslashit( SNAPPBOX_DIR )  . 'assets/css/style.css' ) 
    );
    wp_enqueue_script('snappbox-map-checkout', plugin_dir_url(__FILE__) . '/assets/js/gutenberg-map.js', ['leaflet'], '1.0', true);
}



add_action('woocommerce_after_order_notes', function () {
    wp_nonce_field('snappbox_geo_meta', 'snappbox_geo_nonce');
});

add_action('woocommerce_checkout_create_order', function($order, $data) {

    if ( empty($_POST['snappbox_geo_nonce'])
      || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['snappbox_geo_nonce'])), 'snappbox_geo_meta') ) {
        return;
    }

    if ( isset($_POST['customer_latitude'], $_POST['customer_longitude']) ) {
        $lat = (float) sanitize_text_field(wp_unslash($_POST['customer_latitude']));
        $lng = (float) sanitize_text_field(wp_unslash($_POST['customer_longitude']));
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            $order->update_meta_data('_customer_latitude',  $lat);
            $order->update_meta_data('_customer_longitude', $lng);
        }
    }
}, 10, 2);




function snappbox_admin_notice() {
    static $notice_displayed = false;

    if ( $notice_displayed || ! is_admin() ) {
        return;
    }
    if ( isset($_SERVER['REQUEST_METHOD']) === 'POST' ) {
        return;
    }
    $screen = get_current_screen();

    if ( isset( $screen->id ) ) {
        if ( $screen->id === 'dashboard' || $screen->id === 'woocommerce_page_wc-settings' ) {
            $notice_displayed = true; 
            $newNoticeObj = new SnappBoxShippingMethod();
            $walletObj = new SnappBoxWalletBalance();
            $walletObjResult = $walletObj->check_balance();
            $newNoticeObj->admin_alert( $walletObjResult );
        }
    }
}
add_action( 'admin_notices', 'snappbox_admin_notice' );


add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'snappbox_settings_link' );

function snappbox_settings_link( $links ) {
  $settings_link = '<a href="' . get_admin_url(null, 'admin.php?page=wc-settings&tab=shipping&section=snappbox_shipping_method') . '">' . __('Settings', 'sb-delivery') . '</a>';
  array_unshift( $links, $settings_link );
  return $links;
}

add_action( 'add_meta_boxes', 'snappbox_remove_shipping_address_admin_order_page', 100 );
function snappbox_remove_shipping_address_admin_order_page() {
    remove_action( 'woocommerce_admin_order_data_after_shipping_address', 'woocommerce_admin_shipping_address' );
}



