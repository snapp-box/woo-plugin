<?php
namespace Snappbox\Api;
if ( ! defined( 'ABSPATH' ) ) exit;

class SnappBoxPriceHandler {

    private $apiUrl;
    private $api_key;

    public function __construct($api_key = \SNAPPBOX_API_TOKEN) {
        global $snappb_api_base_url;
        $this->api_key = $api_key;
        $this->apiUrl  = rtrim($snappb_api_base_url, '/') . '/v1/pricing';

        \add_action('wp_ajax_snappbox_get_pricing',  [$this, 'snappb_handle_create_order']);
        \add_action('wp_ajax_nopriv_snappbox_get_pricing', [$this, 'snappb_handle_create_order']);
    }

    public function snappb_get_pricing($orderId, $cityName, $state_code, $customerLat, $customerLong, $voucherCode) {
        if($orderId){
            $latitude  = \get_post_meta($orderId, '_customer_latitude', true);
            $longitude = \get_post_meta($orderId, '_customer_longitude', true);
            $city      = \get_post_meta($orderId, 'customer_city', true);
            $order     = \wc_get_order($orderId);
        }
        else{
            $latitude  = $customerLat;
            $longitude = $customerLong;
            $city      = $cityName;
        }

        $settings_serialized = \get_option('woocommerce_snappbox_shipping_method_settings');
        $settings            = \maybe_unserialize($settings_serialized);

        $payload = [
            'city'                        => $city,
            'deliveryCategory'            => 'bike-without-box',
            'hasReturn'                    => false,
            'voucherCode'                 => $voucherCode,
            'paymentType'          => 'prepaid',
            'terminals'                   => [
                [
                    
                    'address'              => \WC()->countries->get_base_address() . ' ' . \WC()->countries->get_base_address_2(),
                    'comment'              => '',
                    'contactName'          => $settings['snappbox_store_name'] ?? '',
                    'latitude'             => (string) $settings['snappbox_latitude'] ?? '',
                    'longitude'            => (string) $settings['snappbox_longitude'] ?? '',
                    'phoneNumber'   => $settings['snappbox_store_phone'] ?? '',
                    'reference'       => "1",
                    'type'                 => 'pickup',
                ],
                [
                    'address'              => $order ? $order->get_billing_address_1() : '',
                    'comment'              => '',
                    'contactName'          => $order ? ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) : '',
                    'latitude'             => $latitude,
                    'longitude'            => $longitude,
                    'phoneNumber'   => $order ? $this->snappb_phone_number($order->get_billing_phone()) : '',
                    'reference'       => "1",
                    'type'                 => 'pickup',
                ],
            ],
            "waitingTime"=> 10
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
        
        if (!empty($response_body['finalCustomerFare'])) {
            return [
                'success' => true,
                'data' => $response_body
            ];
        } else {
            return [
                'success' => false,
                'data' => $response_body
            ];
        }
        
    }

    private function snappb_phone_number($phone) {
        $phone = trim($phone);
        $phone = str_replace(' ', '', $phone);
    
        if (strpos($phone, '+98') === 0) {
            $phone = '0' . substr($phone, 3);
        }
        elseif (strpos($phone, '98') === 0) {
            $phone = '0' . substr($phone, 2);
        }
        else{
            $phone = $phone;
        }
        return $phone;
    }

    public function snappb_handle_create_order() {
        \check_ajax_referer('snappbox_get_pricing', 'nonce');
        $order_id     = isset($_POST['order_id']) ? \absint( \wp_unslash( $_POST['order_id'] ) ) : 0;
        $state_code   = isset($_POST['state_code']) ? \sanitize_text_field( \wp_unslash( $_POST['state_code'] ) ) : '';
        $voucher_code = isset($_POST['voucher_code']) ? \sanitize_text_field( \wp_unslash( $_POST['voucher_code'] ) ) : '';
        $customerLat = isset($_POST['_customer_latitude']) ? \sanitize_text_field( \wp_unslash( $_POST['_customer_latitude'] ) ) : '';
        $customerLong = isset($_POST['_customer_longitude']) ? \sanitize_text_field( \wp_unslash( $_POST['_customer_longitude'] ) ) : '';
        $cityName = isset($_POST['customer_city']) ? \sanitize_text_field( \wp_unslash( $_POST['customer_city'] ) ) : '';
        return $this->snappb_get_pricing($order_id, $cityName, $state_code, $customerLat, $customerLong, $voucher_code);
    }
}
