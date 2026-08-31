<?php

namespace Snappbox\Api;

if (! defined('ABSPATH')) exit;

use \Snappbox\Api\Branches\SnappBoxBranchesDefault;

class SnappBoxPriceHandler
{

    private $apiUrl;
    private $api_key;

    public function __construct($api_key = \SNAPPBOX_API_TOKEN)
    {
        $this->api_key = $api_key;
        $this->apiUrl  = rtrim(\SNAPPBOX_API_BASE_URL, '/') . '/v1/pricing';

        \add_action('wp_ajax_snappbox_get_pricing',  [$this, 'snappb_handle_create_order']);
        \add_action('wp_ajax_nopriv_snappbox_get_pricing', [$this, 'snappb_handle_create_order']);
    }

    public function snappb_get_pricing($orderId, $cityName, $state_code, $customerLat, $customerLong, $voucherCode, $defaultBranch = "", array $branch_data = [])
    {
        $branchLat = $branch_data['latitude'] ?? '';
        $branchLong = $branch_data['longitude'] ?? '';
        $branchPhoneNumber = $branch_data['phoneNumber'] ?? '';
        $branchContactName = $branch_data['contactName'] ?? '';
        $branchAddress = $branch_data['address'] ?? '';
        if ($defaultBranch == true) {
            $defaultBranchObj = new SnappBoxBranchesDefault();
            $defaultBranchItem = $defaultBranchObj->snappb_branches_default()['response'] ?? "";

            if (!\is_array($defaultBranchItem)
                || empty($defaultBranchItem)
                || (!empty($defaultBranchItem['statusCode']) && (int) $defaultBranchItem['statusCode'] >= 400)) {
                $settings_serialized = \get_option('woocommerce_snappbox_shipping_method_settings');
                $settings            = \maybe_unserialize($settings_serialized);
                $branchLat = (string) $settings['snappbox_latitude'];
                $branchLong = (string) $settings['snappbox_longitude'];
                $branchAddress = \WC()->countries->get_base_address() . ' ' . \WC()->countries->get_base_address_2();
                $branchPhoneNumber = $settings['snappbox_store_phone'];
                $city      = $cityName;
            } else {
                $branchLat = $defaultBranchItem['latitude'] ?? $customerLat;
                $branchLong = $defaultBranchItem['longitude'] ?? $customerLong;
                $branchAddress = $defaultBranchItem['address'] ?? "";
                $branchPhoneNumber = $defaultBranchItem['contactPhoneNumber'] ?? "";
                $city      = $cityName;
            }
        }
        if ($orderId) {
            $latitude  = \get_post_meta($orderId, '_customer_latitude', true);
            $longitude = \get_post_meta($orderId, '_customer_longitude', true);
            $city      = \get_post_meta($orderId, 'customer_city', true);
            $order     = \wc_get_order($orderId);
        } else {
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

                    'address'              => ($branchAddress) ? $branchAddress : \WC()->countries->get_base_address() . ' ' . \WC()->countries->get_base_address_2(),
                    'comment'              => '',
                    'contactName'          => ($branchContactName) ? $branchContactName : $settings['snappbox_store_name'] ?? '',
                    'latitude'             => ($branchLat) ? (string) $branchLat : (string) $settings['snappbox_latitude'] ?? '',
                    'longitude'            => ($branchLong) ? (string) $branchLong : (string) $settings['snappbox_longitude'] ?? '',
                    'phoneNumber'   => ($branchPhoneNumber) ? $branchPhoneNumber : $settings['snappbox_store_phone'] ?? '',
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
            "waitingTime" => 10
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
            return [
                'success' => false,
                'data' => [
                    'message' => $response->get_error_message(),
                ],
            ];
        }

        $response_body = \json_decode(\wp_remote_retrieve_body($response), true);

        if (isset($response_body['finalCustomerFare']) && \is_numeric($response_body['finalCustomerFare'])) {
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

    private function snappb_phone_number($phone)
    {
        $phone = trim($phone);
        $phone = str_replace(' ', '', $phone);

        if (strpos($phone, '+98') === 0) {
            $phone = '0' . substr($phone, 3);
        } elseif (strpos($phone, '98') === 0) {
            $phone = '0' . substr($phone, 2);
        } else {
            $phone = $phone;
        }
        return $phone;
    }

    public function snappb_handle_create_order()
    {
        \check_ajax_referer('snappbox_get_pricing', 'nonce');
        $order_id     = isset($_POST['order_id']) ? \absint(\wp_unslash($_POST['order_id'])) : 0;
        $state_code   = isset($_POST['state_code']) ? \sanitize_text_field(\wp_unslash($_POST['state_code'])) : '';
        $voucher_code = isset($_POST['voucher_code']) ? \sanitize_text_field(\wp_unslash($_POST['voucher_code'])) : '';
        $customerLat = isset($_POST['_customer_latitude']) ? \sanitize_text_field(\wp_unslash($_POST['_customer_latitude'])) : '';
        $customerLong = isset($_POST['_customer_longitude']) ? \sanitize_text_field(\wp_unslash($_POST['_customer_longitude'])) : '';
        $cityName = isset($_POST['customer_city']) ? \sanitize_text_field(\wp_unslash($_POST['customer_city'])) : '';
        $branch_data = [
            'latitude'    => isset($_POST['branchLatitude']) ? \sanitize_text_field(\wp_unslash($_POST['branchLatitude'])) : '',
            'longitude'   => isset($_POST['branchLongitude']) ? \sanitize_text_field(\wp_unslash($_POST['branchLongitude'])) : '',
            'phoneNumber' => isset($_POST['phoneNumber']) ? \sanitize_text_field(\wp_unslash($_POST['phoneNumber'])) : '',
            'contactName' => isset($_POST['branchContactName']) ? \sanitize_text_field(\wp_unslash($_POST['branchContactName'])) : '',
            'address'     => isset($_POST['branchAddress']) ? \sanitize_text_field(\wp_unslash($_POST['branchAddress'])) : '',
        ];
        return $this->snappb_get_pricing($order_id, $cityName, $state_code, $customerLat, $customerLong, $voucher_code, '', $branch_data);
    }
}
