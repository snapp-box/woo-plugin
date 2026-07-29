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
        global $snappb_api_base_url;
        $this->api_url = $snappb_api_base_url . '/v1/customers/addresses/store/default';
        $this->auth_token = \SNAPPBOX_API_TOKEN;
    }

    public function snappbox_reverse_polygon(string $polygon): string
    {
        $polygon = trim($polygon);

        if (!preg_match('/^POLYGON\s*\(\((.+)\)\)$/i', $polygon, $matches)) {
            throw new \InvalidArgumentException('Invalid POLYGON format.');
        }

        $points = explode(',', $matches[1]);

        $coordinates = array_map(function (string $point): array {
            $point = trim($point);
            $parts = preg_split('/\s+/', $point);

            if (count($parts) !== 2) {
                throw new \InvalidArgumentException(
                    'Each polygon point must contain longitude and latitude.'
                );
            }

            return [
                (float) $parts[0], // longitude
                (float) $parts[1], // latitude
            ];
        }, $points);

        return json_encode($coordinates, JSON_UNESCAPED_UNICODE);
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
