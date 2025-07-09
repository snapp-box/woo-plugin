<?php 
class SnappBoxPriceHandler {

    private $apiUrl;
    private $api_key; 
    public function __construct($api_key = SNAPPBOX_API_TOKEN) {
        global $api_base_url;
        $this->api_key = $api_key;
        $this->apiUrl = $api_base_url . "/v1/customer/order/pricing";
        add_action('wp_ajax_snappbox_get_pricing', [$this, 'handleCreateOrder']);
        add_action('wp_ajax_nopriv_snappbox_get_pricing', [$this, 'handleCreateOrder']);
    }
    public function get_pricing($orderId) {
        $latitude = get_post_meta($orderId, '_customer_latitude', true);
        $longitude = get_post_meta($orderId, '_customer_longitude', true);
        
        $order = wc_get_order($orderId);
        $settings_serialized = get_option('woocommerce_snappbox_shipping_method_settings');
        $settings = maybe_unserialize($settings_serialized);
        $state = $order->get_billing_state();
        $payload = [
            "city" => $state,
            "customerWalletType" => null,
            "deliveryCategory" => "bike", // updated from "bike-without-box"
            "deliveryFarePaymentType" => null,
            "isReturn" => false,
            "loadAssistance" => false,
            "orderLevelServices" => [],
            "sequenceNumberDeliveryCollection" => 1,
            "waitingTime" => 0,
            "cargoComment" => "",
            "id" => null,
            "items" => [],
            "terminals" => [
                [
                    "canEdit" => true,
                    "cashOnDelivery" => 0,
                    "cashOnPickup" => 0,
                    "collectCash" => "no",
                    "editMerchandiseInfo" => null,
                    "id" => null,
                    "isEditing" => false,
                    "isHub" => null,
                    "isMerchandisingEnabled" => false,
                    "merchandise" => null,
                    "merchandiseInvoiceId" => null,
                    "paymentType" => "prepaid",
                    "state" => "Confirmed",
                    "vendorId" => null,
                    "zoneType" => "DISABLED",
                    "address" => WC()->countries->get_base_address() . ' ' . WC()->countries->get_base_address_2(),
                    "comment" => "",
                    "contactName" => $settings['snappbox_store_name'],
                    "contactPhoneNumber" => $settings['snappbox_store_phone'],
                    "plate" => "",
                    "unit" => "",
                    "latitude" => $settings['snappbox_latitude'] ?? '',
                    "longitude" => $settings['snappbox_longitude'] ?? '',
                    "location" => [
                        "latitude" => $settings['snappbox_latitude'] ?? '',
                        "longitude" => $settings['snappbox_longitude'] ?? '',
                    ],
                    "sequenceNumber" => 1,
                    "spriteKey" => "pickup",
                    "type" => "pickup",
                    "statusText" => "",
                    "itemDetail" => null
                ],
                [
                    "canEdit" => true,
                    "cashOnDelivery" => 0,
                    "cashOnPickup" => 0,
                    "collectCash" => "no",
                    "editMerchandiseInfo" => null,
                    "id" => null,
                    "isEditing" => false,
                    "isHub" => null,
                    "isMerchandisingEnabled" => false,
                    "merchandise" => null,
                    "merchandiseInvoiceId" => null,
                    "paymentType" => "prepaid",
                    "state" => "Confirmed",
                    "vendorId" => null,
                    "zoneType" => "DISABLED",
                    "address" => $order->get_billing_address_1(),
                    "comment" => "",
                    "contactName" => $order->get_billing_first_name() .' ' .$order->get_billing_last_name(),
                    "contactPhoneNumber" => $order->get_billing_phone(),
                    "plate" => "",
                    "unit" => "",
                    "latitude" => $latitude,
                    "longitude" => $longitude,
                    "location" => [
                        "latitude" => $latitude,
                        "longitude" => $longitude,
                    ],
                    "sequenceNumber" => 2,
                    "spriteKey" => "drop",
                    "type" => "drop",
                    "statusText" => "",
                    "itemDetail" => null
                ]
            ]
        ];
        
        $response = wp_remote_post($this->apiUrl, [
            'method'    => 'POST',
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' =>  $this->api_key, 
            ],
            'body'    => json_encode($payload),
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($response_body['finalCustomerFare'])) {
            wp_send_json_success(['finalCustomerFare' => $response_body['finalCustomerFare']]);
        } else {
            wp_send_json_error('Invalid response from SnappBox API');
        }
    }
}
