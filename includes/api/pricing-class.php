<?php
namespace Snappbox\Api;
if ( ! defined( 'ABSPATH' ) ) exit;

class SnappBoxPriceHandler {

    private $apiUrl;
    private $api_key;

    public function __construct($api_key = \SNAPPBOX_API_TOKEN) {
        global $api_base_url;
        $this->api_key = $api_key;
        $this->apiUrl  = rtrim($api_base_url, '/') . '/v1/customer/order/pricing';

        \add_action('wp_ajax_snappbox_get_pricing',  [$this, 'snappb_handle_create_order']);
        \add_action('wp_ajax_nopriv_snappbox_get_pricing', [$this, 'snappb_handle_create_order']);
    }

    public function snappb_get_pricing($orderId, $state_code, $voucherCode) {
        $latitude  = \get_post_meta($orderId, '_customer_latitude', true);
        $longitude = \get_post_meta($orderId, '_customer_longitude', true);
        $city      = \get_post_meta($orderId, 'customer_city', true);
        $order     = \wc_get_order($orderId);

        $settings_serialized = \get_option('woocommerce_snappbox_shipping_method_settings');
        $settings            = \maybe_unserialize($settings_serialized);

        $payload = [
            'city'                        => $city,
            'customerWalletType'          => null,
            'deliveryCategory'            => 'bike-without-box',
            'deliveryFarePaymentType'     => null,
            'isReturn'                    => false,
            'loadAssistance'              => false,
            'voucherCode'                 => $voucherCode,
            'orderLevelServices'          => [],
            'sequenceNumberDeliveryCollection' => 1,
            'waitingTime'                 => 0,
            'cargoComment'                => '',
            'id'                          => null,
            'items'                       => [],
            'terminals'                   => [
                [
                    'canEdit'              => true,
                    'cashOnDelivery'       => 0,
                    'cashOnPickup'         => 0,
                    'collectCash'          => 'no',
                    'editMerchandiseInfo'  => null,
                    'id'                   => null,
                    'isEditing'            => false,
                    'isHub'                => null,
                    'isMerchandisingEnabled'=> false,
                    'merchandise'          => null,
                    'merchandiseInvoiceId' => null,
                    'paymentType'          => 'prepaid',
                    'state'                => 'Confirmed',
                    'vendorId'             => null,
                    'zoneType'             => 'DISABLED',
                    'address'              => \WC()->countries->get_base_address() . ' ' . \WC()->countries->get_base_address_2(),
                    'comment'              => '',
                    'contactName'          => $settings['snappbox_store_name'] ?? '',
                    'contactPhoneNumber'   => $settings['snappbox_store_phone'] ?? '',
                    'plate'                => '',
                    'unit'                 => '',
                    'latitude'             => $settings['snappbox_latitude'] ?? '',
                    'longitude'            => $settings['snappbox_longitude'] ?? '',
                    'location'             => [
                        'latitude'  => $settings['snappbox_latitude'] ?? '',
                        'longitude' => $settings['snappbox_longitude'] ?? '',
                    ],
                    'sequenceNumber'       => 1,
                    'spriteKey'            => 'pickup',
                    'type'                 => 'pickup',
                    'statusText'           => '',
                    'itemDetail'           => null,
                ],
                [
                    'canEdit'              => true,
                    'cashOnDelivery'       => 0,
                    'cashOnPickup'         => 0,
                    'collectCash'          => 'no',
                    'editMerchandiseInfo'  => null,
                    'id'                   => null,
                    'isEditing'            => false,
                    'isHub'                => null,
                    'isMerchandisingEnabled'=> false,
                    'merchandise'          => null,
                    'merchandiseInvoiceId' => null,
                    'paymentType'          => 'prepaid',
                    'state'                => 'Confirmed',
                    'vendorId'             => null,
                    'zoneType'             => 'DISABLED',
                    'address'              => $order ? $order->get_billing_address_1() : '',
                    'comment'              => '',
                    'contactName'          => $order ? ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) : '',
                    'contactPhoneNumber'   => $order ? $order->get_billing_phone() : '',
                    'plate'                => '',
                    'unit'                 => '',
                    'latitude'             => $latitude,
                    'longitude'            => $longitude,
                    'location'             => [
                        'latitude'  => $latitude,
                        'longitude' => $longitude,
                    ],
                    'sequenceNumber'       => 2,
                    'spriteKey'            => 'drop',
                    'type'                 => 'drop',
                    'statusText'           => '',
                    'itemDetail'           => null,
                ],
            ],
        ];

        $response = \wp_remote_post($this->apiUrl, [
            'method'  => 'POST',
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => $this->api_key,
            ],
            'body'    => \wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (\is_wp_error($response)) {
            \wp_send_json_error(['message' => $response->get_error_message()], 400);
        }

        $response_body = \json_decode(\wp_remote_retrieve_body($response), true);

        if (!empty($response_body['finalCustomerFare']) && empty($response_body['voucherMessage'])) {
            \wp_send_json_success(['finalCustomerFare' => $response_body['finalCustomerFare']]);
        } elseif (!empty($response_body['voucherMessage']) && $response_body['voucherMessage'] === 'SUCCESS') {
            \wp_send_json_success([
                'finalCustomerFare' => $response_body['finalCustomerFare'] ?? null,
                'totalFare'         => $response_body['totalFare'] ?? null,
            ]);
        } elseif (!empty($response_body['voucherMessage']) && $response_body['voucherMessage'] !== 'SUCCESS') {
            \wp_send_json_error(['voucherMessage' => $response_body['voucherMessage']], 400);
        } else {
            \wp_send_json_error($response_body, 400);
        }
    }

    public function snappb_handle_create_order() {
        \check_ajax_referer('snappbox_get_pricing', 'nonce');
        $order_id     = isset($_POST['order_id']) ? \absint( \wp_unslash( $_POST['order_id'] ) ) : 0;
        $state_code   = isset($_POST['state_code']) ? \sanitize_text_field( \wp_unslash( $_POST['state_code'] ) ) : '';
        $voucher_code = isset($_POST['voucher_code']) ? \sanitize_text_field( \wp_unslash( $_POST['voucher_code'] ) ) : '';
        return $this->snappb_get_pricing($order_id, $state_code, $voucher_code);
    }
}
