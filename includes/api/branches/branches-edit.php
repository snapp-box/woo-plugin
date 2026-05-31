<?php

namespace Snappbox\Api\Branches;

if (!defined('ABSPATH')) {
    exit;
}

class SnappboxBranchesUpdate
{
    private string $token;
    private string $base_url = 'https://biz-stg.snapp-box.com/api/biz/v1/addresses/store';

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function update_address(string $id, array $data)
    {
        $url = "{$this->base_url}/{$id}";

        $response = wp_remote_request($url, [
            'method'  => 'PUT',
            'headers' => [
                'accept' => '*/*',
                'content-type' => 'application/json',
                'authorization' => 'Bearer ' . $this->token,
                'appversion' => '7.0.4',
                'clienttype' => 'pwa',
                'locale' => 'fa-IR',
                'platform' => 'web',
            ],
            'body' => wp_json_encode(array_merge($data, [
                'id' => $id
            ])),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body;
    }
}
