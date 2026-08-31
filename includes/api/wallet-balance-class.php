<?php

namespace Snappbox\Api;

if (! defined('ABSPATH')) exit;
class SnappBoxWalletBalance
{
    private $api_url;


    public function __construct()
    {
        $this->api_url = \SNAPPBOX_API_BASE_URL . '/v1/wallets';
    }

    public function snappb_check_balance($apiKey)
    {
        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'method'  => 'GET',
            'timeout' => 45,
        ];

        if ($apiKey) {
            $args['headers']['Authorization'] = $apiKey;
        }

        $response = \wp_remote_get($this->api_url, $args);

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
