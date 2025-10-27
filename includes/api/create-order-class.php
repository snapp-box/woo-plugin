<?php
namespace Snappbox\Api;
if ( ! defined( 'ABSPATH' ) ) exit;

class SnappBoxCreateOrder {
    private $api_url;
    private $api_key;

    public const NONCE_ACTION = 'snappbox_admin_actions';
    public const NONCE_FIELD  = 'nonce';

    public function __construct($api_key = \SNAPPBOX_API_TOKEN) {
        global $api_base_url;
        $this->api_url = $api_base_url . '/v1/customer/create_order';
        $this->api_key = $api_key;

        \add_action('wp_ajax_snappbox_create_order',        [$this, 'snappb_handle_create_order']);
        \add_action('wp_ajax_nopriv_snappbox_create_order', [$this, 'snappb_handle_create_order']);
    }

    public function snappb_create_order($order_data, $order) {
        $args = [
            'body'    => \wp_json_encode($order_data),
            'headers' => [
                'Content-Type'  => 'application/json',
                'platform'      => 'web',
                'clientType'    => 'woocommerce-plugin',
                'Authorization' => $this->api_key,
            ],
            'method'  => 'POST',
            'timeout' => 45,
        ];

        $response = \wp_remote_post($this->api_url, $args);

        if (\is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $decoded_response = \json_decode(\wp_remote_retrieve_body($response), true);
        $this->snappb_store_order_detail($order, $decoded_response);

        return [
            'success'  => true,
            'response' => $decoded_response,
        ];
    }

 

    public function snappb_handle_create_order() {
        if (! \check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            \wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        if (! isset($_POST['order_id'])) {
            \wp_send_json_error(['message' => 'Order ID is missing'], 400);
        }

        $voucherCode = \sanitize_text_field(wp_unslash(isset($_POST['voucher_code'])) ?? '');
        $order_id    = (int) \sanitize_text_field(wp_unslash($_POST['order_id']));
        $order       = \wc_get_order($order_id);

        if (! $order) {
            \wp_send_json_error(['message' => 'Invalid order'], 404);
        }

        $order_data = $this->snappb_prepare_order_data($order, $order_id, $voucherCode);
        $response   = $this->snappb_create_order($order_data, $order);

        \wp_send_json($response, 200, \JSON_UNESCAPED_UNICODE);
    }

    

    private function snappb_store_order_detail($order, $response) {
        if (isset($response['data']['details']['id'])) {
            $snappbox_order_id = \sanitize_text_field($response['data']['details']['id']);
            \update_post_meta($order->get_id(), '_snappbox_order_id', $snappbox_order_id);
        }
    }

    private function snappb_prepare_order_data($order, int $order_id, $voucherCode): array {
        return [
            'data' => [
                'itemDetails'             => $this->snappb_get_item_details($order),
                'orderDetails'            => $this->snappb_get_order_details($order, $order_id, $voucherCode),
                'pickUpDetails'           => $this->snappb_get_pickup_details(),
                'dropOffDetails'          => $this->snappb_get_dropoff_details($order),
                'verificationCodeEnabled' => false,
            ],
        ];
    }

    private function snappb_get_item_details($order): array {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'pickedUpSequenceNumber' => 1,
                'dropOffSequenceNumber'  => 2,
                'name'                   => $item->get_name(),
                'quantity'               => $item->get_quantity(),
                'quantityMeasuringUnit'  => 'unit',
                'packageValue'           => $item->get_total(),
                'externalRefType'        => 'INSURANCE',
                'externalRefId'          => 99,
            ];
        }
        return $items;
    }

    private function snappb_get_order_details($order, int $order_id, $voucherCode): array {
        return [
            'city'                             => $order->get_meta('customer_city'),
            'customerWalletType'               => null,
            'deliveryCategory'                 => 'bike-without-box',
            'deliveryFarePaymentType'          => 'cod',
            'isReturn'                         => false,
            'loadAssistance'                   => false,
            'pricingId'                        => '',
            'sequenceNumberDeliveryCollection' => 1,
            'customerEmail'                    => $order->get_billing_email(),
            'customerName'                     => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customerPhonenumber'              => $order->get_billing_phone(),
            'voucherCode'                      => $voucherCode,
            'waitingTime'                      => 0,
        ];
    }

    private function snappb_get_pickup_details(): array {
        $settings_serialized = \get_option('woocommerce_snappbox_shipping_method_settings');
        $settings            = \maybe_unserialize($settings_serialized);
        return [[
            'id'                  => null,
            'contactName'         => \get_option('snappbox_store_name', ''),
            'address'             => \WC()->countries->get_base_address() . ' ' . \WC()->countries->get_base_address_2(),
            'contactPhoneNumber'  => $settings['snappbox_store_phone'] ?? '',
            'plate'               => '',
            'sequenceNumber'      => 1,
            'unit'                => '',
            'editMerchandiseInfo' => null,
            'comment'             => '',
            'latitude'            => $settings['snappbox_latitude'] ?? '',
            'longitude'           => $settings['snappbox_longitude'] ?? '',
            'type'                => 'pickup',
            'paymentType'         => 'prepaid',
            'vendorId'            => 0,
            'services'            => [['itemServiceId' => 2, 'quantity' => 1]],
        ]];
    }

    private function snappb_get_dropoff_details($order): array {
        $latitude  = \get_post_meta($order->get_id(), '_customer_latitude', true);
        $longitude = \get_post_meta($order->get_id(), '_customer_longitude', true);

        return [[
            'id'                               => null,
            'contactName'                      => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'address'                          => $order->get_billing_address_1(),
            'contactPhoneNumber'               => $order->get_billing_phone(),
            'editMerchandiseInfo'              => null,
            'plate'                            => '',
            'sequenceNumber'                   => 2,
            'unit'                             => '',
            'comment'                          => '',
            'latitude'                         => $latitude,
            'longitude'                        => $longitude,
            'type'                             => 'drop',
            'paymentType'                      => 'prepaid',
            'vendorId'                         => 0,
            'services'                         => [['itemServiceId' => 1, 'quantity' => 1]],
            'verificationCodeGenerationStrategy' => 'AUTO',
            'terminalDetails'                  => [
                ['verificationCode' => ''],
            ],
        ]];
    }
}

?>
