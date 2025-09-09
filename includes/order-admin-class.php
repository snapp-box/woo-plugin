<?php
require_once(SNAPPBOX_DIR . 'includes/create-order-class.php');
require_once(SNAPPBOX_DIR . 'includes/cancel-order-class.php');
require_once(SNAPPBOX_DIR . 'includes/status-check-class.php');
require_once(SNAPPBOX_DIR . 'includes/pricing-class.php');
require_once(SNAPPBOX_DIR . 'includes/convert-woo-cities-to-snappbox.php');

class SnappBoxOrderAdmin
{
    public function __construct($accessToken = SNAPPBOX_API_TOKEN)
    {   
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_leaflet' ] );
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_order_admin_box'], 20, 1);
        add_action('wp_ajax_create_snappbox_order', [$this, 'handle_create_snappbox_order']);
        add_action('wp_ajax_cancel_snappbox_order', [$this, 'handle_cancel_snappbox_order']);
        add_action('wp_ajax_get_pricing', [$this, 'handle_get_pricing']); 
    }

    public function display_location_in_order_admin($order)
    {
        
        $orderID = get_post_meta($order->get_id(), '_snappbox_order_id', true);
        if($orderID){
            echo '<div style="margin-bottom:20px;"><strong>'. esc_html(__('Order ID', 'sb-delivery')).': </strong>' . esc_html($orderID). '</div>';
        }
    }
    public function enqueue_leaflet() {
        wp_enqueue_style(
            'leaflet',
            trailingslashit( SNAPPBOX_URL ) . 'assets/css/leaflet.css',
            [],
            '1.9.4'
        );

        wp_enqueue_script(
            'leaflet',
            trailingslashit( SNAPPBOX_URL ) . 'assets/js/leaflet.js',
            [],
            '1.9.4',
            true
        );
        wp_enqueue_style(
            'snappbox-style',
            trailingslashit( SNAPPBOX_URL ) . 'assets/css/style.css',
            [],
            filemtime( trailingslashit( SNAPPBOX_DIR )  . 'assets/css/style.css' ) 
        );
    }
    public function display_map_in_admin_order($order)
    {
        $latitude = get_post_meta($order->get_id(), '_customer_latitude', true);
        $longitude = get_post_meta($order->get_id(), '_customer_longitude', true);
        if ($latitude && $longitude) {
            echo '<div id="admin-osm-map" style="height: 400px; margin-top: 20px;"></div>';
?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var latitude = <?php echo esc_js($latitude); ?>;
                    var longitude = <?php echo esc_js($longitude); ?>;
                    var map = L.map('admin-osm-map').setView([latitude, longitude], 15);
                    L.tileLayer('https://raster.snappmaps.ir/styles/snapp-style/{z}/{x}/{y}{r}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(map);
                    L.marker([latitude, longitude]).addTo(map)
                        .bindPopup('Customer Location')
                        .openPopup();
                });
            </script>
<?php
        }
    }
   
    public function display_order_admin_box($order){
        if(isset($_GET['action']) && $_GET['action'] == 'edit'){
            ?>
            <script>
                jQuery(document).ready(function(){
                    var secondElement = jQuery('.order_data_column').eq(2);
                    secondElement.addClass('none-class');
                });
            </script>
            </div>
            <div class="order_data_column_fullwidth">
                <h3><?php esc_attr_e('SnappBox', 'sb-delivery');?></h3>
                <?php 
                $this->display_map_in_admin_order($order);
                $this->display_location_in_order_admin($order);
                echo( '<b>' .esc_html(__('Address', 'sb-delivery')) . '</b> : ' .esc_html($order->shipping_address_1));
                $free_delivery = $order->get_meta( '_free_delivery' );
                if($free_delivery){
                    echo('<div><b>'.esc_html($free_delivery).'</b></div>');
                }
                $this->check_order_status();
                $this->display_snappbox_order_button($order);
                ?>
            </div>
            <?php
        }
    }

    public function display_snappbox_order_button($order)
    {
        $snappboxOrder = get_post_meta($order->get_id(), '_snappbox_order_id', true);
        $day  = $order->get_meta('_snappbox_day');
        $time = $order->get_meta('_snappbox_time');
        
        
        if($day && $time){
            $ts = $day ? strtotime($day . ' 12:00:00') : false; 
            $dateLabel = $ts ? wp_date('l j F Y', $ts) : $day;  
            ?>
            <div class="snappbox-order-container clearfix">
                <p><b><?php esc_html_e('Delivery Date and Time', 'sb-delivery');?> :</b>  <?php  esc_html_e($dateLabel); ?>- <?php esc_html_e($time);?></p>
            </div>
            <?php 
        }
        if (!$snappboxOrder) {
?>
            <div class="modal" style="display:none;justify-content:center;align-items:center;background-color:rgba(255,255,255,0.7);z-index:1100;left:0;right:0;top:0;bottom:0;position:absolute;">
                <div class="modal-box" style="width:40%;text-align:center;overflow:hidden;display:flex;flex-direction:column;background-color:#fff;padding-bottom:20px;border:1px solid #ebebeb;border-radius:15px;">
                    <div class="modal-header" style="height:100px;background-color:#22a958;width:100%;"></div>
                    <div class="modal-content">
                        <h3>قیمت اسنپ باکس</h3>
                        <p id="pricing-message">در حال دریافت قیمت...</p>
                        <div class="snappbox-order-container">
                            <button id="snappbox-create-order" data-order-id="<?php echo esc_attr($order->get_id()); ?>" class="snappbox-btn button button-primary">
                                <?php esc_html_e('Send to SnappBox', 'sb-delivery'); ?>
                            </button>
                        </div>
                        <img class="ct-order-loading" style="visibility:hidden" src="<?php esc_html_e(SNAPPBOX_URL) ;?>/assets/img/ld.svg" />
                        <span id="snappbox-response"></span>

                    </div>
                    <div class="vds-content" style="display:none">
                        <video width="320" height="240" autoplay loop muted><source src="<?php esc_html_e(SNAPPBOX_URL)?>assets/vds/cup.mp4" type="video/mp4"></video>
                        <span id="snappbox-response-victory"></span>
                    </div>
                    
                    <a href="#" class="close" style="margin-top:15px;">بستن</a>
                </div>
            </div>

            <div class="snappbox-order-container clearfix" style="clear: both;margin-top: 20px;float: left;width: 100%;">
                <button id="snappbox-pricing-order" data-order-id="<?php echo esc_attr($order->get_id()); ?>" class="snappbox-btn button button-primary">
                    <?php esc_html_e('Get SnappBox Price', 'sb-delivery'); ?>
                    
                </button>
                <img class="loading" style="display:none" src="<?php esc_html_e(SNAPPBOX_URL);?>/assets/img/ld.svg" />
            </div>
<?php
        } else {
            $orderID = get_post_meta($_GET['id'], '_snappbox_order_id', true);
            $getResponse = get_post_meta($orderID, '_snappbox_last_api_response', true);
            if($getResponse->canCancel == 1){
                ?>
                <div class="snappbox-cancel-container" style="clear: both;margin-top: 20px;float: left;width: 100%;">
                    <button id="snappbox-cancel-order" data-order-id="<?php echo esc_attr($snappboxOrder); ?>" class="cancel-order button button-secondary">
                        <?php esc_html_e('Cancel Order', 'sb-delivery'); ?>
                    </button>
                    <img class="cancel-order-loading" style="visibility:hidden" src="<?php esc_html_e(SNAPPBOX_URL);?>/assets/img/ld.svg" />
                    <span id="snappbox-cancel-response"></span>
                </div>
                <?php 
            }
        }
        
?>

        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('.close').on('click', function (e) {
                    e.preventDefault();
                    $('.modal').hide();
                });

                $('#snappbox-pricing-order').on('click', function (e) {
                    e.preventDefault();
                    let orderId = $(this).data('order-id');
                    
                    jQuery('.loading').css('display', 'inline-block');
                    $.ajax({
                        url: '<?php esc_html_e(admin_url('admin-ajax.php')); ?>',
                        type: 'POST',
                        data: {
                            action: 'get_pricing',
                            order_id: orderId
                        },
                        beforeSend: function () {
                            $('#pricing-message').text('در حال دریافت قیمت...');
                        },
                        success: function (response) {
                            $('.modal').css('display', 'flex');
                            if(response.success != false){
                                jQuery('#snappbox-create-order').css('display', 'block');
                                let wooCurrency = '<?php esc_html_e(get_woocommerce_currency());?>';
                                if( wooCurrency == 'IRT'){
                                    var finalFare = snappboxRialToToman(response.data.finalCustomerFare)
                                    var simbol = 'تومان'
                                }
                                else{
                                    var finalFare = response.data.finalCustomerFare
                                    var simbol = 'ریال'
                                }

                                jQuery('.loading').css('display', 'none');
                                $('#pricing-message').text('قیمت تخمینی: ' + new Intl.NumberFormat("en-IR", { maximumSignificantDigits: 3 }).format(
                                    finalFare,) + simbol);
                            }
                            else{
                                jQuery('.loading').css('display', 'none');
                                jQuery('#pricing-message').text(response.data.message);
                                jQuery('#snappbox-create-order').css('display', 'none');
                            }
                            
                        },
                        error: function (response) {
                            $('#pricing-message').text('خطا در ارسال درخواست.');
                            jQuery('.loading').css('display', 'none');
                        }
                    });
                });
                function snappboxRialToToman(currency){
                    return parseInt(currency) / 10;
                }
                $('#snappbox-create-order').on('click', function (e) {
                    e.preventDefault();
                    let orderId = $(this).data('order-id');

                    $.ajax({
                        url: '<?php esc_html_e(admin_url('admin-ajax.php')); ?>',
                        type: 'POST',
                        data: {
                            action: 'create_snappbox_order',
                            order_id: orderId
                        },
                        beforeSend: function () {
                            $('.ct-order-loading').css('visibility', 'visible');
                        },
                        success: function (response) {
                            if (response.response.status_code === '201') {
                                let data = response.response.data;
                                $('.modal-content').css('display', 'none');
                                $('.vds-content').css('display', 'block');
                                $('#snappbox-response-victory').html('<span style="color:green;">' + response.response.message + '</span>');
                                location.reload();
                            } else {
                                $('#snappbox-response').html('<span style="color:red;">Error: ' + response.response.data + '</span>');
                            }
                            $('.ct-order-loading').css('visibility', 'hidden');
                        },
                        error: function () {
                            $('#snappbox-response').text('Error sending order.');
                            $('.ct-order-loading').css('visibility', 'hidden');
                        }
                    });
                });

                $('#snappbox-cancel-order').on('click', function (e) {
                    e.preventDefault();
                    let orderId = $(this).data('order-id');

                    $.ajax({
                        url: '<?php esc_html_e(admin_url('admin-ajax.php')); ?>',
                        type: 'POST',
                        data: {
                            action: 'cancel_snappbox_order',
                            order_id: orderId,
                            woo_order_id: <?php esc_html_e($order->get_id()); ?>
                        },
                        beforeSend: function () {
                            $('.cancel-order-loading').css('visibility', 'visible');
                        },
                        success: function (response) {
                            if (response.success) {
                                $('#snappbox-cancel-response').html('<span style="color:green;">' + response.data + '</span>');
                                $('.cancel-order-loading').css('visibility', 'hidden');
                                location.reload();
                            } else {
                                $('#snappbox-cancel-response').html('<span style="color:red;">Error: ' + response.data + '</span>');
                                $('.cancel-order-loading').css('visibility', 'hidden');
                            }
                        },
                        error: function () {
                            $('#snappbox-cancel-response').text('Error cancelling order.');
                            $('.cancel-order-loading').css('visibility', 'hidden');
                        }
                    });
                });
            });
        </script>
<?php
    }

    public function handle_create_snappbox_order()
    {
        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Order ID missing');
        }

        $order_id = intval($_POST['order_id']);
        if (!$order_id) {
            wp_send_json_error('Invalid Order ID');
        }

        $snappbox_order = new SnappBoxCreateOrder();

        $response = $snappbox_order->handleCreateOrder($order_id);
        
        if ($response['success']) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error($response['message']);
        }
    }

    public function handle_get_pricing()
    {
        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Order ID missing');
        }
        
        $order_id = intval($_POST['order_id']);

        if (!$order_id) {
            wp_send_json_error('Invalid Order ID');
        }
        $order = wc_get_order($order_id);
        $state = strtolower($order->get_billing_state());
        $allCities = new SnappBoxCityHelper();
        $city_map = $allCities->get_city_to_state_map();
        $state_code = isset($city_map[strtoupper($state)]) ? $city_map[strtoupper($state)] : null;
        
        $settings_serialized = get_option('woocommerce_snappbox_shipping_method_settings');
        $settings = maybe_unserialize($settings_serialized);
        $stored_cities = $settings['snappbox_cities'];
        
        if(in_array($state_code, $stored_cities)){
            $pricing_api = new SnappBoxPriceHandler();
            $response = $pricing_api->get_pricing($order_id, $state_code);

            if ($response['success'] == true) {
                wp_send_json_success([
                    'fare' => $response['data']['finalCustomerFare']
                ]);
            } else {
                print_r($response['message']);
                die();
            }
        } 
        
        
    }

    public function handle_cancel_snappbox_order()
    {
        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Order ID missing');
        }

        $order_id = sanitize_text_field($_POST['order_id']);
        $woo_order_id = sanitize_text_field($_POST['woo_order_id']);

        $snappbox_api = new SnappBoxCancelOrder();
        $response = $snappbox_api->cancel_order($order_id);

        if ($response['success'] === false) {
            delete_post_meta($woo_order_id, '_snappbox_order_id');
            delete_post_meta($woo_order_id, '_snappbox_last_api_response');
            delete_post_meta($woo_order_id, '_snappbox_last_api_call');
            wp_send_json_success($response['message']);
        } else {
            wp_send_json_error($response['message']);
        }
    }

   
    
    public function check_order_status()
    {
        (isset($_GET['id']) ? $currentID = $_GET['id'] : $currentID = $_GET['post']);
        $orderID = get_post_meta($currentID, '_snappbox_order_id', true);
        $getResponse = get_post_meta($orderID, '_snappbox_last_api_response', true);
        $last_called = get_post_meta($orderID, '_snappbox_last_api_call', true);
        
        if ($getResponse) {
            esc_html_e('<p><b>'.__('Status', 'sb-delivery').'</b>: ' . esc_html($getResponse->statusText) . '</p>');
        }
        
        $statusCehck = new SnappOrderStatus();
        $response = $statusCehck->get_order_status($orderID);
        if (!is_wp_error($response)) {
            update_post_meta($orderID, '_snappbox_last_api_response', $response);
            update_post_meta($orderID, '_snappbox_last_api_call', time());
        } else {
            error_log('API Error: ' . $response->get_error_message());
        }
    }
}
