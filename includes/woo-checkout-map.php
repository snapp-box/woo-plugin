<?php


namespace Snappbox;

if ( ! defined('ABSPATH') ) {
    exit;
}

require_once( SNAPPBOX_DIR . 'includes/api/cities-class.php' );
require_once( SNAPPBOX_DIR . 'includes/api/create-order-class.php' );

class SnappBoxCheckout
{
    const NONCE_ACTION = 'snappbox_geo_meta';
    const NONCE_FIELD  = 'snappbox_geo_nonce';

    public function __construct()
    {
        \add_action('wp_enqueue_scripts', [$this, 'snappb_enqueue_map_scripts']);

        \add_action('woocommerce_before_checkout_billing_form', [$this, 'snappb_display_osm_map']);
        \add_action('woocommerce_checkout_update_order_meta',   [$this, 'snappb_save_customer_location']);
        \add_action('woocommerce_checkout_process',             [$this, 'snappb_validate_customer_location']);
        \add_shortcode('snappbox_checkout_map',                 [$this, 'snappb_display_osm_map']);

        \add_action('woocommerce_review_order_after_shipping',  [$this, 'snappb_render_snappbox_dates_row']);
        \add_action('wp_footer',                                [$this, 'snappb_add_checkout_scripts']);
        \add_action('woocommerce_checkout_create_order',        [$this, 'snappb_save_order_meta'], 10, 2);
        \add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'snappb_display_order_meta']);
        \add_action('woocommerce_order_details_after_order_table',         [$this, 'snappb_display_order_meta'], 20, 1);
    }

    public function snappb_enqueue_map_scripts()
    {
        \wp_register_style(
            'maplibre',
            \trailingslashit(SNAPPBOX_URL) . 'assets/css/leaflet.css',
            [],
            '1.9.4'
        );
        \wp_register_script(
            'maplibre',
            \trailingslashit(SNAPPBOX_URL) . 'assets/js/leaflet.js',
            [],
            '1.9.4',
            true
        );

        \wp_register_style(
            'snappbox-checkout',
            \trailingslashit(SNAPPBOX_URL) . 'assets/css/snappbox-checkout.css',
            ['maplibre'],
            '1.0.0'
        );

        \wp_register_script(
            'snappbox-map',
            \trailingslashit(SNAPPBOX_URL) . 'assets/js/snappbox-map.js',
            ['maplibre'],
            '1.0.0',
            true
        );

        \wp_register_script(
            'snappbox-checkout',
            \trailingslashit(SNAPPBOX_URL) . 'assets/js/snappbox-checkout.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }

    public function snappb_display_osm_map()
    {
        $settings_serialized = \get_option('woocommerce_snappbox_shipping_method_settings');
        $settings = \maybe_unserialize($settings_serialized);
        if ( empty($settings['enabled']) || $settings['enabled'] !== 'yes' ) return;
        if ( empty($settings['snappbox_latitude']) || empty($settings['snappbox_longitude']) ) return;
        $mapTitle = !empty($settings['map_title']) ? $settings['map_title'] : '';


        ?>
        <div id="snappbox-map-section" style="display:none;">
            <h3><?php \esc_html_e('Select your location', 'snappbox'); ?></h3>
            <?php if(!empty($mapTitle)) {?>
                <h3><?php esc_html($mapTitle);?></h3>
            <?php }?>
            <div id="osm-map" style="height:400px; margin-bottom:12px; z-index:1; position:relative;">
                <button id="center-pin" type="button" aria-label="<?php \esc_attr_e('Set this location', 'snappbox'); ?>"></button>
            </div>

            <input type="hidden" id="customer_latitude"  name="customer_latitude" />
            <input type="hidden" id="customer_longitude" name="customer_longitude" />
            <input type="hidden" id="customer_city"      name="customer_city" />
            <input type="hidden" id="customer_address"   name="customer_address" />
            <input type="hidden" id="customer_postcode"  name="customer_postcode" />
            <input type="hidden" id="customer_state"     name="customer_state" />
            <input type="hidden" id="customer_country"   name="customer_country" />

            <?php \wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
        </div>
        <?php
    }

  
    private function snappb_get_selected_shipping_methods_from_post(): array
    {
        $methods = [];

        if (
            isset($_POST['woocommerce-process-checkout-nonce']) &&
            \wp_verify_nonce(
                \sanitize_text_field(\wp_unslash($_POST['woocommerce-process-checkout-nonce'])),
                'woocommerce-process_checkout'
            )
        ) {
            if ( isset($_POST['shipping_method']) ) {
                $raw = \sanitize_text_field(wp_unslash($_POST['shipping_method']));
                if ( ! \is_array($raw) ) {
                    $raw = [$raw];
                }
                $methods = \wc_clean($raw);
                $methods = \array_values(\array_filter(\array_map(
                    static function ($v) {
                        return \is_scalar($v) ? \sanitize_text_field((string) $v) : '';
                    },
                    $methods
                )));
            }
        }

        if ( empty($methods) && function_exists('WC') && null !== \WC()->session ) {
            $chosen = \WC()->session->get('chosen_shipping_methods', []);
            if ( ! \is_array($chosen) ) {
                $chosen = [$chosen];
            }
            $methods = \array_values(\array_filter(\array_map(
                static function ($v) {
                    return \is_scalar($v) ? \sanitize_text_field((string) $v) : '';
                },
                $chosen
            )));
        }

        return $methods;
    }

    public function snappb_save_customer_location($order_id)
    {
        $selected_methods = $this->snappb_get_selected_shipping_methods_from_post();
        $snapp_selected = false;
        foreach ( $selected_methods as $m ) {
            if ( \strpos((string) $m, 'snappbox_shipping_method') === 0 ) { $snapp_selected = true; break; }
        }
        if ( ! $snapp_selected ) return;

        if (
            empty($_POST[self::NONCE_FIELD]) ||
            ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            return;
        }

        if ( isset($_POST['customer_latitude'], $_POST['customer_longitude']) ) {
            $latitude        = (float) \sanitize_text_field(\wp_unslash($_POST['customer_latitude']));
            $longitude       = (float) \sanitize_text_field(\wp_unslash($_POST['customer_longitude']));
            $customerCity    = isset($_POST['customer_city'])     ? \sanitize_text_field(\wp_unslash($_POST['customer_city']))     : '';
            $customerAddress = isset($_POST['customer_address'])  ? \sanitize_text_field(\wp_unslash($_POST['customer_address']))  : '';
            $customerPost    = isset($_POST['customer_postcode']) ? \sanitize_text_field(\wp_unslash($_POST['customer_postcode'])) : '';
            $customerState   = isset($_POST['customer_state'])    ? \sanitize_text_field(\wp_unslash($_POST['customer_state']))    : '';
            $customerCountry = isset($_POST['customer_country'])  ? \sanitize_text_field(\wp_unslash($_POST['customer_country']))  : '';

            if ( $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180 ) {
                \update_post_meta($order_id, '_customer_latitude',  $latitude);
                \update_post_meta($order_id, '_customer_longitude', $longitude);
            }

            \update_post_meta($order_id, 'customer_city',     $customerCity);
            \update_post_meta($order_id, 'customer_state',    $customerState);
            \update_post_meta($order_id, 'customer_country',  $customerCountry);
            \update_post_meta($order_id, 'customer_postcode', $customerPost);
            \update_post_meta($order_id, 'customer_address',  $customerAddress);
        }
    }

    public function snappb_validate_customer_location()
    {
        $selected_methods = $this->snappb_get_selected_shipping_methods_from_post();
        $snapp_selected = false;
        foreach ( $selected_methods as $m ) {
            if ( \strpos((string) $m, 'snappbox_shipping_method') === 0 ) { $snapp_selected = true; break; }
        }
        if ( ! $snapp_selected ) return;

        if (
            empty($_POST[self::NONCE_FIELD]) ||
            ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            \wc_add_notice(\__('Security check failed. Please refresh the page and try again.', 'snappbox'), 'error');
            return;
        }

        if ( empty($_POST['customer_latitude']) || empty($_POST['customer_longitude']) ) {
            \wc_add_notice(\__('Please select your location on the map.', 'snappbox'), 'error');
        }
    }

    public function snappb_render_snappbox_dates_row()
    {
        $schedule = \get_option('snappbox_schedule', []);
        if ( empty($schedule) || ! \is_array($schedule) ) return;

        echo '<tr class="snappbox-delivery-tr" style="display:none;">';
        echo '  <td colspan="2" style="padding:0;border:0;">';
        echo '    <div class="snappbox-checkout-box" style="margin:10px 0 0; padding:10px; border:1px solid #ddd;">';
        echo '      <p><strong>' . \esc_html__('Select delivery day & time:', 'snappbox') . '</strong></p>';
        echo '      <input type="hidden" name="snappbox_day" class="snappbox-day-hidden" />';
        echo '      <div class="snappbox_day_grid_wrap"><div class="snappbox-day-grid" id="snappbox_day_grid"></div></div>';
        echo '      <select name="snappbox_time" class="snappbox-time" id="snappbox_time" style="margin-top:8px; width:100%;"></select>';
        echo '    </div>';
        echo '  </td>';
        echo '</tr>';
    }

    public function snappb_add_checkout_scripts()
    {
        if ( ! \is_checkout() ) return;

        \wp_enqueue_style('maplibre');
        \wp_enqueue_style('snappbox-checkout');

        \wp_enqueue_script('maplibre');
        \wp_enqueue_script('snappbox-map');
        \wp_enqueue_script('snappbox-checkout');

        $settings_serialized = \get_option('woocommerce_snappbox_shipping_method_settings');
        $settings = \maybe_unserialize($settings_serialized);
        $defaultLat = ! empty($settings['snappbox_latitude'])  ? (float) $settings['snappbox_latitude']  : 0.0;
        $defaultLng = ! empty($settings['snappbox_longitude']) ? (float) $settings['snappbox_longitude'] : 0.0;
        $autoFill   = ! empty($settings['autofill']) ? (string) $settings['autofill'] : '';

        \wp_localize_script('snappbox-map', 'SNAPPBOX_MAP', [
            'defaultLat' => $defaultLat,
            'defaultLng' => $defaultLng,
            'autoFill'   => $autoFill,
            'styleUrl'   => 'https://tile.snappmaps.ir/styles/snapp-style-v4.1.2/style.json',
            'rtlPlugin'  => \trailingslashit(SNAPPBOX_URL) . 'assets/js/mapbox-gl-rtl-text.js',
            'reverseUrl' => 'https://api.teh-1.snappmaps.ir/reverse/v1',
            'reverseHeaders' => [
                'Accept'        => 'application/json',
                'X-Smapp-Key'   => 'aa22e8eef7d348d32f492d8a0c755f4d',
                'Authorization' => 'pk.eyJ1IjoibWVpaCIsImEiOiJjamY2aTJxenIxank3MzNsbmY0anhwaG9mIn0.egsUz_uibSftB0sjSWb9qw',
            ],
            'nominatimUrl' => 'https://nominatim.openstreetmap.org/reverse',
        ]);

        $raw_schedule = \get_option('snappbox_schedule', []);
        $weekly = (\is_array($raw_schedule) && ! empty($raw_schedule)) ? $this->snappb_sb_normalize_schedule_to_w($raw_schedule) : [];
        $candidates    = [];
        $times_by_date = [];

        if ( ! empty($weekly) ) {
            $tz        = \function_exists('wp_timezone') ? \wp_timezone() : new \DateTimeZone(\wp_timezone_string());
            $now_ts    = \current_time('timestamp');
            $lookahead = 60;

            for ( $i = 0; $i < $lookahead && \count($candidates) < 10; $i++ ) {
                $ts = $now_ts + ($i * DAY_IN_SECONDS);
                $dt = new \DateTime('@' . $ts);
                $dt->setTimezone($tz);

                $w = (int) $dt->format('w');
                if ( empty($weekly[$w]) ) continue;

                $slots = [];
                foreach ( (array) $weekly[$w] as $slot ) {
                    if ( \is_array($slot) && isset($slot['start'], $slot['end']) ) {
                        $slots[] = \trim($slot['start']) . ' - ' . \trim($slot['end']);
                    } elseif ( \is_string($slot) && $slot !== '' ) {
                        $slots[] = \trim($slot);
                    }
                }
                $slots = \array_values(\array_unique(\array_filter($slots)));
                if ( empty($slots) ) continue;

                $date_iso = $dt->format('Y-m-d');
                $candidates[] = [
                    'date_iso' => $date_iso,
                    'label'    => [
                        'title' => \wp_date('l', $ts),
                        'd'     => \wp_date('j', $ts),
                        'month' => \wp_date('F', $ts),
                    ],
                ];
                $times_by_date[$date_iso] = $slots;
            }
        }

        \wp_localize_script('snappbox-checkout', 'SNAPPB_DELIVERY_DATES', [
            'candidates'  => $candidates,
            'timesByDate' => $times_by_date,
        ]);
    }

    private function snappb_sb_normalize_schedule_to_w(array $schedule): array
    {
        $name_to_w = [
            'sunday'    => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
            'thursday'  => 4, 'friday' => 5, 'saturday' => 6,
        ];
        $out = [];
        foreach ( $schedule as $key => $slots ) {
            $w = null;
            if ( \is_numeric($key) ) {
                $w = \max(0, \min(6, (int) $key));
            } else {
                $k = \strtolower(\trim((string) $key));
                if ( isset($name_to_w[$k]) ) {
                    $w = $name_to_w[$k];
                }
            }
            if ( $w === null ) continue;
            if ( ! isset($out[$w]) ) $out[$w] = [];
            $out[$w] = \array_merge($out[$w], (array) $slots);
        }
        return $out;
    }

    public function snappb_save_order_meta($order, $data)
    {
        if (
            empty($_POST[self::NONCE_FIELD]) ||
            ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            return;
        }
        if ( ! empty($_POST['snappbox_day']) ) {
            $order->update_meta_data('_snappbox_day', \sanitize_text_field(\wp_unslash($_POST['snappbox_day'])));
        }
        if ( ! empty($_POST['snappbox_time']) ) {
            $order->update_meta_data('_snappbox_time', \sanitize_text_field(\wp_unslash($_POST['snappbox_time'])));
        }
    }

    public function snappb_display_order_meta($order)
    {
        $dateIso = $order->get_meta('_snappbox_day');
        $time    = $order->get_meta('_snappbox_time');
        if ( $dateIso || $time ) {
            $ts = $dateIso ? \strtotime($dateIso . ' 12:00:00') : false;
            $dateLabel = $ts ? \wp_date('l j F Y', $ts) : $dateIso;
            echo '<p><strong>' . \esc_html__('SnappBox Delivery:', 'snappbox') . '</strong><br>';
            echo \esc_html(\trim($dateLabel . ' - ' . $time, ' -')) . '</p>';
        }
    }
}

