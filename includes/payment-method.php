<?php 

if (!class_exists('WC_Payment_Gateway')) {
    return; 
}

class SnappBoxOnDeliveryGateway extends WC_Payment_Gateway {

    public static function register() {
        add_filter('woocommerce_payment_gateways', function ($gateways) {
            $gateways[] = __CLASS__;
            return $gateways;
        });
    }

    public function __construct() {
        $this->id = 'snappbox_gateway';
        $this->method_title = __('SnappBox On-Delivery', 'sb-delivery');
        $this->method_description = __('Pay with SnappBox on delivery.', 'sb-delivery');
        $this->has_fields = false;
        $this->icon = '';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->supports = ['products'];

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'sb-delivery'),
                'type'    => 'checkbox',
                'label'   => __('Enable SnappBox On-Delivery Payment', 'sb-delivery'),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __('Title', 'sb-delivery'),
                'type'        => 'text',
                'description' => __('Title shown to customers during checkout.', 'sb-delivery'),
                'default'     => __('SnappBox On Delivery', 'sb-delivery'),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __('Description', 'sb-delivery'),
                'type'        => 'textarea',
                'description' => __('Description shown to customers during checkout.', 'sb-delivery'),
                'default'     => __('Pay using SnappBox when your order is delivered.', 'sb-delivery'),
            ],
        ];
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $order->update_status('on-hold', __('Awaiting SnappBox payment', 'sb-delivery'));
        wc_reduce_stock_levels($order_id);
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }
}