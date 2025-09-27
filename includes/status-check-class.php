<?php
class SnappOrderStatus
{
    private $apiUrl;
    private $headers;

    public function __construct($accessToken = SNAPPBOX_API_TOKEN)
    {
        global $api_base_url;
        $this->apiUrl = $api_base_url . '/v2/orders/';
        $this->headers = [
            'Content-Type' => 'application/json',
        ];

        if (!empty($accessToken)) {
            $this->headers['Authorization'] = $accessToken;
        }
    }

    public function get_order_status($orderID)
    {
        $url = $this->apiUrl . $orderID;

        $response = wp_remote_get($url, [
            'headers' => $this->headers,
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Request error: ' . esc_html($response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);

        return json_decode($body, false);
    }
}
