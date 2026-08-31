<?php

namespace Snappbox\Api\Branches;

if (!defined('ABSPATH')) {
    exit;
}


class SnappBoxBranchesList
{
    private string $api_url;
    private string $auth_token;

    public function __construct()
    {
        $this->api_url = \SNAPPBOX_API_BASE_URL . '/v1/customers/addresses/store';
        $this->auth_token = \SNAPPBOX_API_TOKEN;
    }

    public function snappb_branches_list(): array
    {
        $args = [
            'timeout' => 45,
            'headers' => [
                'Authorization'    => $this->auth_token,
            ],
        ];

        $response = \wp_remote_get($this->api_url, $args);

        if (\is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        if ($response['response']['code'] == 404) {
            return [
                'success' => false,
                'error'   => $response['response']['message'],
            ];
        }

        $status_code = \wp_remote_retrieve_response_code($response);
        $body        = \wp_remote_retrieve_body($response);
        $decoded     = json_decode($body, true);

        return [
            'success' => $status_code >= 200 && $status_code < 300,
            'status'  => $status_code,
            'response' => $decoded,
            'raw'     => $body,
        ];
    }
}
