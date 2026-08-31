<?php

namespace Snappbox\Api;

if (! defined('ABSPATH')) exit;

class SnappOrderStatus
{
    private $apiUrl;
    private $headers;

    public function __construct($accessToken = \SNAPPBOX_API_TOKEN)
    {
        $this->apiUrl = \SNAPPBOX_API_BASE_URL . '/v1/orders/';
        $this->headers = [
            'Content-Type' => 'application/json',
        ];

        if (!empty($accessToken)) {
            $this->headers['Authorization'] = $accessToken;
        }
    }

    public function snappb_get_order_status($orderID)
    {
        $url = $this->apiUrl . rawurlencode((string) $orderID);

        $response = \wp_remote_get($url, [
            'headers' => $this->headers,
        ]);

        if (\is_wp_error($response)) {
            return $response;
        }

        $status_code = \wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return new \WP_Error(
                'snappbox_status_request_failed',
                \sprintf('Snappbox API returned HTTP %d.', $status_code)
            );
        }

        $body = \wp_remote_retrieve_body($response);
        $decoded = \json_decode($body, false);
        if (\json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('snappbox_status_invalid_response', 'Invalid response from Snappbox API.');
        }

        return $decoded;
    }

    public function get_order_status($orderID)
    {
        return $this->snappb_get_order_status($orderID);
    }
}
