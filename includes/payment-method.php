<?php
namespace Snappbox;

if (! \class_exists('\WC_Payment_Gateway')) {
    return;
}

class SnappBoxOnDeliveryGateway extends \WC_Payment_Gateway
{
    public static function snappb_register()
    {
        \add_filter('woocommerce_payment_gateways', function ($gateways) {
            $gateways[] = __CLASS__;
            return $gateways;
        });
    }

    public function __construct()
    {
        $this->id                 = 'snappbox_gateway';
        $this->method_title       = \__('SnappBox On-Delivery', 'snappbox');
        $this->method_description = \__('Pay with SnappBox on delivery.', 'snappbox');
        $this->has_fields         = false;
        $this->icon               = '';

        $this->snappb_init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');

        $this->supports = ['products'];

        \add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [ $this, 'process_admin_options' ] 
        );
    }

    public function snappb_init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => \__('Enable/Disable', 'snappbox'),
                'type'    => 'checkbox',
                'label'   => \__('Enable SnappBox On-Delivery Payment', 'snappbox'),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => \__('Title', 'snappbox'),
                'type'        => 'text',
                'description' => \__('Title shown to customers during checkout.', 'snappbox'),
                'default'     => \__('SnappBox On Delivery', 'snappbox'),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => \__('Description', 'snappbox'),
                'type'        => 'textarea',
                'description' => \__('Description shown to customers during checkout.', 'snappbox'),
                'default'     => \__('Pay using SnappBox when your order is delivered.', 'snappbox'),
            ],
        ];
    }

    public function init_form_fields()
    {
        return $this->snappb_init_form_fields();
    }

    public function snappb_process_payment($order_id)
    {
        $order = \wc_get_order($order_id);
        if ($order) {
            $order->update_status('on-hold', \__('Awaiting SnappBox payment', 'snappbox'));
        }

        \wc_reduce_stock_levels($order_id);

        if (\function_exists('WC') && \WC()->cart) {
            \WC()->cart->empty_cart();
        }

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

   
}
