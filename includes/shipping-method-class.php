<?php

namespace Snappbox;

if (! \defined('ABSPATH')) {
    exit;
}

if (! \class_exists('WooCommerce')) {
    return;
}

require_once(SNAPPBOX_DIR . 'includes/api/cities-class.php');
require_once(SNAPPBOX_DIR . 'includes/api/wallet-balance-class.php');
require_once(SNAPPBOX_DIR . 'includes/convert-woo-cities-to-snappbox.php');

class SnappBoxShippingMethod extends \WC_Shipping_Method
{
    const API_NONCE_ACTION = 'snappbox_save_api_key';
    const API_NONCE_FIELD  = 'snappbox_api_nonce';

    public function __construct($instance_id = 0)
    {
        $this->id                 = 'snappbox_shipping_method';
        $this->instance_id        = \absint($instance_id);
        $this->method_title       = \__('SnappBox Shipping Method', 'snappbox');
        $this->method_description = \__('A SnappBox shipping method with dynamic pricing.', 'snappbox');
        $this->supports           = ['shipping-zones', 'instance-settings', 'settings'];

        $this->snappb_init();
    }

    public function snappb_init()
    {
        $this->snappb_init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled');
        $this->title   = $this->get_option('title');

        \add_action('admin_enqueue_scripts', [$this, 'snappb_enqueue_leafles_scripts'], 10, 1);
        \add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'snappb_process_admin_options']);
        \add_action('woocommerce_checkout_create_order', [$this, 'snappb_order_register'], 10, 2);
        \add_filter('woocommerce_checkout_fields', [$this, 'snappb_customize_checkout_fields']);
        \add_action('admin_notices', [$this, 'snappb_admin_alert']);
    }

    public function snappb_enqueue_leafles_scripts()
    {
        \wp_enqueue_style(
            'snappbox-style',
            \trailingslashit(SNAPPBOX_URL) . 'assets/css/style.css',
            [],
            \filemtime(\trailingslashit(SNAPPBOX_DIR) . 'assets/css/style.css')
        );
    }

    public function snappb_process_admin_options()
    {
        parent::process_admin_options();

        $posted_key = null;
        if (isset($_POST['snappbox_api'])) {
            $posted_key = \sanitize_text_field(\wp_unslash($_POST['snappbox_api']));
        } elseif (isset($_POST['woocommerce_snappbox_shipping_method_snappbox_api'])) {
            $posted_key = \sanitize_text_field(\wp_unslash($_POST['woocommerce_snappbox_shipping_method_snappbox_api']));
        }

        if ($posted_key !== null) {
            $nonce_ok = isset($_POST[self::API_NONCE_FIELD]) &&
                \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST[self::API_NONCE_FIELD])), self::API_NONCE_ACTION);

            if (! $nonce_ok) {
                if (\class_exists('\WC_Admin_Settings')) {
                    \WC_Admin_Settings::add_error(\__('Security check failed. API key was not saved.', 'snappbox'));
                } else {
                    \add_settings_error('woocommerce', 'snappbox_api_nonce', \__('Security check failed. API key was not saved.', 'snappbox'), 'error');
                }
                return;
            }

            $settings = \maybe_unserialize(\get_option('woocommerce_snappbox_shipping_method_settings'));
            if (! \is_array($settings)) {
                $settings = [];
            }
            $settings['snappbox_api'] = $posted_key;
            \update_option('woocommerce_snappbox_shipping_method_settings', $settings);
            $this->settings = $settings;

            if (\class_exists('\WC_Admin_Settings')) {
                \WC_Admin_Settings::add_message(\__('API key saved.', 'snappbox'));
            }
        }
    }

    public function snappb_customize_checkout_fields($fields)
    {
        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['label']       = \__('Mobile Phone', 'snappbox');
            $fields['billing']['billing_phone']['placeholder'] = '09121234567';
            $fields['billing']['billing_phone']['required']    = true;
        }
        return $fields;
    }

    public function snappb_order_register($order, $data)
    {
        global $woocommerce;

        $chosen_shipping_methods = \WC()->session->get('chosen_shipping_methods');
        $chosen_shipping_method  = \is_array($chosen_shipping_methods) ? ($chosen_shipping_methods[0] ?? '') : '';

        $cart_subtotal = \WC()->cart ? (float) \WC()->cart->get_subtotal() : 0.0;

        $free_delivery = $this->get_option('free_delivery');

        if (! empty($free_delivery) && (float) $free_delivery < $cart_subtotal) {
            $order->update_meta_data('_free_delivery', \__('SnappBox cost is free', 'snappbox'));
        }

        $shipping_city     = isset($data['shipping_state']) ? $data['shipping_state'] : '';
        $shipping_cityName = isset($data['shipping_city']) ? $data['shipping_city'] : '';

        $settings_serialized = \get_option('woocommerce_snappbox_shipping_method_settings');
        $settings            = \maybe_unserialize($settings_serialized);
        $allCities           = new \Snappbox\SnappBoxCityHelper();
        $stored_cities       = isset($settings['snappbox_cities']) ? (array) $settings['snappbox_cities'] : [];

        if ($chosen_shipping_method === 'snappbox_shipping_method') {
            $nonce_field  = 'snappbox_geo_nonce';
            $nonce_action = 'snappbox_geo_meta';
            if (empty($_POST[$nonce_field]) || ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST[$nonce_field])), $nonce_action)) {
                throw new \Exception(\esc_html__('Security check failed. Please refresh the page and try again.', 'snappbox'));
            }
        }

        $city = isset($_POST['customer_city']) ? \sanitize_text_field(\wp_unslash($_POST['customer_city'])) : '';

        if ($chosen_shipping_method === 'snappbox_shipping_method') {
            if (\in_array(\strtolower($city), \array_map('strtolower', $stored_cities), true)) {
                $order->add_order_note('Order registered with SnappBox in ' . $shipping_city);
                $order->update_meta_data('_snappbox_city', $shipping_city);
            } else {
                throw new \Exception(\esc_html__('SnappBox is not available in your city', 'snappbox'));
            }
        }
    }

    public function snappb_init_form_fields()
    {
        $latitude            = \get_option('snappbox_latitude', '35.8037761');
        $longitude           = \get_option('snappbox_longitude', '51.4152466');
        $settings_serialized = \get_option('woocommerce_snappbox_shipping_method_settings');
        $settings            = \maybe_unserialize($settings_serialized);

        $stored_cities  = isset($settings['snappbox_cities']) ? (array) $settings['snappbox_cities'] : [];
        $snappBoxAPIKey = $this->get_option('snappbox_api');

        $transient_key = 'snappbox_cities_' . \md5($latitude . '_' . $longitude);
        $cities        = \get_transient($transient_key);

        if ($cities === false) {
            $citiesObj = new \Snappbox\Api\SnappBoxCities();
            $cities    = $citiesObj->snappb_get_delivery_category($latitude, $longitude);

            if (! empty($cities) && isset($cities->cities)) {
                \set_transient($transient_key, $cities, DAY_IN_SECONDS);
            }
        }

        $city_options = [];
        if (! empty($cities->cities) && \is_array($cities->cities)) {
            $filtered_cities = \array_filter($cities->cities, function ($city) {
                return ! empty($city->cityName);
            });

            $mapped_cities = \array_map(function ($city) {
                if ($city->cityKey == 'gilan') {
                    $city->cityKey = 'rasht';
                }
                return $city;
            }, $filtered_cities);

            $city_options = \array_column($mapped_cities, 'cityName', 'cityKey');
        }

        $this->form_fields = [
            'enabled' => [
                'title'       => \__('Enable', 'snappbox'),
                'type'        => 'checkbox',
                'description' => \__('Enable this shipping method', 'snappbox'),
                'default'     => 'yes',
            ],
            'sandbox' => [
                'title'       => \__('Enable Test Mode', 'snappbox'),
                'type'        => 'checkbox',
                'description' => \__('Enable test mode for this plugin', 'snappbox'),
                'default'     => 'no',
            ],
            'title' => [
                'title'       => \__('Title', 'snappbox'),
                'type'        => 'text',
                'description' => \__('Title to display during checkout', 'snappbox'),
                'default'     => \__('SnappBox Shipping', 'snappbox'),
            ],
            'fixed_price' => [
                'title'       => \__('Fixed Price', 'snappbox'),
                'type'        => 'number',
                'description' => \__('Leave it empty for canceling fixed price', 'snappbox') . '. ' . \__('You must enter a price in ', 'snappbox') . ' : ' . \get_woocommerce_currency_symbol(),
            ],
            'free_delivery' => [
                'title'       => \__('Free Delivery', 'snappbox'),
                'type'        => 'number',
                'description' => \__('Minimum basket price for free delivery', 'snappbox') . '. ' . \__('You must enter a price in ', 'snappbox') . ' : ' . \get_woocommerce_currency_symbol(),
            ],
            'base_cost' => [
                'title'       => \__('Base Shipping Cost', 'snappbox'),
                'type'        => 'number',
                'description' => \__('Base shipping cost for this method', 'snappbox'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'map_title' => [
                'title'       => \__('Map Title', 'snappbox'),
                'type'        => 'text',
                'description' => \__('Title to display under the map in checkout page', 'snappbox'),
                'default'     => \__('Please set your location here', 'snappbox'),
            ],
            // 'cost_per_kg' => [
            //     'title'       => __('Cost per KG', 'snappbox'),
            //     'type'        => 'number',
            //     'description' => __('Shipping cost per kilogram', 'snappbox'),
            //     'default'     => '',
            //     'desc_tip'    => true,
            // ],

            'snappbox_latitude' => [
                'title'             => \__('Latitude', 'snappbox'),
                'type'              => 'text',
                'default'           => $latitude,
                'custom_attributes' => ['readonly' => 'readonly']
            ],
            'snappbox_longitude' => [
                'title'             => \__('Longitude', 'snappbox'),
                'type'              => 'text',
                'default'           => $longitude,
                'custom_attributes' => ['readonly' => 'readonly']
            ],
            'snappbox_store_phone' => [
                'title'   => \__('Phone Number', 'snappbox'),
                'type'    => 'text',
                'default' => \get_option('snappbox_store_phone', '')
            ],
            'snappbox_store_name' => [
                'title'   => \__('Store Name', 'snappbox'),
                'type'    => 'text',
                'default' => \get_option('snappbox_store_name', '')
            ],
            'ondelivery' => [
                'title'       => \__('Enable payment on delivery', 'snappbox'),
                'type'        => 'checkbox',
                'description' => \__('Pay SnappBox payment on delivery', 'snappbox'),
                'default'     => 'no',
            ],
            'autofill' => [
                'title'       => \__('Enable Address Autofill', 'snappbox'),
                'type'        => 'checkbox',
                'description' => \__('This option enables the address to be autofilled from SmappMap', 'snappbox'),
                'default'     => 'no',
            ],

            'snappbox_cities' => [
                'title'       => \__('Cities', 'snappbox'),
                'type'        => 'multiselect',
                'options'     => $city_options,
                'description' => \__('This Item will show after token insertion', 'snappbox'),
                'default'     => $stored_cities,
            ]
        ];
    }

    public function admin_options()
    {
        $walletObj       = new \Snappbox\Api\SnappBoxWalletBalance();
        $walletObjResult = $walletObj->snappb_check_balance();

        echo '<div class="snappbox-panel right">';
        parent::admin_options();
        echo '</div>';

        $lat = (float) $this->get_option('snappbox_latitude', '35.8037761');
        $lng = (float) $this->get_option('snappbox_longitude', '51.4152466');

        $this->enqueue_maplibre_assets();

        ?>
        <div style="margin-bottom: 5px; float:left;">
            <a href="#" id="snappbox-launch-modal" class="button colorful-button button-secondary">
                <?php echo \esc_html__('Show Setup Guide', 'snappbox'); ?>
            </a>
            <a href="#" id="snappbox-launch-modal-guide" class="button colorful-button button-secondary">
                <?php echo \esc_html__('Show Dates', 'snappbox'); ?>
            </a>
        </div>

        <?php $this->snappb_token_integeration(); ?>
        <?php $this->snappb_wallet_information(); ?>

        <div class="snappbox-panel">
            <h4><?php \esc_html_e('Set Store Location', 'snappbox'); ?></h4>
            <p><?php \esc_html_e('Please move the pin', 'snappbox'); ?></p>

            <div id="map" style="height:400px; position:relative;">
                <button id="center-pin" type="button" aria-label="<?php \esc_attr_e('Set this location', 'snappbox'); ?>"></button>
            </div>
            <?php $this->enqueue_maplibre_inline_script($lat, $lng); ?>
        </div>

        <?php $this->snappb_add_modal_box(); ?>
        <?php
    }

    protected function enqueue_maplibre_assets()
    {
        if (! \wp_script_is('maplibre', 'registered')) {
            \wp_register_script(
                'maplibre',
                \trailingslashit(SNAPPBOX_URL) . 'assets/js/leaflet.js',
                [],
                null,
                true
            );
        }
        \wp_enqueue_script('maplibre');

        if (! \wp_style_is('maplibre', 'registered')) {
            \wp_register_style(
                'maplibre',
                \trailingslashit(SNAPPBOX_URL) . 'assets/js/leaflet.css',
                [],
                null
            );
        }
        \wp_enqueue_style('maplibre');
    }

    protected function enqueue_maplibre_inline_script($lat, $lng)
    {
        $defaultLat = \wp_json_encode((float) $lat);
        $defaultLng = \wp_json_encode((float) $lng);

        $rtl_plugin_url = \esc_url(\trailingslashit(SNAPPBOX_URL) . 'assets/js/mapbox-gl-rtl-text.js');
        $rtl_plugin_url_js = \wp_json_encode($rtl_plugin_url);

        $inline_js  = 'document.addEventListener("DOMContentLoaded", function() {';
        $inline_js .= 'if (typeof maplibregl === "undefined") { console.error("MapLibre not loaded"); return; }';
        $inline_js .= 'const defaultLat = ' . $defaultLat . ';';
        $inline_js .= 'const defaultLng = ' . $defaultLng . ';';
        $inline_js .= 'const map = new maplibregl.Map({';
        $inline_js .= 'container:"map",';
        $inline_js .= 'style:"https://tile.snappmaps.ir/styles/snapp-style-v4.1.2/style.json",';
        $inline_js .= 'center:[defaultLng, defaultLat],';
        $inline_js .= 'zoom:16,';
        $inline_js .= 'attributionControl:true';
        $inline_js .= '});';
        $inline_js .= 'maplibregl.setRTLTextPlugin(' . $rtl_plugin_url_js . ', null, true);';
        $inline_js .= 'map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), "top-right");';
        $inline_js .= 'const marker = new maplibregl.Marker({ draggable: true }).setLngLat([defaultLng, defaultLat]).addTo(map);';
        $inline_js .= 'function updateInputs(lat, lng){';
        $inline_js .= 'var latInput=document.querySelector(\'[name="woocommerce_snappbox_shipping_method_snappbox_latitude"]\');';
        $inline_js .= 'var lngInput=document.querySelector(\'[name="woocommerce_snappbox_shipping_method_snappbox_longitude"]\');';
        $inline_js .= 'if(latInput) latInput.value=Number(lat).toFixed(9);';
        $inline_js .= 'if(lngInput) lngInput.value=Number(lng).toFixed(9);';
        $inline_js .= '}';
        $inline_js .= 'function onSet(lat,lng){ marker.setLngLat([lng,lat]); updateInputs(lat,lng);}';
        $inline_js .= 'marker.on("dragend", function(){ var p=marker.getLngLat(); updateInputs(p.lat,p.lng); });';
        $inline_js .= 'map.on("click", function(e){ onSet(e.lngLat.lat, e.lngLat.lng); });';
        $inline_js .= 'var centerPinBtn=document.getElementById("center-pin");';
        $inline_js .= 'if(centerPinBtn){ centerPinBtn.addEventListener("click", function(){ var c=map.getCenter(); onSet(c.lat,c.lng); }); }';
        $inline_js .= 'updateInputs(defaultLat, defaultLng);';
        $inline_js .= '});';

        \wp_add_inline_script('maplibre', $inline_js);
    }

    public function snappb_admin_alert($walletObjResult = null)
    {
        static $notice_output = false;
        if ($notice_output) return;

        if (! $walletObjResult) {
            $walletObj       = new \Snappbox\Api\SnappBoxWalletBalance();
            $walletObjResult = $walletObj->snappb_check_balance();
        }

        if (empty($walletObjResult)) return;

        $currentBalance = isset($walletObjResult['response']['currentBalance'])
            ? (float) $walletObjResult['response']['currentBalance']
            : 0.0;

        $balanceDefaultResponse = \wp_remote_get('https://assets.snapp-box.com/static/plugin/woo-config.json');
        if (\is_wp_error($balanceDefaultResponse)) return;

        $balanceDefault = \wp_remote_retrieve_body($balanceDefaultResponse);
        $config         = \json_decode($balanceDefault);
        if (! $config || ! isset($config->minWalletCredit)) return;

        $minCredit = (float) $config->minWalletCredit;

        $was_low = (bool) \get_option('snappbox_wallet_was_low', false);
        $is_low  = ($currentBalance < $minCredit);

        if ($is_low && ! $was_low) {
            $notice_output = true;
            ?>
            <div class="notice notice-error is-dismissible snappbox-low-balance">
                <p>
                    <?php \esc_html_e('Your wallet balance is too low. Please contact Snappbox', 'snappbox'); ?>
                    <a href="https://app.snapp-box.com/top-up" target="_blank" rel="noopener noreferrer">
                        <?php \esc_html_e('Learn more.', 'snappbox'); ?>
                    </a>
                </p>
            </div>
            <?php
            \update_option('snappbox_wallet_was_low', true, false);
            return;
        }

        if (! $is_low && $was_low) {
            \update_option('snappbox_wallet_was_low', false, false);
        }
    }

    public function snappb_wallet_information()
    { ?>
        <div class="snappbox-panel">
            <h4><?php \esc_html_e('wallet Information', 'snappbox'); ?></h4>
            <?php
            $walletObj       = new \Snappbox\Api\SnappBoxWalletBalance();
            $walletObjResult = $walletObj->snappb_check_balance();

            if (! empty($walletObjResult) && isset($walletObjResult['response']['currentBalance'])) {
                if (\get_woocommerce_currency() === 'IRT') {
                    $currentBalance = $this->snappb_rial_to_toman($walletObjResult['response']['currentBalance']);
                } else {
                    $currentBalance = $walletObjResult['response']['currentBalance'];
                }
                echo '<p>' . \esc_html__('Your current balance is: ', 'snappbox') . \esc_html($currentBalance) . ' ' . \esc_html(\get_woocommerce_currency_symbol()) . '</p>';
            } else {
                echo \esc_html__('Unable to fetch wallet balance.', 'snappbox');
            }
            ?>
        </div>
    <?php
    }

    public function snappb_token_integeration()
    {
        $api_key = $this->get_option('snappbox_api');
        ?>
        <div class="snappbox-panel">
            <h4><?php \esc_html_e('API Key', 'snappbox'); ?></h4>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="snappbox_api"><?php \esc_html_e('Enter your SnappBox API token', 'snappbox'); ?></label>
                    </th>
                    <td width="35%">
                        <input type="text"
                            id="snappbox_api"
                            name="snappbox_api"
                            class="regular-text"
                            value="<?php echo \esc_attr($api_key); ?>"
                            placeholder="<?php \esc_attr_e('Paste your API key…', 'snappbox'); ?>" />
                        <?php
                        \wp_nonce_field(self::API_NONCE_ACTION, self::API_NONCE_FIELD);
                        ?>
                    </td>
                    <td>
                        <a href="https://snapp-box.com/connect" class="snappbox-token" target="_blank" rel="noopener noreferrer">درخواست توکن</a>
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

    public function snappb_rial_to_toman($amount)
    {
        return $amount / 10;
    }

    public function calculate_shipping($package = [])
    {
        $subtotal      = \WC()->cart ? (float) \WC()->cart->get_subtotal() : 0.0;

        $base_cost     = (float) $this->get_option('base_cost');
        $cost_per_kg   = $this->get_option('cost_per_kg'); 
        $fixed_price   = $this->get_option('fixed_price');
        $free_delivery = $this->get_option('free_delivery');

        if (! empty($fixed_price)) {
            if (! empty($free_delivery) && (float) $free_delivery < $subtotal) {
                $cost = 0;
            } elseif (empty($free_delivery) && empty($base_cost) && empty($cost_per_kg)) {
                $cost = 0;
            } else {
                $cost = (float) $fixed_price;
            }
        } else {
            $cost = (float) $base_cost;
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
        \add_filter('woocommerce_shipping_methods', [__CLASS__, 'snappb_add_method']);
    }

    public static function snappb_add_method($methods)
    {
        $currentUser = \wp_get_current_user();
        if (\defined('SNAPPBOX_SANDBOX') && SNAPPBOX_SANDBOX == 'yes') {
            if (\in_array('administrator', (array) $currentUser->roles, true)) {
                $methods['snappbox_shipping_method'] = __CLASS__;
            }
        } else {
            $methods['snappbox_shipping_method'] = __CLASS__;
        }

        return $methods;
    }

    public function snappb_add_modal_box()
    {
        require_once(SNAPPBOX_DIR . 'includes/schedule-modal.php');
        \wp_enqueue_script('schedule-scripts', \trailingslashit(SNAPPBOX_URL) . 'assets/js/scripts.js', [], null, true);
        ?>
        <div id="snappbox-setup-modal" class="snappbox-modal">
            <div class="snappbox-modal-content" id="guide">
                <span class="snappbox-close">&times;</span>
                <div class="snappbox-slide active">
                    <h2><?php \esc_html_e('Enable and Disable!', 'snappbox'); ?></h2>
                    <p><?php \esc_html_e('You can enable and disable the method here', 'snappbox'); ?></p>
                    <img src="<?php echo \esc_html(SNAPPBOX_URL); ?>/assets/screens/1.png" />
                    <button class="snappbox-next button colorful-button"><?php \esc_html_e('Next', 'snappbox'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php \esc_html_e('Step 1: Enter Your API Key', 'snappbox'); ?></h2>
                    <p><?php \esc_html_e('Put your API key in this field. you can aquire this API key by contacting SnappBox team', 'snappbox'); ?></p>
                    <img src="<?php echo \esc_html(SNAPPBOX_URL); ?>/assets/screens/2.png" />
                    <button class="snappbox-next button colorful-button"><?php \esc_html_e('Next', 'snappbox'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php \esc_html_e('Step 2: Set Your Location', 'snappbox'); ?></h2>
                    <p><?php \esc_html_e('Drag the map marker to your store’s location.', 'snappbox'); ?></p>
                    <img src="<?php echo \esc_html(SNAPPBOX_URL); ?>/assets/screens/5.png" />
                    <button class="snappbox-next button colorful-button"><?php \esc_html_e('Next', 'snappbox'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php \esc_html_e('Step 3: Set Stores data', 'snappbox'); ?></h2>
                    <p><?php \esc_html_e('Set your stores name and your Mobile Number.', 'snappbox'); ?></p>
                    <img src="<?php echo \esc_html(SNAPPBOX_URL); ?>/assets/screens/7.png" />
                    <button class="snappbox-next button colorful-button"><?php \esc_html_e('Next', 'snappbox'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php \esc_html_e('Step 4: Set Stores city', 'snappbox'); ?></h2>
                    <p><?php \esc_html_e('Set the city that your store can send products by SnappBox.', 'snappbox'); ?></p>
                    <img src="<?php echo \esc_html(SNAPPBOX_URL); ?>/assets/screens/4.png" />
                    <button class="snappbox-next button colorful-button"><?php \esc_html_e('Next', 'snappbox'); ?></button>
                </div>
                <div class="snappbox-slide">
                    <h2><?php \esc_html_e('Final Step: Save Settings', 'snappbox'); ?></h2>
                    <div class="holder">
                        <p><?php \esc_html_e('Scroll down and click "Save changes" to activate SnappBox.', 'snappbox'); ?></p>
                        <img src="<?php echo \esc_html(SNAPPBOX_URL); ?>/assets/screens/6.png" />
                    </div>
                    <button class="snappbox-close button colorful-button"><?php \esc_html_e('Got it!', 'snappbox'); ?></button>
                </div>
            </div>
            <?php
            $modal = new \Snappbox\SnappBoxScheduleModal();
            $modal->snappb_render_modal_html();
            ?>
        </div>
        <?php
    }
}
