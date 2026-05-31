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
        $this->api_url = 'https://biz-stg.snapp-box.com/api/biz/v1/addresses/store?useCase=BIZ_BOX_STORE_ADDRESS';
        $this->auth_token = \SNAPPBOX_BUSINESS_TOKEN;
    }

    public function snappb_branches_list(): array
    {
        $args = [
            'timeout' => 45,
            'headers' => [
                'Authorization'    => 'Bearer ' . $this->auth_token,
                'Accept-Language' => 'fa-IR',
                'clientType'      => 'pwa',
                'locale'          => 'fa-IR',
                'platform'        => 'web',
                'Content-Type'    => 'application/json',
                'Referer'         => 'https://biz-stg.snapp-box.com/store-addresses',
                'User-Agent'      => 'Mozilla/5.0',
                'appVersion'      => '7.0.2',
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
