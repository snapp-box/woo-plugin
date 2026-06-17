<?php

namespace Snappbox\Api;

if (! defined('ABSPATH')) exit;


class SnappMapsReverseGeocoder
{

    private $base_url  = \SNAPPBOX_REVERSE_URL;
    private $auth_token = \SNAPPBOX_SMAPP_TOKEN;
    private $smapp_key = \SNAPPBOX_SMAPP_KEY;

    public function get_address($lat, $lng, $language)
    {

        $url = \add_query_arg([
            'lat'      => $lat,
            'lon'      => $lng,
            'language' => $language,
        ], $this->base_url);

        $response = \wp_remote_get($url, [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => $this->auth_token,
                'X-Smapp-Key'   => $this->smapp_key,
                'User-Agent'    => 'SnappBoxWoo/1.0',
            ]
        ]);

        if (\is_wp_error($response)) {
            return new \WP_Error('snappmaps_error', 'Failed to communicate with SnappMaps API.');
        }

        $body = \wp_remote_retrieve_body($response);
        return \json_decode($body, true);
    }
}
