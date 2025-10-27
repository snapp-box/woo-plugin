<?php
namespace Snappbox\Api;
if ( ! defined( 'ABSPATH' ) ) exit; 
class SnappBoxWalletBalance {
    private $api_url;
    private $api_key;

    public function __construct($api_key = \SNAPPBOX_API_TOKEN) {
        global $api_base_url;
        $this->api_key = $api_key;
        $this->api_url = $api_base_url . '/v1/customer/current_balance';
    }

    public function snappb_check_balance($order_data = '') {
        $args = [
            'body'    => \json_encode($order_data, JSON_UNESCAPED_UNICODE),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'method'  => 'POST',
            'timeout' => 45,
        ];

        if ($this->api_key) {
            $args['headers']['Authorization'] = $this->api_key;
        }

        $response = \wp_remote_post($this->api_url, $args);
        if (\is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $decoded_response = \json_decode(\wp_remote_retrieve_body($response), true);

        return [
            'success'  => true,
            'response' => $decoded_response,
        ];
    }

   
}

?>
