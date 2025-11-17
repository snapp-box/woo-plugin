<?php
namespace Snappbox\Api;

if (!defined('ABSPATH')) exit;

class SnappBoxNearBy {
    private $api_url;
    private $auth_token;

    public function __construct() {
        $this->api_url = 'https://app.snapp-box.com/api/v1/customer/nearby_biker_locations';
        $this->auth_token = \SNAPPBOX_API_TOKEN;
    }

    public function snappb_check_nearby($order_data = []) {
        $defaults = [
            'latitude'  => 35.6892,
            'longitude' => 51.3890,
            'zoom'      => 8,
        ];

        $order_data = wp_parse_args($order_data, $defaults);

        $args = [
            'body'    => json_encode($order_data),
            'headers' => [
                'authorization' => $this->auth_token, 
                'clienttype'    => 'pwa',
                'content-type'  => 'application/json',
                'locale'        => 'fa-IR',
                'platform'      => 'web',
                'Cache-Control' => 'no-cache',
            ],
            'timeout' => 45,
        ];

        $response = \wp_remote_post($this->api_url, $args);

        if (\is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'success'  => true,
            'response' => $decoded,
            'status'   => wp_remote_retrieve_response_code($response),
        ];
    }
}
