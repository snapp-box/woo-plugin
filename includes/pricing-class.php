<?php 
class SnappBoxPriceHandler {

    private $endpoint = SNAPPBOX_API_BASE_URL_STAGING . "/v1/customer/order/pricing";
    private $api_key; 
    public function __construct($api_key = SNAPPBOX_API_TOKEN) {
        $this->api_key = $api_key;
        add_action('wp_ajax_snappbox_get_pricing', [$this, 'handleCreateOrder']);
        add_action('wp_ajax_nopriv_snappbox_get_pricing', [$this, 'handleCreateOrder']);
    }
    public function get_pricing($orderId) {
        $latitude = get_post_meta($orderId, '_customer_latitude', true);
        $longitude = get_post_meta($orderId, '_customer_longitude', true);
        $order = wc_get_order($orderId);
        $payload = [
            "city" => 'tehran',
            "customerWalletType" => null,
            "deliveryCategory" => "bike-without-box",
            "isReturn" => false,
            "loadAssistance" => false,
            "voucherCode" => "",
            "waitingTime" => 0,
            "terminals" => [
                [
                    "address" => WC()->countries->get_base_address() . ' ' . WC()->countries->get_base_address_2(),
                    "sequenceNumber" => 1,
                    "latitude" => get_option('snappbox_latitude', ''),
                    "longitude" => get_option('snappbox_longitude', ''),
                    "type" => "pickup"
                ],
                [
                    "address" => 'خیابان هشت بهشت پلاک 18',
                    "sequenceNumber" => 2,
                    "latitude"=>  $latitude,
                    "longitude"=> $longitude,
                    "type" => "drop"
                ]
            ]
        ];
        $response = wp_remote_post($this->endpoint, [
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
