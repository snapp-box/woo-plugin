<?php

namespace Snappbox\Api\Branches;

if (!defined('ABSPATH')) {
    exit;
}

class SnappboxBranchesDelete
{
    private string $base_url;

    public function __construct()
    {
        $this->base_url = \SNAPPBOX_API_BASE_URL . '/v1/customers/addresses/store';
    }

    public function delete_address(string $id, array $data)
    {
        $url = "{$this->base_url}/{$id}";
        $response = wp_remote_request($url, [
            'method'  => 'DELETE',
            'headers' => [
                'Authorization'    =>  \SNAPPBOX_API_TOKEN,
                'Content-Type'  => 'application/json',
            ],
            'body' => '',
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
                'message' => isset($decodedResponse->message) ? sanitize_text_field($decodedResponse->message) : __('The branch could not be deleted.', 'snappbox'),
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
