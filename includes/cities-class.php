<?php
class SnappBoxCities
{
    private $apiUrl;
    private $headers;

    public function __construct()
    {
        global $api_base_url;
        $this->headers = [
            'Content-Type: application/json',
            
        ];
        $this->apiUrl = $api_base_url.'/v2/delivery-category/by-city';
        $this->headers[] = 'Authorization:' . SNAPPBOX_API_TOKEN;
    }

    public function get_delivery_category($latitude, $longitude)
    {
        $queryParams = http_build_query([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
        $url = $this->apiUrl . '?' . $queryParams;
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


