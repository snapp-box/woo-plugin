<?php
namespace Snappbox\Api;

class SnappBoxCities {
    private $apiUrl;
    private $headers;

    public function __construct() {
        global $api_base_url;
        $this->headers = [
            'Accept'        => 'application/json',
            'Authorization' => \SNAPPBOX_API_TOKEN,
            'User-Agent'    => 'SnappBoxCities/1.0; ' . \home_url('/'),
        ];

        $this->apiUrl = \trailingslashit($api_base_url) . 'v2/delivery-category/by-city';
    }

    public function snappb_get_delivery_category($latitude, $longitude) {
        try {
            $url = \add_query_arg([
                'latitude'  => $latitude,
                'longitude' => $longitude,
            ], $this->apiUrl);

            $response = \wp_remote_get($url, [
                'headers'     => $this->headers,
                'timeout'     => 15,
                'redirection' => 5,
                'sslverify'   => true,
            ]);

            if (\is_wp_error($response)) {
                throw new \Exception('Request error: ' . $response->get_error_message());
            }

            $code = \wp_remote_retrieve_response_code($response);
            $body = \wp_remote_retrieve_body($response);

            if ($code < 200 || $code >= 300) {
                throw new \Exception('HTTP ' . $code . ' received. Body: ' . $body);
            }

            $decoded = \json_decode($body);
            if (\json_last_error() !== \JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON: ' . \json_last_error_msg() . '. Raw: ' . $body);
            }

            return $decoded;

        } catch (\Exception $e) {
            printf('<div class="notice notice-error"><p>%s</p></div>', \esc_html($e->getMessage()));
            return null;
        }
    }

   
}

?>
