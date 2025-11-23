<?php
namespace Snappbox\Api;

class SnappBoxCities {
    private $apiUrl;
    public function __construct() {
        $this->apiUrl = 'https://assets.snapp-box.com/static/plugin/woo-config.json';
    }

    public function snappb_get_delivery_category() {
        try {
            $url = $this->apiUrl;
    
            $response = \wp_remote_get($url);
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
