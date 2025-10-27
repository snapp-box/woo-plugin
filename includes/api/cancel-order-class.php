<?php
namespace Snappbox\Api;
if ( ! defined( 'ABSPATH' ) ) exit; 

class SnappBoxCancelOrder {
    private $api_url;
    private $api_token;

    public function __construct() {
        global $api_base_url;
        $this->api_url   = $api_base_url . '/v1/customer/cancel_order';
        $this->api_token = \SNAPPBOX_API_TOKEN;
    }

    public function snappb_cancel_order($order_id) {
        $response = \wp_remote_post($this->api_url, [
            'method'  => 'POST',
            'body'    => \json_encode(['orderId' => $order_id]),
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
                'message' => 'Order cancelled successfully.',
            ];
        }

        return [
            'success' => false,
            'message' => $body['message'] ?? 'Cancellation failed.',
        ];
    }

}
?>
