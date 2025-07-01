<?php
class SnappOrderStatus
{
    private $apiUrl;
    private $headers;

    public function __construct($accessToken = SNAPPBOX_API_TOKEN)
    {
        global $api_base_url;
        $this->apiUrl = $api_base_url.'/v2/orders/';
        $this->headers = [
            'Content-Type: application/json',
        ];

        if (!empty($accessToken)) {
            $this->headers[] = 'Authorization:' . $accessToken;
        }
    }

    public function get_order_status($orderID)
    {
        $url = $this->apiUrl . $orderID;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_HTTPGET, true); 

        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, false);
    }
}


