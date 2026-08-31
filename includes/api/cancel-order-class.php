<?php

namespace Snappbox\Api;

if (! defined('ABSPATH')) exit;

class SnappBoxCancelOrder
{
    private $api_url;
    private $api_token;

    public function __construct()
    {
        $this->api_url   = \SNAPPBOX_API_BASE_URL . '/v1/orders/';
        $this->api_token = \SNAPPBOX_API_TOKEN;
    }

    public function snappb_cancel_order($order_id)
    {

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

        $status_code = \wp_remote_retrieve_response_code($response);
        $body = \json_decode(\wp_remote_retrieve_body($response), true);

        if ($status_code < 200 || $status_code >= 300 || (!empty($body['apiStatus']) && $body['apiStatus'] === 'FAILURE')) {
            return [
                'success' => false,
                'message' => is_array($body) && isset($body['message']) ? $body['message'] : __('Delete request failed.', 'snappbox'),
            ];
        }

        return [
            'success' => true,
            'message' => is_array($body) && !empty($body['message'])
                ? sanitize_text_field($body['message'])
                : __('Order deleted successfully', 'snappbox'),
        ];
    }
}
