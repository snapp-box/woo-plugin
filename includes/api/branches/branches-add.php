<?php

namespace Snappbox\Api\Branches;

use \Snappbox\EnvConfig;

class SnappboxBranchesAdd
{

    private $endpoint;
    private $token;

    public function __construct()
    {
        global $snappb_api_base_url;
        $this->endpoint = $snappb_api_base_url . '/v1/customers/addresses/store';
    }

    public function store_address($data = [])
    {

        $body = [
            'name'               => (string) ($data['name'] ?? ''),
            'contactName'        => (string) ($data['contactName'] ?? ''),
            'contactPhoneNumber' => (string) ($data['contactPhoneNumber'] ?? ''),
            'latitude'           => (string) ($data['latitude'] ?? ''),
            'longitude'          => (string) ($data['longitude'] ?? ''),
            'address'            => (string) ($data['address'] ?? ''),
            'plate'              => (string) ($data['plate'] ?? ''),
            'unit'               => (string) ($data['unit'] ?? ''),
            'comment'            => (string) ($data['comment'] ?? ''),
            'defaultAddress'     => (bool) ($data['defaultAddress'] ?? true),
            'polygon'            => (string) ($data['polygon'] ?? null),
        ];
        $json = \wp_json_encode($body);
        $args = [
            'timeout' => 30,
            'headers' => [
                'Authorization'    =>  SNAPPBOX_API_TOKEN,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => $json,
            'data_format' => 'body',
        ];

        $response = \wp_remote_post($this->endpoint, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decodedResponse = json_decode($response_body);

        if ($status_code >= 400 && $status_code <= 500) {
            return [
                'success' => false,
                'message' => $decodedResponse->message,
            ];
        } else {
            return [
                'success' => ($status_code >= 200 && $status_code < 300),
                'status'  => $status_code,
                'body'    => json_decode($response_body, true),
                'raw'     => $response_body,
            ];
        }
    }
}
