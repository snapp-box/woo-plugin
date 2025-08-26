<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once(SNAPPBOX_DIR . 'includes/cities-class.php');
require_once(SNAPPBOX_DIR . 'includes/create-order-class.php');

class SnappBoxCheckout
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_leaflet_scripts']);
        add_action('woocommerce_before_checkout_billing_form', [$this, 'display_osm_map']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_customer_location']);
        add_action('woocommerce_checkout_process', [$this, 'validate_customer_location']);
        add_shortcode('snappbox_checkout_map', [$this, 'display_osm_map']);

        add_action('woocommerce_review_order_after_shipping', [$this, 'render_snappbox_dates_row']); 
        add_action('wp_footer', [$this, 'add_checkout_scripts']);
        add_action('woocommerce_checkout_create_order', [$this, 'save_order_meta'], 10, 2);
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'display_order_meta']);
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_order_meta'], 20, 1);

    }

    public function enqueue_leaflet_scripts()
    {
        wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], null, true);
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
    }

    public function display_osm_map()
    {
        $settings_serialized = get_option('woocommerce_snappbox_shipping_method_settings');
        $settings = maybe_unserialize($settings_serialized);
        
        if ($settings['enabled'] == 'yes') {
        ?>
            <h3><?php _e('Select your location', 'sb-delivery'); ?></h3>
            <div id="osm-map" style="height: 400px;"></div>
            <input type="hidden" id="customer_latitude" name="customer_latitude" />
            <input type="hidden" id="customer_longitude" name="customer_longitude" />
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var defaultLat = 35.6892;
                    var defaultLng = 51.3890;
                    var map = L.map('osm-map').setView([defaultLat, defaultLng], 12);
                    L.tileLayer('https://raster.snappmaps.ir/styles/snapp-style/{z}/{x}/{y}{r}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(map);
                    var marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
                    marker.on('dragend', function () {
                        var position = marker.getLatLng();
                        document.getElementById('customer_latitude').value = position.lat;
                        document.getElementById('customer_longitude').value = position.lng;
                    });
                    document.getElementById('customer_latitude').value = defaultLat;
                    document.getElementById('customer_longitude').value = defaultLng;
                });
            </script>
        <?php }
    }

    public function save_customer_location($order_id)
    {
        if (isset($_POST['customer_latitude']) && isset($_POST['customer_longitude'])) {
            $latitude = sanitize_text_field($_POST['customer_latitude']);
            $longitude = sanitize_text_field($_POST['customer_longitude']);
            update_post_meta($order_id, '_customer_latitude', $latitude);
            update_post_meta($order_id, '_customer_longitude', $longitude);
        }
    }

    public function validate_customer_location()
    {
        if (empty($_POST['customer_latitude']) || empty($_POST['customer_longitude'])) {
            wc_add_notice(__('Please select your location on the map.'), 'error');
        }
    }

    public function render_snappbox_dates_row() {
        $schedule = get_option('snappbox_schedule', []);
        if (empty($schedule) || !is_array($schedule)) return;
        echo '<tr class="snappbox-delivery-tr" style="display:none;">';
        echo '  <td colspan="2" style="padding:0;border:0;">';
        echo '    <div class="snappbox-checkout-box" style="margin:10px 0 0; padding:10px; border:1px solid #ddd;">';
        echo '      <p><strong>' . esc_html__('Select delivery day & time:', 'sb-delivery') . '</strong></p>';
        echo '      <input type="hidden" name="snappbox_day" class="snappbox-day-hidden" />';
        echo '      <div class="snappbox-day-grid" id="snappbox_day_grid"></div>';
        echo '      <select name="snappbox_time" class="snappbox-time" id="snappbox_time" style="margin-top:8px; width:100%;"></select>';
        echo '    </div>';
        echo '  </td>';
        echo '</tr>';
    }
    
    
    

    public function add_checkout_scripts() {
        if ( ! is_checkout() ) return;
    
        $raw_schedule = get_option('snappbox_schedule', []);
        if (empty($raw_schedule) || !is_array($raw_schedule)) return;
    
        $weekly = $this->sb_normalize_schedule_to_w($raw_schedule);
        if (empty($weekly)) return;
        $candidates    = [];
        $times_by_date = [];
        $tz            = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone( wp_timezone_string() );
        $now_ts        = current_time('timestamp');
        $lookahead     = 60;
    
        for ($i = 0; $i < $lookahead && count($candidates) < 10; $i++) {
            $ts = $now_ts + ($i * DAY_IN_SECONDS);
            $dt = new DateTime('@'.$ts);
            $dt->setTimezone($tz);
    
            $w = (int) $dt->format('w'); 
            if (empty($weekly[$w])) continue;
    
            $slots = [];
            foreach ((array) $weekly[$w] as $slot) {
                if (is_array($slot) && isset($slot['start'], $slot['end'])) {
                    $slots[] = trim($slot['start']) . ' - ' . trim($slot['end']);
                } elseif (is_string($slot) && $slot !== '') {
                    $slots[] = trim($slot);
                }
            }
            $slots = array_values(array_unique(array_filter($slots)));
            if (empty($slots)) continue;
    
            $date_iso = $dt->format('Y-m-d');
    
            $candidates[] = [
                'date_iso' => $date_iso,
                'label'    => [
                    'title' => wp_date('l', $ts), 
                    'd'     => wp_date('j', $ts), 
                    'month' => wp_date('F', $ts), 
                ],
            ];
            $times_by_date[$date_iso] = $slots;
        }
    
        if (empty($candidates)) return;
        ?>
        <style>
            .snappbox-day-grid{
                display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));
                gap:8px;margin-top:6px
            }
            .snappbox-day-card{border:1px solid #ddd;border-radius:10px;padding:10px 8px;text-align:center;cursor:pointer;user-select:none;transition:box-shadow .15s,border-color .15s}
            .snappbox-day-card:hover{border-color:#2271b1}
            .snappbox-day-card input[type=radio]{display:none}
            .snappbox-day-card .day-title{font-weight:600;font-size:12px;line-height:1.1;margin-bottom:6px}
            .snappbox-day-card .day-date{font-size:22px;font-weight:700;line-height:1;margin-bottom:4px}
            .snappbox-day-card .day-month{font-size:12px;color:#555}
            .snappbox-day-card.snappbox-selected{border-color:#2271b1;box-shadow:0 0 0 2px rgba(34,113,177,.15)}
            .snappbox-time{
                border:1px solid #cecece;
                padding:10px;
                border-radius: 10px;
            }
        </style>
        <script>
        jQuery(function($){
            var SB_DELIVERY = {
                candidates: <?php echo wp_json_encode($candidates); ?>,
                timesByDate: <?php echo wp_json_encode($times_by_date); ?>
            };
    

            function renderInto($row){
                var $box    = $row.find('.snappbox-checkout-box');
                var $grid   = $row.find('#snappbox_day_grid');
                var $hidden = $row.find('input.snappbox-day-hidden');
                var $time   = $row.find('select.snappbox-time');
    
                if (!$box.length || !$grid.length || !$hidden.length || !$time.length) return;

                if ($grid.data('sbInit')) return;
                $grid.data('sbInit', true);
    
                $grid.empty();
                SB_DELIVERY.candidates.forEach(function(c, idx){
                    var $label = $('<label class="snappbox-day-card" />');
                    var $input = $('<input type="radio" name="snappbox_day_choice" />')
                                    .val(c.date_iso)
                                    .attr('data-date', c.date_iso);
                    if (idx === 0) $input.prop('checked', true);
    
                    $label.append($input);
                    $label.append('<div class="day-title">'+ c.label.title +'</div>');
                    $label.append('<div class="day-date">'+  c.label.d     +'</div>');
                    $label.append('<div class="day-month">'+ c.label.month +'</div>');
    
                    $grid.append($label);
                });
    
                applySelected($row);
            }
    
            function applySelected($row){
                var $grid   = $row.find('#snappbox_day_grid');
                var $hidden = $row.find('input.snappbox-day-hidden');
                var $time   = $row.find('select.snappbox-time');
    
                $grid.find('.snappbox-day-card').each(function(){
                    var checked = $(this).find('input[type=radio]').prop('checked');
                    $(this).toggleClass('snappbox-selected', !!checked);
                });
    
                var $sel    = $grid.find('input[type=radio]:checked');
                var dateKey = $sel.data('date');
                if (dateKey){
                    $hidden.val(dateKey);
                    fillTimes($time, dateKey);
                }
            }
    
            function fillTimes($timeSel, dateKey){
                var slots = SB_DELIVERY.timesByDate[dateKey] || [];
                $timeSel.empty();
                slots.forEach(function(s){
                    $('<option/>', { value:s, text:s }).appendTo($timeSel);
                });
            }
    
            function isSnappBoxSelected(){
                var selected = $('input[name^="shipping_method["]:checked').map(function(){ return $(this).val() || ''; }).get();
                return selected.some(function(v){ return /^snappbox_shipping_method(?::|$)/.test(v); });
            }
    
            function mountRow(){
                var $row = $('tr.snappbox-delivery-tr');
                if (!$row.length) return;
    
                if (isSnappBoxSelected()){
                    $row.show();
                    renderInto($row);
                } else {
                    $row.hide();
                }
            }
    
            $(document.body)
              .off('click.snappbox', '.snappbox-day-card')
              .on('click.snappbox',  '.snappbox-day-card', function(){
                  var $row   = $(this).closest('tr.snappbox-delivery-tr');
                  var $input = $(this).find('input[type=radio]');
                  if (!$input.prop('checked')) $input.prop('checked', true).trigger('change');
                  applySelected($row);
              });
    
            $(document.body)
              .off('change.snappbox', 'input[name="snappbox_day_choice"]')
              .on('change.snappbox',  'input[name="snappbox_day_choice"]', function(){
                  applySelected($(this).closest('tr.snappbox-delivery-tr'));
              });
    
            mountRow();
            $(document.body).on('updated_checkout updated_shipping_method updated_wc_div', mountRow);
            $(document.body).on('change', 'input[name^="shipping_method["]', mountRow);
        });
        </script>
        <?php
    }
    
    
    private function sb_normalize_schedule_to_w( array $schedule ) : array {
        $name_to_w = [
            'sunday'    => 0,
            'monday'    => 1,
            'tuesday'   => 2,
            'wednesday' => 3,
            'thursday'  => 4,
            'friday'    => 5,
            'saturday'  => 6,
        ];
        $day_labels = [
            'monday'    => _x( 'Monday',    'weekday name', 'sb-delivery' ),
            'tuesday'   => _x( 'Tuesday',   'weekday name', 'sb-delivery' ),
            'wednesday' => _x( 'Wednesday', 'weekday name', 'sb-delivery' ),
            'thursday'  => _x( 'Thursday',  'weekday name', 'sb-delivery' ),
            'friday'    => _x( 'Friday',    'weekday name', 'sb-delivery' ),
            'saturday'  => _x( 'Saturday',  'weekday name', 'sb-delivery' ),
            'sunday'    => _x( 'Sunday',    'weekday name', 'sb-delivery' ),
        ];
        $out = [];
        foreach ( $schedule as $key => $slots ) {
            $w = null;
            if ( is_numeric( $key ) ) {
                $w = max(0, min(6, (int) $key));
            } else {
                $k = strtolower( trim( (string) $key ) );
                if ( isset( $name_to_w[ $k ] ) ) $w = $name_to_w[ $k ];
            }
            if ( $w === null ) continue;
            if ( ! isset( $out[ $w ] ) ) $out[ $w ] = [];
            $out[ $w ] = array_merge( $out[ $w ], (array) $slots );
        }
        return $out;
    }


    public function save_order_meta($order, $data)
    {
        if (!empty($_POST['snappbox_day'])) {
            $order->update_meta_data('_snappbox_day', sanitize_text_field($_POST['snappbox_day']));
        }
        if (!empty($_POST['snappbox_time'])) {
            $order->update_meta_data('_snappbox_time', sanitize_text_field($_POST['snappbox_time']));
        }
    }

    public function display_order_meta($order) {
        $dateIso = $order->get_meta('_snappbox_day');   
        $time    = $order->get_meta('_snappbox_time');
    
        if ($dateIso || $time) {
            $ts = $dateIso ? strtotime($dateIso . ' 12:00:00') : false; 
            $dateLabel = $ts ? wp_date('l j F Y', $ts) : $dateIso;      
            echo '<p><strong>' . esc_html__('SnappBox Delivery:', 'sb-delivery') . '</strong><br>';
            echo esc_html( trim($dateLabel . ' - ' . $time, ' -') ) . '</p>';
        }
    }
}
