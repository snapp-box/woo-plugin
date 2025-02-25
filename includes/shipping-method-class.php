<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
    return;
}

class SnappBoxShippingMethod extends WC_Shipping_Method {

    public function __construct() {
        $this->id                 = 'snappbox_shipping_method'; 
        $this->method_title       = __( 'SnappBox Shipping Method', 'sb-delivery' );
        $this->method_description = __( 'A SnappBox shipping method with dynamic pricing.', 'sb-delivery' );
        $this->enabled            = "yes";
        $this->title              = "Snappbox Shipping";
        $this->init();
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();
        $this->enabled = $this->get_option( 'enabled' );
        $this->title   = $this->get_option( 'title' );
        add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'       => __( 'Enable', 'sb-delivery' ),
                'type'        => 'checkbox',
                'description' => __( 'Enable this shipping method', 'sb-delivery' ),
                'default'     => 'yes',
            ],
            'title' => [
                'title'       => __( 'Title', 'sb-delivery' ),
                'type'        => 'text',
                'description' => __( 'Title to display during checkout', 'sb-delivery' ),
                'default'     => __( 'SnappBox Shipping', 'sb-delivery' ),
            ],
            'fixed_price' => [
                'title'       => __( 'Fixed Price', 'sb-delivery' ),
                'type'        => 'number',
                'description' => __( 'Leave it empty for canceling fixed price', 'sb-delivery' ),
                'default'     => __( 'SnappBox Shipping', 'sb-delivery' ),
            ],
            'free_delivery' => [
                'title'       => __( 'Free Delivery', 'sb-delivery' ),
                'type'        => 'number',
                'description' => __( 'Please set the minimum basket price for free delivery', 'sb-delivery' ),
                'default'     => __( 'SnappBox Shipping', 'sb-delivery' ),
            ],
            'base_cost' => [
                'title'       => __( 'Base Shipping Cost', 'sb-delivery' ),
                'type'        => 'number',
                'description' => __( 'Base shipping cost for this method', 'sb-delivery' ),
                'default'     => '5',
                'desc_tip'    => true,
            ],
            'cost_per_kg' => [
                'title'       => __( 'Cost per KG', 'sb-delivery' ),
                'type'        => 'number',
                'description' => __( 'Shipping cost per kilogram of weight', 'sb-delivery' ),
                'default'     => '2',
                'desc_tip'    => true,
            ],
        ];
    }

    public function calculate_shipping( $package = [] ) {
        global $woocommerce;
        $base_cost = $this->get_option( 'base_cost' );
        $cost_per_kg = $this->get_option( 'cost_per_kg' );
        $fixed_price = $this->get_option( 'fixed_price' );
        $free_delivery = $this->get_option('free_delivery');
        $totalCard = floatval( preg_replace( '#[^\d.]#', '', $woocommerce->cart->get_cart_total() ) );
        $weight = 0;
        if(empty($fixed_price)){

            foreach ( $package['contents'] as $item ) {
                $product = $item['data'];
                // wp_die($product);
                // $weight += $product->get_weight() * $item['quantity']; 
            }
    
            $cost = $base_cost + ( $weight * $cost_per_kg );
        }
        else if(!empty($free_delivery) && $free_delivery < $totalCard){
            $cost = 0;
        }
        else{
            $cost = $fixed_price;
        }
        
        $rate = [
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $cost,
            'calc_tax' => 'per_item', 
        ];
        $this->add_rate( $rate );
    }

    public static function register() {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'SnappBoxShippingMethod registering...' );
        }
        add_filter( 'woocommerce_shipping_methods', [ __CLASS__, 'add_method' ] );
    }

    public static function add_method( $methods ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Adding SnappBoxShippingMethod to WooCommerce shipping methods' );
        }
        $methods['snappbox_shipping_method'] = __CLASS__;
        return $methods;
    }
}

function snappbox_shipping_method_init() {
    if ( class_exists( 'SnappBoxShippingMethod' ) ) {
        SnappBoxShippingMethod::register();
    }
}

add_action( 'woocommerce_shipping_init', 'snappbox_shipping_method_init' );


