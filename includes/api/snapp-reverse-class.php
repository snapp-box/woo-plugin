<?php
namespace Snappbox\Api;

if ( ! defined( 'ABSPATH' ) ) exit;

class SnappMapsReverseGeocoder {

    private $base_url  = 'https://api.teh-1.snappmaps.ir/reverse/v1';
    private $auth_token = 'pk.eyJ1IjoibWVpaCIsImEiOiJjamY2aTJxenIxank3MzNsbmY0anhwaG9mIn0.egsUz_uibSftB0sjSWb9qw';
    private $smapp_key = 'aa22e8eef7d348d32f492d8a0c755f4d';

    public function get_address( $lat, $lng, $language ) {

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

        if ( \is_wp_error( $response ) ) {
            return new \WP_Error( 'snappmaps_error', 'Failed to communicate with SnappMaps API.' );
        }

        $body = \wp_remote_retrieve_body( $response );
        return \json_decode( $body, true );
    }
}
