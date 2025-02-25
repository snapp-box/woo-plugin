<?php

class SnappBoxCities
{
    private $apiUrl = 'https://customer.snapp-box.com/v2/delivery-category/by-city';
    private $headers;

    public function __construct($accessToken = SNAPPBOX_API_TOKEN)
    {
        $this->headers = [
            'Content-Type: application/json',
        ];

        if (!empty($accessToken)) {
            $this->headers[] = 'Authorization: Bearer ' . $accessToken;
        }
    }

    public function get_delivery_category($latitude, $longitude)
    {
        // Build query string parameters
        $queryParams = http_build_query([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        // Append query parameters to the API URL
        $url = $this->apiUrl . '?' . $queryParams;

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_HTTPGET, true); // Set request type to GET

        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, false);
    }
}


