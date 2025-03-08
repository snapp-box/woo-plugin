<?php
class SnappBoxCreateOrder {
    private $api_url = SNAPPBOX_API_BASE_URL_STAGING."/v1/customer/create_order";
    private $api_key; 

    public function __construct($api_key = SNAPPBOX_API_TOKEN) {
        $this->api_key = $api_key;
        add_action('wp_ajax_snappbox_create_order', [$this, 'handleCreateOrder']);
        add_action('wp_ajax_nopriv_snappbox_create_order', [$this, 'handleCreateOrder']);
    }

    public function create_order($order_data) {
        $args = [
            'body'    => json_encode($order_data),
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'method'  => 'POST',
            'timeout' => 45,
        ];
        if ($this->api_key) {
            $args['headers']['Authorization'] = $this->api_key;
        }
        $response = wp_remote_post($this->api_url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        return [
            'success' => true,
            'response' => json_decode(wp_remote_retrieve_body($response), true),
        ];
    }

    public function handleCreateOrder() {
        if (!isset($_POST['order_id'])) {
            wp_send_json_error(['message' => 'Order ID is missing']);
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Invalid order']);
        }

        $order_data = $this->prepareOrderData($order, $order_id);
        $response = $this->create_order($order_data);
        wp_send_json($response);
    }

    private function prepareOrderData($order, int $order_id): array {
        return [
            "data" => [
                "timeSlotDTO" => [
                    "startTimeSlot" => current_time( 'mysql' ),
                    "endTimeSlot" => date('Y-m-d H:i:s', strtotime('+2 hours', current_time('timestamp')))
                ],
                "itemDetails" => $this->getItemDetails($order),
                "orderDetails" => $this->getOrderDetails($order, $order_id),
                "pickUpDetails" => $this->getPickupDetails(),
                "dropOffDetails" => $this->getDropoffDetails($order),
                "verificationCodeEnabled" => true
            ]
        ];
    }

    private function getItemDetails($order): array {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
            "pickedUpSequenceNumber" => 1,
            "dropOffSequenceNumber" => 2,
            "name" => $item->get_name(),
            "quantity" => $item->get_quantity(),
            "quantityMeasuringUnit" => "unit",
            "packageValue" => $item->get_total(),
            "externalRefType" => "INSURANCE",
            "externalRefId" => 99
            ];
        }
        return $items;
    }

    private function getOrderDetails($order, int $order_id): array {
        return [
            "city" => $order->get_billing_city(),
            "customerWalletType" => null,
            "deliveryCategory" => "bike",
            "deliveryFarePaymentType" => "cod",
            "isReturn" => false,
            "loadAssistance" => false,
            "pricingId" => "",
            "sequenceNumberDeliveryCollection" => 1,
            "orderLevelServices" => [["id" => 4, "quantity" => 1]],
            "customerEmail" => $order->get_billing_email(),
            "customerName" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            "customerPhonenumber" => $order->get_billing_phone(),
            "customerRefId" => 31,
            "voucherCode" => "",
            "waitingTime" => 0
        ];
    }

    private function getPickupDetails(): array {
        return [[
            "id" => null,
            "contactName" => get_option('snappbox_store_name', ''),
            "address" => WC()->countries->get_base_address() .' '. WC()->countries->get_base_address_2(),
            "contactPhoneNumber" => get_option('snappbox_store_phone'),
            "latitude" => get_option('snappbox_latitude', ''),
            "longitude" => get_option('snappbox_longitude', ''),
            "type" => "pickup",
            "paymentType" => "prepaid",
            "vendorId" => 0,
            "services" => [["itemServiceId" => 2, "quantity" => 1]]
        ]];
    }

    private function getDropoffDetails($order): array {
        $latitude = get_post_meta($order->get_id(), '_customer_latitude', true);
        $longitude = get_post_meta($order->get_id(), '_customer_longitude', true);
        return [[
            "id" => null,
            "contactName" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            "address" => $order->get_billing_address_1(),
            "contactPhoneNumber" => $order->get_billing_phone(),
            "latitude" => $latitude,
            "longitude" => $longitude,
            "type" => "drop",
            "paymentType" => "prepaid",
            "vendorId" => 0,
            "services" => [["itemServiceId" => 1, "quantity" => 1]],
            "verificationCodeGenerationStrategy" => "AUTO",
            "terminalDetails" => [["verificationCode" => '']]
        ]];
    }
}





