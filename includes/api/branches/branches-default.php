<?php

namespace Snappbox\Api\Branches;

if (!defined('ABSPATH')) {
    exit;
}

use \Snappbox\EnvConfig;

class SnappBoxBranchesDefault
{
    private string $api_url;
    private string $auth_token;

    public function __construct()
    {
        $this->api_url = EnvConfig::get('SNAPPBPX_BUSINESS_BASE_URL') . '/v1/customers/addresses/store/default';
        $this->auth_token = \SNAPPBOX_BUSINESS_TOKEN;
    }

    public function snappb_branches_default(): array
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
