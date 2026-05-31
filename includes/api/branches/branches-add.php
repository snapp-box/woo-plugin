<?php

namespace Snappbox\Api\Branches;

class SnappboxBranchesAdd
{

    private $endpoint = 'https://biz-stg.snapp-box.com/api/biz/v1/addresses/store';
    private $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function store_address($data = [])
    {

        $body = [
            'name'               => $data['name'] ?? '',
            'contactName'        => $data['contactName'] ?? '',
            'contactPhoneNumber' => $data['contactPhoneNumber'] ?? '',
            'latitude'           => (float) ($data['latitude'] ?? 0),
            'longitude'          => (float) ($data['longitude'] ?? 0),
            'address'            => $data['address'] ?? '',
            'plate'              => $data['plate'] ?? '',
            'unit'               => $data['unit'] ?? '',
            'comment'            => $data['comment'] ?? '',
            'defaultAddress'     => (bool) ($data['defaultAddress'] ?? true),
        ];

        $response = wp_remote_post($this->endpoint, [
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => [
                'Accept-Language'  => 'fa-IR',
                'AppVersion'       => '7.0.3',
                'Authorization'    => 'Bearer ' . $this->token,
                'ClientType'       => 'pwa',
                'Content-Type'     => 'application/json',
                'Locale'           => 'fa-IR',
                'Origin'           => 'https://biz-stg.snapp-box.com',
                'Platform'         => 'web',
                'Referer'          => 'https://biz-stg.snapp-box.com/store-addresses',
            ],
            'body' => \json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }
        // print_r($response);
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        return [
            'success' => ($status_code >= 200 && $status_code < 300),
            'status'  => $status_code,
            'body'    => json_decode($response_body, true),
            'raw'     => $response_body,
        ];
    }
}
