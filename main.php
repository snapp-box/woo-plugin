<?php

/*
 * Plugin Name:  SnappBox WooCommerce
 * Plugin URI: http://snapp-box.com/
 * Description: SnappBox WooCommerce Delivery Plugin
 * Version: 1.0
 * Author: Saman Tohidian
 * Author URI: http://uikar.com
 * Text Domain: sb-delivery
 * Domain Path: /languages/
 *
 */

define('SNAPPBOX_DIR', plugin_dir_path(__FILE__));
define('SNAPPBOX_URL', plugin_dir_url(__FILE__));
define('SNAPPBOX_API_BASE_URL_STAGING', 'https://customer-stg.snapp-box.com/');
define('SNAPPBOX_API_BASE_URL_PRODUCTION', 'https://customer.snapp-box.com/');
define('SNAPPBOX_API_TOKEN', get_option('snappbox_api', ''));

require_once(SNAPPBOX_DIR . 'includes/woo-checkout-map.php');
require_once(SNAPPBOX_DIR . 'includes/cities-class.php');
require_once(SNAPPBOX_DIR . 'includes/wooCommerce-filter-class.php');
require_once(SNAPPBOX_DIR . 'includes/order-admin-class.php');
require_once(SNAPPBOX_DIR . 'includes/checkout-pricing-class.php');


function snappbox_init() {
    
    if ( class_exists('SnappBoxCheckout') ) {
        new SnappBoxCheckout();
    }
   
    if( class_exists('SnappBoxCities') ){
        new SnappBoxCities();
    }
    if(class_exists('SnappBoxWooCommerceFilter')){
        new SnappBoxWooCommerceFilter();
    }
    if(class_exists('SnappBoxOrderAdmin')){
        new SnappBoxOrderAdmin();
    }
    if(class_exists('SnappBoxPricing')){
        new SnappBoxPricing();
    }
    
    load_plugin_textdomain('sb-delivery', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    if ( class_exists('WC_Shipping_Method') ) {
        require_once SNAPPBOX_DIR . 'includes/shipping-method-class.php';
        add_action('woocommerce_shipping_init', function() {
            SnappBoxShippingMethod::register();
        });
    }
}
add_action('plugins_loaded', 'snappbox_init');

function snappbox_activate() {
    delete_transient( 'woocommerce_shipping_zones_cache' );
}

register_activation_hook( __FILE__, 'snappbox_activate' );

