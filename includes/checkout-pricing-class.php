<?php

if (!defined('ABSPATH')) {
    exit; 
}

class SnappBoxPricing {
    private static $instance = null;
    private $api_url = SNAPPBOX_API_BASE_URL_STAGING."/v1/customer/order/pricing";
    private $api_token = SNAPPBOX_API_TOKEN;
    private $freeDelivery;
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    
    public function __construct() {
        $this->freeDelivery = get_option('free_delivery');
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_snappbox_pricing', [$this, 'get_pricing']);
        add_action('wp_ajax_nopriv_snappbox_pricing', [$this, 'get_pricing']);
        add_action('woocommerce_after_shipping_rate', [$this, 'inject_checkout_script']);
        
    }

    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script('jquery');
        }
    }

    public function inject_checkout_script() {
        if (!is_checkout() ) return;
        if(empty($this->freeDelivery)) {
            ?>
            <script>
                jQuery(document).ready(function ($) {
                    function fetchSnappboxPrice() {
                        let city = $("#billing_city").val();
                        let address = $("#billing_address_1").val();
                        let latitude = $("#customer_latitude").val();
                        let longitude = $("#customer_longitude").val();
                        
                        if (!city || !address) return;

                        let data = {
                            action: "snappbox_pricing",
                            city: city,
                            address: address,
                            latitude: latitude,
                            longitude: longitude
                        };

                        $.ajax({
                            url: "<?php echo admin_url('admin-ajax.php'); ?>",
                            type: "POST",
                            data: data,
                            success: function (response) {
                                if (response.success) {
                                    $("#snappbox_price").text("Delivery Price: " + response.data.finalCustomerFare + " Toman");
                                } else {
                                    console.error(response.data);
                                }
                            }
                        });
                    }

                    $("#billing_city, #billing_address_1").on("change", function () {
                        fetchSnappboxPrice();
                    });

                    fetchSnappboxPrice();
                });
            </script>
            <div id="snappbox_price" style="margin-top: 10px; font-weight: bold;"></div>
            <?php 
        }
        ?>
        <?php
    }

    
    public function get_pricing() {
        
        $city = sanitize_text_field($_POST['city']);
        $address = sanitize_text_field($_POST['address']);
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        if ($this->freeDelivery) {
            wp_send_json_success(['finalCustomerFare' => 0]);
        }
        $body = [
            "city" => $city,
            "customerWalletType" => null,
            "deliveryCategory" => "bike-without-box",
            "isReturn" => false,
            "loadAssistance" => false,
            "voucherCode" => "",
            "waitingTime" => 0,
            "terminals" => [
                [
                    "address" => WC()->countries->get_base_address() . ' ' . WC()->countries->get_base_address_2(),
                    "sequenceNumber" => 1,
                    "latitude" => get_option('snappbox_latitude', ''),
                    "longitude" => get_option('snappbox_longitude', ''),
                    "type" => "pickup"
                ],
                [
                    "address" => $address,
                    "sequenceNumber" => 2,
                    "latitude" => $latitude, 
                    "longitude" => $longitude,
                    "type" => "drop"
                ]
            ]
        ];
        
            $response = wp_remote_post($this->api_url, [
                'method'    => 'POST',
                'body'      => json_encode($body),
                'headers'   => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => $this->api_token
                ],
                'timeout'   => 30
            ]);
    
            if (is_wp_error($response)) {
                wp_send_json_error($response->get_error_message());
            }
    
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
            if (!empty($response_body['finalCustomerFare'])) {
                wp_send_json_success(['finalCustomerFare' => $response_body['finalCustomerFare']]);
            } else {
                wp_send_json_error('Invalid response from SnappBox API');
            }
        
    }
}

