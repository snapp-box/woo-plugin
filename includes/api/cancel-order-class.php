<?php
namespace Snappbox\Api;
if ( ! defined( 'ABSPATH' ) ) exit; 

class SnappBoxCancelOrder {
    private $api_url;
    private $api_token;

    public function __construct() {
        global $snappb_api_base_url;
        $this->api_url   = $snappb_api_base_url . '/v1/orders/';
        $this->api_token = \SNAPPBOX_API_TOKEN;
    }

    public function snappb_cancel_order($order_id) {

        $url = $this->api_url . $order_id;

        $response = \wp_remote_request($url, [
            'method'  => 'DELETE',
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => $this->api_token,
            ],
        ]);

        if (\is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'API request failed: ' . $response->get_error_message(),
            ];
        }

        $body = \json_decode(\wp_remote_retrieve_body($response), true);

        if (!empty($body) && isset($body['success']) && $body['success'] === true) {
            return [
                'success' => true,
                'message' => 'Order deleted successfully.',
            ];
        }

        return [
            'success' => false,
            'message' => $body['message'] ?? 'Delete request failed.',
        ];
    }
}
?>
