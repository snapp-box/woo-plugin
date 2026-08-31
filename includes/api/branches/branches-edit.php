<?php

namespace Snappbox\Api\Branches;

if (!defined('ABSPATH')) {
    exit;
}


class SnappboxBranchesUpdate
{
    private string $base_url;

    public function __construct()
    {
        $this->base_url = \SNAPPBOX_API_BASE_URL . '/v1/customers/addresses/store';
    }

    public function update_address(string $id, array $data)
    {
        $url = "{$this->base_url}/{$id}";
        $body = [
            'id'                 => (string) $id,
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
            'polygon'            => (string) ($data['polygon'] ?? ''),
        ];
        $response = wp_remote_request($url, [
            'method'  => 'PUT',
            'headers' => [
                'Authorization'    =>  \SNAPPBOX_API_TOKEN,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception(esc_html($response->get_error_message()));
        }
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decodedResponse = json_decode($response_body);
        if ($status_code >= 400 && $status_code <= 500) {
            return [
                'success' => false,
                'message' => isset($decodedResponse->message) ? sanitize_text_field($decodedResponse->message) : __('The branch could not be updated.', 'snappbox'),
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
