<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('WooCommerce')) {
    return;
}

require_once(SNAPPBOX_DIR . 'includes/cities-class.php');
require_once(SNAPPBOX_DIR . 'includes/wallet-balance-class.php');
require_once(SNAPPBOX_DIR . 'includes/convert-woo-cities-to-snappbox.php');

class SnappBoxShippingMethod extends WC_Shipping_Method
{
    const API_NONCE_ACTION = 'snappbox_save_api_key';
    const API_NONCE_FIELD  = 'snappbox_api_nonce';

    public function __construct($instance_id = 0)
    {
        $this->id                 = 'snappbox_shipping_method';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('SnappBox Shipping Method', 'sb-delivery');
        $this->method_description = __('A SnappBox shipping method with dynamic pricing.', 'sb-delivery');
        $this->supports = ['shipping-zones', 'instance-settings', 'settings'];
        $this->init();
    }

    public function init()
    {
        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled');
        $this->title   = $this->get_option('title');

        add_action('admin_enqueue_scripts', array($this, 'enqueue_leafles_scripts'), 10, 1);
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_checkout_create_order', array($this, 'snappbox_order_register'), 10, 2);
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        add_action('admin_notices', [$this, 'admin_alert']);
    }

    public function enqueue_leafles_scripts()
    {
        wp_enqueue_style(
            'snappbox-style',
            trailingslashit(SNAPPBOX_URL) . 'assets/css/style.css',
            [],
            filemtime(trailingslashit(SNAPPBOX_DIR)  . 'assets/css/style.css')
        );
        wp_enqueue_script('maplibreg', trailingslashit(SNAPPBOX_URL) . 'assets/js/leaflet.js', [], null, true);
        wp_enqueue_style('leaflet-css', trailingslashit(SNAPPBOX_URL) . 'assets/css/leaflet.css');
    }

    public function process_admin_options()
    {
        parent::process_admin_options();

        $posted_key = null;
        if (isset($_POST['snappbox_api'])) {
            $posted_key = sanitize_text_field(wp_unslash($_POST['snappbox_api']));
        } elseif (isset($_POST['woocommerce_snappbox_shipping_method_snappbox_api'])) {
            $posted_key = sanitize_text_field(wp_unslash($_POST['woocommerce_snappbox_shipping_method_snappbox_api']));
        }

        if ($posted_key !== null) {
            $nonce_ok = isset($_POST[self::API_NONCE_FIELD]) &&
                wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::API_NONCE_FIELD])), self::API_NONCE_ACTION);

            if (! $nonce_ok) {
                if (class_exists('WC_Admin_Settings')) {
                    WC_Admin_Settings::add_error(__('Security check failed. API key was not saved.', 'sb-delivery'));
                } else {
                    add_settings_error('woocommerce', 'snappbox_api_nonce', __('Security check failed. API key was not saved.', 'sb-delivery'), 'error');
                }
                return;
            }

            $settings = maybe_unserialize(get_option('woocommerce_snappbox_shipping_method_settings'));
            if (! is_array($settings)) {
                $settings = [];
            }
            $settings['snappbox_api'] = $posted_key;
            update_option('woocommerce_snappbox_shipping_method_settings', $settings);
            $this->settings = $settings;

            if (class_exists('WC_Admin_Settings')) {
                WC_Admin_Settings::add_message(__('API key saved.', 'sb-delivery'));
            }
        }
    }

    public function customize_checkout_fields($fields)
    {
        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['label'] = __('Mobile Phone', 'sb-delivery');
            $fields['billing']['billing_phone']['placeholder'] = "09121234567";
            $fields['billing']['billing_phone']['required'] = true;
        }
        return $fields;
    }

    public function snappbox_order_register($order, $data)
    {
        global $woocommerce;

        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        $chosen_shipping_method  = is_array($chosen_shipping_methods) ? ($chosen_shipping_methods[0] ?? '') : '';
        $totalCard               = floatval(preg_replace('#[^\d.]#', '', $woocommerce->cart->get_cart_total()));

        $free_delivery = $this->get_option('free_delivery');

        if (! empty($free_delivery) && $free_delivery < $totalCard) {
            $order->update_meta_data('_free_delivery', __('SnappBox cost is free', 'sb-delivery'));
        }

        $shipping_city     = isset($data['shipping_state']) ? $data['shipping_state'] : '';
        $shipping_cityName = isset($data['shipping_city']) ? $data['shipping_city'] : '';

        $settings_serialized = get_option('woocommerce_snappbox_shipping_method_settings');
        $settings            = maybe_unserialize($settings_serialized);
        $allCities           = new SnappBoxCityHelper();
        $stored_cities       = isset($settings['snappbox_cities']) ? (array) $settings['snappbox_cities'] : [];

        if ($chosen_shipping_method === 'snappbox_shipping_method') {
            $nonce_field  = 'snappbox_geo_nonce';
            $nonce_action = 'snappbox_geo_meta';
            if (empty($_POST[$nonce_field]) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_field])), $nonce_action)) {
                throw new Exception(esc_html__('Security check failed. Please refresh the page and try again.', 'sb-delivery'));
            }
        }

        $city = isset($_POST['customer_city']) ? sanitize_text_field(wp_unslash($_POST['customer_city'])) : '';

        if ($chosen_shipping_method === 'snappbox_shipping_method') {
            if (in_array(strtolower($city), array_map('strtolower', $stored_cities), true)) {
                $order->add_order_note('Order registered with SnappBox in ' . $shipping_city);
                $order->update_meta_data('_snappbox_city', $shipping_city);
            } else {
                throw new Exception(esc_html__('SnappBox is not available in your city', 'sb-delivery'));
            }
        }
    }


    public function init_form_fields()
    {
        $latitude  = get_option('snappbox_latitude', '35.8037761');
        $longitude = get_option('snappbox_longitude', '51.4152466');
        $settings_serialized = get_option('woocommerce_snappbox_shipping_method_settings');
        $settings = maybe_unserialize($settings_serialized);

        $stored_cities = isset($settings['snappbox_cities']);
        $snappBoxAPIKey = $this->get_option('snappbox_api');

        $transient_key = 'snappbox_cities_' . md5($latitude . '_' . $longitude);
        $cities = get_transient($transient_key);

        if ($cities === false) {
            $citiesObj = new SnappBoxCities();
            $cities = $citiesObj->get_delivery_category($latitude, $longitude);

            if (!empty($cities) && isset($cities->cities)) {
                set_transient($transient_key, $cities, DAY_IN_SECONDS);
            }
        }

        $city_options = [];
        if (!empty($cities->cities) && is_array($cities->cities)) {
            $filtered_cities = array_filter($cities->cities, function ($city) {
                return !empty($city->cityName);
            });

            $city_options = array_column($filtered_cities, 'cityName', 'cityKey');
        }

        $this->form_fields = [
            'enabled' => [
                'title'       => __('Enable', 'sb-delivery'),
                'type'        => 'checkbox',
                'description' => __('Enable this shipping method', 'sb-delivery'),
                'default'     => 'yes',
            ],
            'sandbox' => [
                'title'       => __('Enable Test Mode', 'sb-delivery'),
                'type'        => 'checkbox',
                'description' => __('Enable test mode for this plugin', 'sb-delivery'),
                'default'     => 'yes',
            ],
            'title' => [
                'title'       => __('Title', 'sb-delivery'),
                'type'        => 'text',
                'description' => __('Title to display during checkout', 'sb-delivery'),
                'default'     => __('SnappBox Shipping', 'sb-delivery'),
            ],
            'fixed_price' => [
                'title'       => __('Fixed Price', 'sb-delivery'),
                'type'        => 'number',
                'description' => __('Leave it empty for canceling fixed price', 'sb-delivery') . '. ' . __('You must enter a price in ', 'sb-delivery') . ' : ' . get_woocommerce_currency_symbol(),
            ],
            'free_delivery' => [
                'title'       => __('Free Delivery', 'sb-delivery'),
                'type'        => 'number',
                'description' => __('Minimum basket price for free delivery', 'sb-delivery') . '. ' . __('You must enter a price in ', 'sb-delivery') . ' : ' . get_woocommerce_currency_symbol(),
            ],
            'base_cost' => [
                'title'       => __('Base Shipping Cost', 'sb-delivery'),
                'type'        => 'number',
                'description' => __('Base shipping cost for this method', 'sb-delivery'),
                'default'     => '5',
                'desc_tip'    => true,
            ],
            'cost_per_kg' => [
                'title'       => __('Cost per KG', 'sb-delivery'),
                'type'        => 'number',
                'description' => __('Shipping cost per kilogram', 'sb-delivery'),
                'default'     => '',
                'desc_tip'    => true,
            ],

            'snappbox_latitude' => [
                'title'       => __('Latitude', 'sb-delivery'),
                'type'        => 'text',
                'default'     => $latitude,
                'custom_attributes' => ['readonly' => 'readonly']
            ],
            'snappbox_longitude' => [
                'title'       => __('Longitude', 'sb-delivery'),
                'type'        => 'text',
                'default'     => $longitude,
                'custom_attributes' => ['readonly' => 'readonly']
            ],
            'snappbox_store_phone' => [
                'title'       => __('Phone Number', 'sb-delivery'),
                'type'        => 'text',
                'default'     => get_option('snappbox_store_phone', '')
            ],
            'snappbox_store_name' => [
                'title'       => __('Store Name', 'sb-delivery'),
                'type'        => 'text',
                'default'     => get_option('snappbox_store_name', '')
            ],
            'ondelivery' => [
                'title'       => __('Enable payment on delivery', 'sb-delivery'),
                'type'        => 'checkbox',
                'description' => __('Pay SnappBox payment on delivery', 'sb-delivery'),
                'default'     => 'no',
            ],
            'snappbox_cities' => [
                'title'       => __('Cities', 'sb-delivery'),
                'type'        => 'multiselect',
                'options'     => $city_options,
                'description' => __('This Item will show after token insertion', 'sb-delivery'),
                'default'     => $stored_cities,
            ]
        ];
    }

    public function admin_options()
    {
        $walletObj = new SnappBoxWalletBalance();
        $walletObjResult = $walletObj->check_balance();

        echo ('<div class="snappbox-panel right">');
        parent::admin_options();
        echo ('</div>');

        $lat = $this->get_option('snappbox_latitude', '35.8037761');
        $lng = $this->get_option('snappbox_longitude', '51.4152466');
?>

        <div style="margin-bottom: 5px; float:left;">
            <a href="#" id="snappbox-launch-modal" class="button colorful-button button-secondary">
                <?php echo esc_html__('Show Setup Guide', 'sb-delivery'); ?>
            </a>
            <a href="#" id="snappbox-launch-modal-guide" class="button colorful-button button-secondary">
                <?php echo esc_html__('Show Dates', 'sb-delivery'); ?>
            </a>
        </div>

        <?php $this->token_integeration(); ?>
        <?php $this->wallet_information(); ?>

        <div class="snappbox-panel">
            <h4><?php esc_html_e('Set Store Location', 'sb-delivery'); ?></h4>
            <p><?php esc_html_e('Please move the pin', 'sb-delivery'); ?></p>

            <div id="map" style="height:400px; position:relative;">
                <button id="center-pin" type="button" aria-label="<?php esc_attr_e('Set this location', 'sb-delivery'); ?>"></button>
            </div>

            <style>
                #center-pin {
                    position: absolute;
                    left: 50%;
                    top: 50%;
                    transform: translate(-50%, -100%);
                    width: 34px;
                    height: 34px;
                    border: 0;
                    padding: 0;
                    background: transparent;
                    cursor: pointer;
                    z-index: 5;
                }

                #center-pin::before {
                    content: "";
                    position: absolute;
                    inset: 0;
                    background-repeat: no-repeat;
                    background-position: center;
                    background-size: contain;
                    background-image: url('data:image/svg+xml;utf8,<svg width="48" height="48" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="%23e53935" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="3" fill="white"/></svg>');
                }

                #center-pin::after {
                    content: "";
                    position: absolute;
                    left: 50%;
                    top: 100%;
                    transform: translate(-50%, 2px);
                    width: 10px;
                    height: 10px;
                    border-radius: 50%;
                    box-shadow: 0 0 0 2px rgba(0, 0, 0, .12);
                    background: rgba(0, 0, 0, .06);
                }

                .maplibregl-marker {
                    z-index: 9999 !important;
                }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof maplibregl === 'undefined') {
                        console.error('MapLibre not loaded');
                        return;
                    }

                    const defaultLat = <?php echo esc_html($lat); ?>;
                    const defaultLng = <?php echo esc_html($lng); ?>;

                    const map = new maplibregl.Map({
                        container: 'map',
                        style: 'https://tile.snappmaps.ir/styles/snapp-style-v4.1.2/style.json',
                        center: [defaultLng, defaultLat], 
                        zoom: 16,
                        attributionControl: true
                    });

                    maplibregl.setRTLTextPlugin(
                        'https://unpkg.com/@mapbox/mapbox-gl-rtl-text@0.3.0/dist/mapbox-gl-rtl-text.js',
                        null,
                        true
                    );

                    map.addControl(new maplibregl.NavigationControl({
                        visualizePitch: true
                    }), 'top-right');

                    const marker = new maplibregl.Marker({
                            draggable: true
                        })
                        .setLngLat([defaultLng, defaultLat])
                        .addTo(map);

                    function updateInputs(lat, lng) {
                        const latInput = document.querySelector('[name="woocommerce_snappbox_shipping_method_snappbox_latitude"]');
                        const lngInput = document.querySelector('[name="woocommerce_snappbox_shipping_method_snappbox_longitude"]');
                        if (latInput) latInput.value = Number(lat).toFixed(9);
                        if (lngInput) lngInput.value = Number(lng).toFixed(9);
                    }

                    function onSet(lat, lng) {
                        marker.setLngLat([lng, lat]);
                        updateInputs(lat, lng);
                    }

                    marker.on('dragend', function() {
                        const p = marker.getLngLat(); // {lng, lat}
                        updateInputs(p.lat, p.lng);
                    });

                    map.on('click', function(e) {
                        onSet(e.lngLat.lat, e.lngLat.lng);
                    });

                    const centerPinBtn = document.getElementById('center-pin');
                    centerPinBtn.addEventListener('click', function() {
                        const c = map.getCenter(); // {lng, lat}
                        onSet(c.lat, c.lng);
                    });

                    updateInputs(defaultLat, defaultLng);
                });
            </script>
        </div>

        <?php $this->add_modal_box(); ?>
        <?php
    }


    public function admin_alert($walletObjResult = null)
    {
        static $notice_output = false;
        if ($notice_output) return;

        if (!$walletObjResult) {
            $walletObj = new SnappBoxWalletBalance();
            $walletObjResult = $walletObj->check_balance();
        }

        if (empty($walletObjResult)) return;

        $currentBalance = isset($walletObjResult['response']['currentBalance'])
            ? floatval($walletObjResult['response']['currentBalance'])
            : 0.0;

        $balanceDefaultResponse = wp_remote_get('https://assets.snapp-box.com/static/plugin/woo-config.json');
        if (is_wp_error($balanceDefaultResponse)) return;

        $balanceDefault = wp_remote_retrieve_body($balanceDefaultResponse);
        $config = json_decode($balanceDefault);
        if (!$config || !isset($config->minWalletCredit)) return;

        $minCredit = floatval($config->minWalletCredit);

        $was_low = (bool) get_option('snappbox_wallet_was_low', false);
        $is_low  = ($currentBalance < $minCredit);

        if ($is_low && !$was_low) {
            $notice_output = true;
        ?>
            <div class="notice notice-error is-dismissible snappbox-low-balance">
                <p>
                    <?php esc_html_e('Your wallet balance is too low. Please contact Snappbox', 'sb-delivery'); ?>
                    <a href="https://app.snapp-box.com/top-up" target="_blank">
                        <?php esc_html_e('Learn more.', 'sb-delivery'); ?>
                    </a>
                </p>
            </div>
        <?php
            update_option('snappbox_wallet_was_low', true, false);
            return;
        }

        if (!$is_low && $was_low) {
            update_option('snappbox_wallet_was_low', false, false);
        }
    }

    public function wallet_information()
    { ?>
        <div class="snappbox-panel">
            <h4><?php esc_html_e('wallet Information', 'sb-delivery'); ?></h4>
            <?php $walletObj = new SnappBoxWalletBalance();
            $walletObjResult = $walletObj->check_balance();

            if (!empty($walletObjResult) && isset($walletObjResult['response']['currentBalance'])) {
                if (get_woocommerce_currency() == 'IRT') {
                    $currentBalance = $this->snappbox_rial_to_toman($walletObjResult['response']['currentBalance']);
                } else {
                    $currentBalance = $walletObjResult['response']['currentBalance'];
                }
                echo ('<p>' . esc_html__('Your current balance is: ', 'sb-delivery') .  esc_html($currentBalance) . ' ' . esc_html(get_woocommerce_currency_symbol()) . '</p>');
            } else {
                echo (esc_html__('Unable to fetch wallet balance.', 'sb-delivery'));
            }
            ?>
        </div>
    <?php
    }

    public function token_integeration()
    {
        $api_key = $this->get_option('snappbox_api');
    ?>
        <div class="snappbox-panel">
            <h4><?php esc_html_e('API Key', 'sb-delivery'); ?></h4>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="snappbox_api"><?php esc_html_e('SnappBox API Key', 'sb-delivery'); ?></label>
                    </th>
                    <td width="35%">
                        <input type="text"
                            id="snappbox_api"
                            name="snappbox_api"
                            class="regular-text"
                            value="<?php echo esc_attr($api_key); ?>"
                            placeholder="<?php esc_attr_e('Paste your API key…', 'sb-delivery'); ?>" />
                        <?php
                        wp_nonce_field(self::API_NONCE_ACTION, self::API_NONCE_FIELD);
                        ?>
                    </td>
                    <td>
                        <a href="https://snapp-box.com/connect" class="snappbox-token" target="_blank">درخواست توکن</a>
                    </td>
                </tr>
            </table>
            <div class="token-info">
                <svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="0.5" y="0.644684" width="19" height="19" rx="9.5" stroke="#3581C4" />
                    <path d="M9.54401 5.24055C9.6879 5.17718 9.84343 5.14452 10.0007 5.14465C10.1578 5.14469 10.3132 5.17748 10.4569 5.24092C10.6007 5.30436 10.7296 5.39707 10.8355 5.51313C10.9415 5.62919 11.022 5.76604 11.0721 5.91498C11.1222 6.06391 11.1407 6.22164 11.1264 6.37812L10.6756 11.3392C10.6581 11.506 10.5794 11.6604 10.4548 11.7726C10.3301 11.8849 10.1684 11.947 10.0007 11.947C9.83295 11.947 9.67118 11.8849 9.54654 11.7726C9.42191 11.6604 9.34325 11.506 9.32572 11.3392L8.8737 6.37812C8.85941 6.22154 8.87793 6.0637 8.92808 5.91468C8.97823 5.76567 9.05891 5.62875 9.16496 5.51267C9.27101 5.39659 9.40011 5.30391 9.54401 5.24055Z" fill="#3581C4" />
                    <path d="M11.081 14.0644C11.081 14.661 10.5973 15.1447 10.0007 15.1447C9.40407 15.1447 8.92041 14.661 8.92041 14.0644C8.92041 13.4677 9.40407 12.9841 10.0007 12.9841C10.5973 12.9841 11.081 13.4677 11.081 14.0644Z" fill="#3581C4" />
                </svg>
                <p>هنگام دریافت API Key با شماره‌ای در اسنپ باکس وارد شوید که می‌خواهید سفارشات را از آن پیگیری، و همچنین اعلانات را دریافت کنید.</p>
            </div>
        </div>
    <?php
    }

    public function snappbox_rial_to_toman($amount)
    {
        return $amount / 10;
    }

    public function calculate_shipping($package = [])
    {
        global $woocommerce;
        $base_cost    = $this->get_option('base_cost');
        $cost_per_kg  = $this->get_option('cost_per_kg');
        $fixed_price  = $this->get_option('fixed_price');
        $free_delivery = $this->get_option('free_delivery');

        $totalCard = floatval(preg_replace('#[^\d.]#', '', $woocommerce->cart->get_cart_total()));
        $weight    = 0;

        if (empty($fixed_price)) {
            foreach ($package['contents'] as $item) {
                $product = $item['data'];
                // $weight += $product->get_weight() * $item['quantity'];
            }
            $cost = $base_cost;
        } elseif (!empty($free_delivery) && $free_delivery < $totalCard) {
            $cost = 0;
        } elseif (empty($free_delivery) && empty($base_cost) && empty($cost_per_kg)) {
            $cost = 0;
        } else {
            $cost = $fixed_price;
        }

        $rate = [
            'id'       => $this->id,
            'label'    => $this->title,
            'cost'     => $cost,
            'calc_tax' => 'per_item',
        ];
        $this->add_rate($rate);
    }

    public static function register()
    {
        add_filter('woocommerce_shipping_methods', [__CLASS__, 'add_method']);
    }

    public static function add_method($methods)
    {
        $currentUser = wp_get_current_user();
        if (SNAPPBOX_SANDBOX == 'yes') {
            if (in_array('administrator', $currentUser->roles)) {
                $methods['snappbox_shipping_method'] = __CLASS__;
            }
        } else {
            $methods['snappbox_shipping_method'] = __CLASS__;
        }

        return $methods;
    }

    public function add_modal_box()
    {
        require_once(SNAPPBOX_DIR . 'includes/schedule-modal.php'); ?>
        <?php
        wp_enqueue_script('schedule-scripts', trailingslashit(SNAPPBOX_URL) . 'assets/js/scripts.js', [], null, true); ?>
        <div id="snappbox-setup-modal" class="snappbox-modal">
            <div class="snappbox-modal-content" id="guide">
                <span class="snappbox-close">&times;</span>
                <div class="snappbox-slide active">
                    <h2><?php esc_html_e('Enable and Disable!', 'sb-delivery'); ?></h2>
                    <p><?php esc_html_e('You can enable and disable the method here', 'sb-delivery'); ?></p>
                    <img src="<?php echo esc_html(SNAPPBOX_URL); ?>/assets/screens/1.png" />
                    <button class="snappbox-next button colorful-button"><?php esc_html_e('Next', 'sb-delivery'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php esc_html_e('Step 1: Enter Your API Key', 'sb-delivery'); ?></h2>
                    <p><?php esc_html_e('Put your API key in this field. you can aquire this API key by contacting SnappBox team', 'sb-delivery'); ?></p>
                    <img src="<?php echo esc_html(SNAPPBOX_URL); ?>/assets/screens/2.png" />
                    <button class="snappbox-next button colorful-button"><?php esc_html_e('Next', 'sb-delivery'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php esc_html_e('Step 2: Set Your Location', 'sb-delivery'); ?></h2>
                    <p><?php esc_html_e('Drag the map marker to your store’s location.', 'sb-delivery'); ?></p>
                    <img src="<?php echo esc_html(SNAPPBOX_URL); ?>/assets/screens/5.png" />
                    <button class="snappbox-next button colorful-button"><?php esc_html_e('Next', 'sb-delivery'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php esc_html_e('Step 3: Set Stores data', 'sb-delivery'); ?></h2>
                    <p><?php esc_html_e('Set your stores name and your Mobile Number.', 'sb-delivery'); ?></p>
                    <img src="<?php echo esc_html(SNAPPBOX_URL); ?>/assets/screens/7.png" />
                    <button class="snappbox-next button colorful-button"><?php esc_html_e('Next', 'sb-delivery'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php esc_html_e('Step 4: Set Stores city', 'sb-delivery'); ?></h2>
                    <p><?php esc_html_e('Set the city that your store can send products by SnappBox.', 'sb-delivery'); ?></p>
                    <img src="<?php echo esc_html(SNAPPBOX_URL); ?>/assets/screens/4.png" />
                    <button class="snappbox-next button colorful-button"><?php esc_html_e('Next', 'sb-delivery'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php esc_html_e('Final Step: Save Settings', 'sb-delivery'); ?></h2>
                    <div class="holder">
                        <p><?php esc_html_e('Scroll down and click "Save changes" to activate SnappBox.', 'sb-delivery'); ?></p>
                        <img src="<?php echo esc_html(SNAPPBOX_URL); ?>/assets/screens/6.png" />
                    </div>
                    <button class="snappbox-close button colorful-button"><?php esc_html_e('Got it!', 'sb-delivery'); ?></button>
                </div>
            </div>
            <?php
            $modal = new SnappBoxScheduleModal();
            $modal->render_modal_html(); ?>
        </div>
<?php
    }
}
