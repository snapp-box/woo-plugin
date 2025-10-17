<?php
if (!defined('ABSPATH')) { exit; }

if (!class_exists('SnappBox_Quick_Setup')) {
  class SnappBox_Quick_Setup {
    private $plugin_file;
    private $page_slug     = 'snappbox-quick-setup';
    private $nonce_action  = 'snappbox_qs_save';
    private $wc_option_key = 'woocommerce_snappbox_shipping_method_settings';

    public function __construct($plugin_file) {
      $this->plugin_file = $plugin_file;

      register_activation_hook($this->plugin_file, [$this, 'on_activate']);

      add_action('admin_init',            [$this, 'maybe_redirect_after_activation']);
      add_action('admin_menu',            [$this, 'add_menu']);
      add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
      add_action('admin_post_snappbox_qs_save', [$this, 'handle_save']);
    }

    public function on_activate() {
      add_option('snappbox_qs_do_activation_redirect', 'yes', false);
    }

    public function maybe_redirect_after_activation() {
      if (get_option('snappbox_qs_do_activation_redirect') === 'yes') {
        delete_option('snappbox_qs_do_activation_redirect');
        if (!isset($_GET['activate-multi']) && current_user_can('manage_woocommerce')) {
          wp_safe_redirect(admin_url('admin.php?page=' . $this->page_slug));
          exit;
        }
      }
    }

    public function add_menu() {
      add_submenu_page(
        'woocommerce',
        __('SnappBox Quick Setup', 'sb-delivery'),
        __('SnappBox Quick Setup', 'sb-delivery'),
        'manage_woocommerce',
        $this->page_slug,
        [$this, 'render_page']
      );
    }

    public function enqueue_assets($hook) {
      if ($hook !== 'woocommerce_page_' . $this->page_slug) return;

      $base_url = defined('SNAPPBOX_URL') ? trailingslashit(SNAPPBOX_URL) : plugin_dir_url($this->plugin_file);
      $base_dir = defined('SNAPPBOX_DIR') ? trailingslashit(SNAPPBOX_DIR) : plugin_dir_path($this->plugin_file);

      wp_enqueue_style(
        'snappbox-quick-setup',
        $base_url . 'assets/css/quick-setup.css',
        [],
        @filemtime($base_dir . 'assets/css/quick-setup.css')
      );

      $step = $this->current_step();

      if ($step === 3) {
        wp_enqueue_script('maplibregl', $base_url . 'assets/js/leaflet.js', [], null, true);
        wp_enqueue_style('maplibre-css', $base_url . 'assets/css/leaflet.css', [], null);
      }

      wp_enqueue_script(
        'snappbox-quick-setup',
        $base_url . 'assets/js/quick-setup.js',
        ['jquery'],
        @filemtime($base_dir . 'assets/js/quick-setup.js'),
        true
      );

      wp_localize_script('snappbox-quick-setup', 'SNB_QS', [
        'isStep3'      => ($step === 3),
        'mapStyle'     => 'https://tile.snappmaps.ir/styles/snapp-style-v4.1.2/style.json',
        'rtlPluginUrl' => 'https://unpkg.com/@mapbox/mapbox-gl-rtl-text@0.3.0/dist/mapbox-gl-rtl-text.js',
        'i18n'         => [
          'centerPinAria' => _x('Set location to map center', 'Center pin button ARIA', 'sb-delivery'),
        ],
      ]);
    }

    public function render_page() {
      if (!current_user_can('manage_woocommerce')) return;
      $step = $this->current_step();

      echo '<div class="sbqs-fullscreen" id="sbqs-root">';
      echo '  <div class="sbqs-container">';
      echo '    <div class="sb-logo">'.$this->get_logo_svg().'</div>';
      echo        $this->render_stepper($step);
      echo '    <div class="sbqs-card">';

      switch ($step) {
        case 1: $this->render_step_1(); break;
        case 2: $this->render_step_2(); break;
        case 3: $this->render_step_3(); break;
        case 4: $this->render_step_4(); break; 
        case 5: $this->render_step_5(); break; 
      }

      echo '    </div>';
      echo '  </div>';
      echo '</div>';
    }

    private function current_step() : int {
      $s = isset($_GET['step']) ? intval($_GET['step']) : 1;
      return max(1, min(5, $s));
    }

    private function url_for_step($n) : string {
      return admin_url('admin.php?page=' . $this->page_slug . '&step=' . intval($n));
    }

    private function render_stepper($step) : string {
      $titles = [
        1 => _x('API Token',     'Wizard step title', 'sb-delivery'),
        2 => _x('Select Cities', 'Wizard step title', 'sb-delivery'),
        3 => _x('Map Setup',     'Wizard step title', 'sb-delivery'),
        4 => _x('Store Info',    'Wizard step title', 'sb-delivery'),
        5 => _x('Other Info',    'Wizard step title', 'sb-delivery'),
      ];
      ob_start();
      echo '<div class="sbqs-stepper" role="navigation" aria-label="'.esc_attr_x('Wizard steps', 'ARIA', 'sb-delivery').'">';
      for ($i=1; $i<=5; $i++) {
        $active = ($i <= $step) ? ' active' : '';
        $current= ($i == $step) ? ' current' : '';
        echo '<div class="sbqs-step'.$active.$current.'">';
        echo '  <div class="sbqs-title">'.esc_html($titles[$i]).'</div>';
        if ($i < $step) {
          echo '  <a class="sbqs-dot" href="'.esc_url($this->url_for_step($i)).'" aria-label="'.esc_attr(sprintf(_x('Go to step %d', 'ARIA', 'sb-delivery'), $i)).'">'.$i.'</a>';
        } else {
          echo '  <span class="sbqs-dot" aria-current="step">'.$i.'</span>';
        }
        echo '</div>';
      }
      echo '</div>';
      return ob_get_clean();
    }

    private function render_form_open($step) {
      echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="sbqs-form">';
      echo '<input type="hidden" name="action" value="snappbox_qs_save" />';
      echo '<input type="hidden" name="step" value="'.intval($step).'" />';
      wp_nonce_field($this->nonce_action, '_snappbox_qs_nonce');
    }

    private function render_form_close($step, $is_last = false) {
      echo '<div class="sbqs-actions">';
      if ($step > 1) {
        echo '<a class="button button-secondary sbqs-btn" href="'.esc_url($this->url_for_step($step-1)).'">'.esc_html_x('Back', 'Button', 'sb-delivery').'</a>';
      }
      echo '<button type="submit" class="button button-primary sbqs-btn">'.esc_html($is_last ? _x('Finish', 'Button', 'sb-delivery') : _x('Save & Continue', 'Button', 'sb-delivery')).'</button>';
      echo '</div></form>';
    }


    private function render_step_1() {
      $settings = maybe_unserialize(get_option($this->wc_option_key));
      $api = is_array($settings) ? ($settings['snappbox_api'] ?? '') : '';

      $this->render_form_open(1);
      echo '<p class="sbqs-lead">'.esc_html_x('Enter your SnappBox API token', 'Lead text', 'sb-delivery').'</p>';
      echo '<div class="sbqs-field sbqs-row">';
      echo '  <label for="sb_api">'.esc_html_x('API Key', 'Label', 'sb-delivery').'</label>';
      echo '  <div class="sbqs-input-row">';
      echo '    <input type="text" id="sb_api" name="api" value="'.esc_attr($api).'" placeholder="'.esc_attr_x('Paste your API key…', 'Placeholder', 'sb-delivery').'" />';
      echo '    <a class="button button-primary sbqs-btn" target="_blank" rel="noopener" href="https://snapp-box.com/connect">'.esc_html_x('Get API Key', 'Button', 'sb-delivery').'</a>';
      echo '  </div>';
      echo '</div>';
      $this->render_form_close(1);
    }

    // 2) Cities
    private function render_step_2() {
      $lat = get_option('snappbox_latitude', '35.8037761');
      $lng = get_option('snappbox_longitude', '51.4152466');

      $settings = maybe_unserialize(get_option($this->wc_option_key));
      $selected = (is_array($settings) && !empty($settings['snappbox_cities'])) ? (array)$settings['snappbox_cities'] : [];

      $cities = [];
      if (class_exists('SnappBoxCities')) {
        try {
          $obj = new \SnappBoxCities();
          $res = $obj->get_delivery_category($lat, $lng);
          if (!empty($res->cities) && is_array($res->cities)) {
            foreach ($res->cities as $c) {
              if (!empty($c->cityKey) && !empty($c->cityName)) {
                $cities[$c->cityKey] = $c->cityName;
              }
            }
          }
        } catch (\Throwable $e) {}
      }

      $this->render_form_open(2);
      echo '<p class="sbqs-lead">'.esc_html_x('Select available cities for delivery', 'Lead text', 'sb-delivery').'</p>';
      if (!empty($cities)) {
        echo '<div class="sbqs-field">';
        echo '  <label for="sbqs-cities">'.esc_html_x('Cities', 'Label', 'sb-delivery').'</label>';
        echo '  <select id="sbqs-cities" class="sbqs-select" name="cities[]" multiple size="8">';
        foreach ($cities as $key => $name) {
          $sel = in_array($key, $selected, true) ? ' selected' : '';
          echo '<option value="'.esc_attr($key).'"'.$sel.'>'.esc_html($name).'</option>';
        }
        echo '  </select>';
        echo '  <small>'.esc_html_x('Hold Ctrl/⌘ to select multiple.', 'Help text', 'sb-delivery').'</small>';
        echo '</div>';
      } else {
        echo '<p>'.esc_html_x('No cities available. Set token and location first.', 'Notice', 'sb-delivery').'</p>';
      }
      $this->render_form_close(2);
    }

    private function render_step_3() {
      $lat = get_option('snappbox_latitude', '35.8037761');
      $lng = get_option('snappbox_longitude', '51.4152466');

      $this->render_form_open(3);
      echo '<p class="sbqs-lead">'.esc_html_x('Place your store on the map', 'Lead text', 'sb-delivery').'</p>';
      echo '<div class="sbqs-map-wrap">';
      echo '  <div id="sbqs-map" class="sbqs-map"></div>';
      echo '  <button type="button" id="sbqs-center-pin" aria-label="'.esc_attr_x('Set location to map center', 'ARIA', 'sb-delivery').'"></button>';
      echo '</div>';
      echo '<div class="sbqs-two">';
      echo '  <div class="sbqs-field"><label>'.esc_html_x('Latitude', 'Label', 'sb-delivery').'</label><input type="text" name="lat" id="sb_lat" value="'.esc_attr($lat).'" /></div>';
      echo '  <div class="sbqs-field"><label>'.esc_html_x('Longitude', 'Label', 'sb-delivery').'</label><input type="text" name="lng" id="sb_lng" value="'.esc_attr($lng).'" /></div>';
      echo '</div>';
      $this->render_form_close(3);
    }

    private function render_step_4() {
      $settings = maybe_unserialize(get_option($this->wc_option_key));
      if (!is_array($settings)) { $settings = []; }

      $store_name  = get_option('snappbox_store_name', '');
      $store_phone = get_option('snappbox_store_phone', '');

      $enabled = isset($settings['enabled']) ? $settings['enabled'] : 'yes'; // default yes
      $title   = isset($settings['title'])   ? $settings['title']   : __('SnappBox Shipping', 'sb-delivery');

      $this->render_form_open(4);
      echo '<p class="sbqs-lead">'.esc_html_x('Store information & activation', 'Lead text', 'sb-delivery').'</p>';
      echo '<div class="sbqs-grid">';
      echo '  <div class="sbqs-field"><label>'.esc_html_x('Store name', 'Label', 'sb-delivery').'</label><input type="text" name="store_name" value="'.esc_attr($store_name).'" /></div>';
      echo '  <div class="sbqs-field"><label>'.esc_html_x('Mobile number', 'Label', 'sb-delivery').'</label><input type="text" name="store_phone" value="'.esc_attr($store_phone).'" placeholder="0912…" /></div>';
      echo '  <div class="sbqs-field"><label>'.esc_html_x('Shipping method title', 'Label', 'sb-delivery').'</label><input type="text" name="method_title" value="'.esc_attr($title).'" placeholder="'.esc_attr_x('SnappBox Shipping', 'Placeholder', 'sb-delivery').'" /></div>';
      echo '  <label class="sbqs-check"><input type="checkbox" name="enabled" value="yes" '.checked($enabled === 'yes', true, false).' /> '.esc_html_x('Enable this shipping method', 'Checkbox', 'sb-delivery').'</label>';
      echo '</div>';
      $this->render_form_close(4);
    }

    private function render_step_5() {
      $settings = maybe_unserialize(get_option($this->wc_option_key));
      if (!is_array($settings)) { $settings = []; }

      $ondelivery    = (isset($settings['ondelivery']) && $settings['ondelivery'] === 'yes');
      $fixed_price   = $settings['fixed_price']   ?? '';
      $free_delivery = $settings['free_delivery'] ?? '';
      $base_cost     = $settings['base_cost']     ?? '';
      $cost_per_kg   = $settings['cost_per_kg']   ?? '';

      $this->render_form_open(5);
      echo '<p class="sbqs-lead">'.esc_html_x('Other settings & rates', 'Lead text', 'sb-delivery').'</p>';
      echo '<div class="sbqs-grid">';
      echo '  <label class="sbqs-check"><input type="checkbox" name="ondelivery" value="yes" '.checked($ondelivery, true, false).' /> '.esc_html_x('Pay on SnappBox delivery', 'Checkbox', 'sb-delivery').'</label>';
      echo '  <div class="sbqs-field"><label>'.esc_html_x('Fixed price', 'Label', 'sb-delivery').'</label><input type="text" name="fixed_price" value="'.esc_attr($fixed_price).'" /></div>';
      echo '  <div class="sbqs-field"><label>'.esc_html_x('Free delivery threshold', 'Label', 'sb-delivery').'</label><input type="text" name="free_delivery" value="'.esc_attr($free_delivery).'" /></div>';
      echo '  <div class="sbqs-field"><label>'.esc_html_x('Base cost', 'Label', 'sb-delivery').'</label><input type="text" name="base_cost" value="'.esc_attr($base_cost).'" /></div>';
      echo '  <div class="sbqs-field"><label>'.esc_html_x('Cost per KG', 'Label', 'sb-delivery').'</label><input type="text" name="cost_per_kg" value="'.esc_attr($cost_per_kg).'" /></div>';
      echo '</div>';
      $this->render_form_close(5, true);
    }

    public function handle_save() {
      if (!current_user_can('manage_woocommerce')) wp_die('forbidden');
      check_admin_referer($this->nonce_action, '_snappbox_qs_nonce');

      $step = isset($_POST['step']) ? intval($_POST['step']) : 1;

      $settings = maybe_unserialize(get_option($this->wc_option_key));
      if (!is_array($settings)) $settings = [];

      switch ($step) {
        case 1: {
          $api = isset($_POST['api']) ? sanitize_text_field(wp_unslash($_POST['api'])) : '';
          $settings['snappbox_api'] = $api;
          update_option($this->wc_option_key, $settings);
          $this->redirect_step(2);
          break;
        }

        case 2: {
          $cities = isset($_POST['cities']) ? (array) $_POST['cities'] : [];
          $settings['snappbox_cities'] = array_map('sanitize_text_field', $cities);
          update_option($this->wc_option_key, $settings);
          $this->redirect_step(3);
          break;
        }

        case 3: {
          $lat = $this->normalize_number(isset($_POST['lat']) ? wp_unslash($_POST['lat']) : '');
          $lng = $this->normalize_number(isset($_POST['lng']) ? wp_unslash($_POST['lng']) : '');

          if ($lat !== '' && is_numeric($lat)) {
            update_option('snappbox_latitude', $lat);
            $settings['snappbox_latitude'] = $lat;
          }
          if ($lng !== '' && is_numeric($lng)) {
            update_option('snappbox_longitude', $lng);
            $settings['snappbox_longitude'] = $lng;
          }

          update_option($this->wc_option_key, $settings);
          $this->redirect_step(4);
          break;
        }

        case 4: {
          $store_name_raw  = isset($_POST['store_name'])  ? wp_unslash($_POST['store_name'])  : '';
          $store_phone_raw = isset($_POST['store_phone']) ? wp_unslash($_POST['store_phone']) : '';
          $method_title    = isset($_POST['method_title'])? wp_unslash($_POST['method_title']): '';
          $enabled         = (isset($_POST['enabled']) && $_POST['enabled'] === 'yes') ? 'yes' : 'no';

          $store_name  = sanitize_text_field($store_name_raw);
          $store_phone = preg_replace('#[^0-9+\-\s]#', '', $store_phone_raw);
          $title       = ($method_title === '') ? __('SnappBox Shipping', 'sb-delivery') : sanitize_text_field($method_title);

          update_option('snappbox_store_name',  $store_name);
          update_option('snappbox_store_phone', $store_phone);

          $settings['snappbox_store_name']  = $store_name;
          $settings['snappbox_store_phone'] = $store_phone;
          $settings['enabled']              = $enabled;
          $settings['title']                = $title;

          update_option($this->wc_option_key, $settings);

          $this->redirect_step(5);
          break;
        }

        case 5: {
          $settings['ondelivery'] = (isset($_POST['ondelivery']) && $_POST['ondelivery'] === 'yes') ? 'yes' : 'no';
          foreach (['fixed_price','free_delivery','base_cost','cost_per_kg'] as $k) {
            if (isset($_POST[$k])) { $settings[$k] = sanitize_text_field(wp_unslash($_POST[$k])); }
          }
          update_option($this->wc_option_key, $settings);
          wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=shipping&section=snappbox_shipping_method'));
          exit;
        }
      }

      $this->redirect_step(1);
    }

    private function redirect_step($n) {
      wp_safe_redirect($this->url_for_step($n));
      exit;
    }

    private function normalize_number($s) {
      $s = trim((string) $s);
      if ($s === '') return '';
      $map = [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
      ];
      $s = strtr($s, $map);
      $s = str_replace(',', '.', $s);
      $s = preg_replace('/[^0-9\.\-+eE]/', '', $s);
      return $s;
    }

    private function get_logo_svg() : string {
      return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 80 42" role="img" aria-label="SnappBox">
  <g clip-path="url(#snappbox-logo_svg__a)">
    <path d="M11.486 2.672a2.55 2.55 0 0 1-2.952 1.276 4.1 4.1 0 0 0-1.196-.16c-.838 0-1.954.48-1.954 1.476 0 .997 1.236 1.396 2.034 1.675l1.156.36c2.433.717 4.307 1.953 4.307 4.785 0 1.755-.398 3.51-1.794 4.706a7.62 7.62 0 0 1-4.945 1.635A10.65 10.65 0 0 1 0 16.43v-.04l1.954-3.63c1.117.998 2.473 1.795 4.028 1.795 1.077 0 2.193-.518 2.193-1.754 0-1.237-1.794-1.715-2.751-1.994C2.552 10.01.678 9.252.678 5.862.678 2.313 3.19 0 6.7 0c1.91.032 3.784.524 5.464 1.436v.04zM17.947 7.537c.997-1.435 2.193-1.914 3.828-1.914 3.19 0 4.467 2.034 4.467 4.945v4.826a2.49 2.49 0 0 1-2.473 2.512h-1.714c-.04 0-.04 0-.04-.04v-5.782c0-1.157.2-3.15-1.915-3.15-1.714 0-2.193 1.275-2.193 2.751v3.669a2.49 2.49 0 0 1-2.473 2.512H13.72c-.04 0-.04 0-.04-.04V5.983c0-.04 0-.04.04-.04h4.147c.04 0 .04 0 .04.04zM38.365 17.906H36.69c-.04 0-.04 0-.04-.04V16.59c0-.04 0-.04-.04-.04h-.04c-.758 1.157-2.193 1.715-3.59 1.715-3.509 0-6.021-2.95-6.021-6.34s2.433-6.342 5.942-6.342a4.81 4.81 0 0 1 3.63 1.476c0 .04.04 0 .04 0V6.022c0-.04 0-.04.039-.04h4.148c.04 0 .04 0 .04.04v9.372a2.365 2.365 0 0 1-2.194 2.512zm-6.98-5.942a2.54 2.54 0 0 0 2.393 2.672h.32a2.544 2.544 0 0 0 2.711-2.353v-.319a2.57 2.57 0 0 0-2.432-2.672h-.28a2.584 2.584 0 0 0-2.711 2.433zM46.42 7.378c.04-.04 .04 0 0 0a4.3 4.3 0 0 1 3.629-1.755c3.55 0 6.022 2.951 6.022 6.381s-2.433 6.301-5.942 6.301a4.88 4.88 0 0 1-3.67-1.515v3.629a2.49 2.49 0 0 1-2.472 2.512h-1.755V6.022h4.228zm-.16 4.586a2.54 2.54 0 0 0 2.393 2.672h.32a2.544 2.544 0 0 0 2.711-2.353v-.319a2.573 2.573 0 0 0-2.433-2.672h-.279a2.584 2.584 0 0 0-2.712 2.433zM61.098 7.378a4.3 4.3 0 0 1 3.629-1.755c3.55 0 6.022 2.951 6.022 6.381s-2.433 6.301-5.942 6.301a4.97 4.97 0 0 1-3.63-1.476c0-.04-.04 0-.04 .04v3.55a2.493 2.493 0 0 1-2.472 2.512H56.95c-.04 0-.04 0-.04-.04V6.022c0-.04 0-.04 .04-.04h4.148c.04 0 .04 0 .04 .04zm-.16 4.586a2.54 2.54 0 0 0 2.393 2.672h.319a2.544 2.544 0 0 0 2.712-2.353v-.319a2.573 2.573 0 0 0-2.433-2.672h-.28a2.584 2.584 0 0 0-2.71 2.433zM75.135 11.645h-2.393L75.614 .44c0-.04 0-.04 .04-.04h4.307c.04 0 .04 0 .04 .04L77.607 9.73a2.56 2.56 0 0 1-2.473 1.914M71.467 15.633a2.54 2.54 0 0 0 2.392 2.672h.32a2.544 2.544 0 0 0 2.711-2.353v-.319a2.57 2.57 0 0 0-2.433-2.672h-.279a2.585 2.585 0 0 0-2.712 2.433zM.678 23.13h6.78c1.595 0 2.83 .4 3.668 1.157.838 .758 1.237 1.875 1.237 3.35a4.2 4.2 0 0 1-.479 2.194 3.9 3.9 0 0 1-1.436 1.475c.583 .101 1.148 .29 1.675 .558.44 .23 .823 .557 1.117 .957.278 .37 .481 .79 .598 1.237.125 .482 .192 .977 .2 1.475a5.8 5.8 0 0 1-.439 2.194 4.7 4.7 0 0 1-1.196 1.595 5.6 5.6 0 0 1-1.875 .957c-.822 .204-1.665 .311-2.512 .32H.718V23.13zm4.546 6.86h.838q2.153 0 2.153-1.675T6.062 26.64h-.838zm0 7.139h.997a5.34 5.34 0 0 0 2.473-.44 1.54 1.54 0 0 0 .757-1.395 1.49 1.49 0 0 0-.757-1.436c-.479-.279-1.316-.438-2.473-.438h-.997zm9.492-2.553a5.85 5.85 0 0 1 .518-2.472 6.05 6.05 0 0 1 1.476-1.994 7.2 7.2 0 0 1 2.273-1.316 8.73 8.73 0 0 1 5.703 0 7.2 7.2 0 0 1 2.273 1.316 5.9 5.9 0 0 1 1.515 2.034 6.5 6.5 0 0 1-.04 5.184 6.3 6.3 0 0 1-1.515 2.034 7.2 7.2 0 0 1-2.273 1.316 8.73 8.73 0 0 1-5.703 0 6.6 6.6 0 0 1-2.233-1.316 5.5 5.5 0 0 1-1.476-2.074 6.3 6.3 0 0 1-.518-2.712m4.426 .04c-.001 .385 .08 .766 .24 1.117q.244 .475 .598 .877 .392 .368 .877 .598a2.8 2.8 0 0 0 2.074 0 2.6 2.6 0 0 0 .877-.598c.244-.26 .446-.555 .599-.877 .159-.337 .24-.705 .239-1.077 .001-.36 -.08 -.714 -.24 -1.037a4.3 4.3 0 0 0 -.598 -.877 3.4 3.4 0 0 0 -.877 -.598 2.8 2.8 0 0 0 -2.074 0c-.33 .138 -.628 .341 -.877 .598 -.244 .26 -.446 .555 -.598 .877 -.159 .309 -.241 .65 -.24 .997m14.357-.438-4.825-5.464h5.463l2.034 2.472 2.074-2.472h5.464l-4.946 5.464 5.983 6.46h-5.584l-3.03-3.51-3.071 3.51h-5.544z"></path>
  </g>
  <defs>
    <clipPath id="snappbox-logo_svg__a"><path fill="#fff" d="M0 0h80v41.157H0z"></path></clipPath>
  </defs>
</svg>
SVG;
    }
  }
}

if (is_admin()) {
  $GLOBALS['snappbox_quick_setup'] = new SnappBox_Quick_Setup(__FILE__);
}
