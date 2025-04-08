<?php
class SnappOrderStatus
{
    private $apiUrl = SNAPPBOX_API_BASE_URL_STAGING.'/v2/orders/';
    private $headers;

    public function __construct($accessToken = SNAPPBOX_API_TOKEN)
    {
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


