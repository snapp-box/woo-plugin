<?php
namespace Snappbox;

defined('ABSPATH') || exit;

if ( ! class_exists('\Snappbox\SnappBox_Quick_Setup') ) {
  class SnappBox_Quick_Setup {
    private $plugin_file;
    private $page_slug         = 'snappbox-quick-setup';
    private $nonce_action      = 'snappbox_qs_save';
    private $nav_nonce_action  = 'snappbox_qs_nav';
    private $nav_nonce_name    = '_snappbox_qs_nav';
    private $wc_option_key     = 'woocommerce_snappbox_shipping_method_settings';

    public function __construct( $plugin_file ) {
      $this->plugin_file = $plugin_file;

      \register_activation_hook( $this->plugin_file, [ $this, 'snappb_on_activate' ] );

      \add_action( 'admin_init',                  [ $this, 'snappb_maybe_redirect_after_activation' ] );
      \add_action( 'admin_menu',                  [ $this, 'snappb_add_menu' ] );
      \add_action( 'admin_enqueue_scripts',       [ $this, 'snappb_enqueue_assets' ] );
      \add_action( 'admin_post_snappbox_qs_save', [ $this, 'snappb_handle_save' ] );
    }

    public function snappb_on_activate() : void {
      \add_option( 'snappbox_qs_do_activation_redirect', 'yes' );
    }

    public function snappb_maybe_redirect_after_activation() : void {
      if ( \get_option( 'snappbox_qs_do_activation_redirect' ) === 'yes' ) {
        \delete_option( 'snappbox_qs_do_activation_redirect' );
        $activate_multi = isset( $_GET['activate-multi'] ) ? \sanitize_text_field( \wp_unslash( $_GET['activate-multi'] ) ) : '';

        if ( $activate_multi === '' && \current_user_can( 'manage_woocommerce' ) ) {
          \wp_safe_redirect( $this->snappb_url_for_step( 1 ) );
          exit;
        }
      }
    }

    public function snappb_add_menu() : void {
      \add_submenu_page(
        'woocommerce',
        \__( 'SnappBox Quick Setup', 'snappbox' ),
        \__( 'SnappBox Quick Setup', 'snappbox' ),
        'manage_woocommerce',
        $this->page_slug,
        [ $this, 'snappb_render_page' ]
      );
    }

    public function snappb_enqueue_assets( $hook ) : void {
      if ( $hook !== 'woocommerce_page_' . $this->page_slug ) {
        return;
      }

      $base_url = \defined( 'SNAPPBOX_URL' ) ? \trailingslashit( SNAPPBOX_URL ) : \plugin_dir_url( $this->plugin_file );
      $base_dir = \defined( 'SNAPPBOX_DIR' ) ? \trailingslashit( SNAPPBOX_DIR ) : \plugin_dir_path( $this->plugin_file );

      $css_path = $base_dir . 'assets/css/quick-setup.css';
      $js_path  = $base_dir . 'assets/js/quick-setup.js';
      $css_ver  = \file_exists( $css_path ) ? (string) \filemtime( $css_path ) : false;
      $js_ver   = \file_exists( $js_path )  ? (string) \filemtime( $js_path )  : false;

      \wp_enqueue_style(
        'snappbox-quick-setup',
        $base_url . 'assets/css/quick-setup.css',
        [],
        $css_ver
      );

      $step = $this->snappb_current_step();

      if ( $step === 3 ) {
        \wp_enqueue_script( 'snappbox-leaflet', $base_url . 'assets/js/leaflet.js', [], false, true );
        \wp_enqueue_style( 'snappbox-leaflet-css', $base_url . 'assets/css/leaflet.css', [], false );
      }

      \wp_enqueue_script(
        'snappbox-quick-setup',
        $base_url . 'assets/js/quick-setup.js',
        [ 'jquery' ],
        $js_ver,
        true
      );

      \wp_localize_script(
        'snappbox-quick-setup',
        'SNB_QS',
        [
          'isStep3'      => ( $step === 3 ),
          'mapStyle'     => 'https://tile.snappmaps.ir/styles/snapp-style-v4.1.2/style.json',
          'rtlPluginUrl' => \trailingslashit( $base_url ) . 'assets/js/mapbox-gl-rtl-text.js',
          'i18n'         => [
            'centerPinAria' => \_x( 'Set location to map center', 'Center pin button ARIA', 'snappbox' ),
          ],
        ]
      );
    }

    public function snappb_render_page() : void {
      if ( ! \current_user_can( 'manage_woocommerce' ) ) {
        return;
      }

      $step = $this->snappb_current_step();

      echo '<div class="sbqs-fullscreen" id="sbqs-root">';
      echo '  <div class="sbqs-container">';
      echo \wp_kses_post( $this->snappb_get_logo_svg() );
      echo \wp_kses_post( $this->snappb_render_stepper( $step ) );
      echo '    <div class="sbqs-card">';

      switch ( $step ) {
        case 1: $this->snappb_render_step_1(); break;
        case 2: $this->snappb_render_step_2(); break;
        case 3: $this->snappb_render_step_3(); break;
        case 4: $this->snappb_render_step_4(); break;
        case 5: $this->snappb_render_step_5(); break;
        default: $this->snappb_render_step_1(); break;
      }

      echo '    </div>';
      echo '  </div>';
      echo '</div>';
    }

    private function snappb_current_step() : int {
      $raw = isset( $_GET['step'] ) ? \sanitize_text_field( \wp_unslash( $_GET['step'] ) ) : '1';
      $s   = (int) $raw;
      return \max( 1, \min( 5, $s ) );
    }

    private function snappb_url_for_step( $n ) : string {
      return \add_query_arg(
        [
          'page' => $this->page_slug,
          'step' => (int) $n,
        ],
        \admin_url( 'admin.php' )
      );
    }

    private function snappb_render_stepper( $step ) : string {
      $titles = [
        1 => \esc_html_x( 'API Token',     'Wizard step title', 'snappbox' ),
        2 => \esc_html_x( 'Select Cities', 'Wizard step title', 'snappbox' ),
        3 => \esc_html_x( 'Map Setup',     'Wizard step title', 'snappbox' ),
        4 => \esc_html_x( 'Store Info',    'Wizard step title', 'snappbox' ),
        5 => \esc_html_x( 'Other Info',    'Wizard step title', 'snappbox' ),
      ];

      \ob_start();
      ?>
      <div class="sbqs-stepper" role="navigation" aria-label="<?php echo \esc_attr_x( 'Wizard steps', 'ARIA', 'snappbox' ); ?>">
        <?php for ( $i = 1; $i <= 5; $i++ ) :
          $active  = ( $i <= $step ) ? ' active' : '';
          $current = ( $i === $step ) ? ' current' : '';
          ?>
          <div class="sbqs-step<?php echo \esc_attr( $active . $current ); ?>">
            <div class="sbqs-title"><?php echo \esc_html( $titles[ $i ] ); ?></div>
            <?php if ( $i < $step ) : ?>
              <a class="sbqs-dot"
                 href="<?php echo \esc_url( $this->snappb_url_for_step( $i ) ); ?>"
                 aria-label="<?php echo \esc_attr( \sprintf(
                   \esc_html( 'Go to step %d', 'snappbox' ),
                   $i
                 ) ); ?>">
                 <?php echo \esc_html( (string) $i ); ?>
              </a>
            <?php else : ?>
              <span class="sbqs-dot" aria-current="step"><?php echo \esc_html( (string) $i ); ?></span>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
      <?php
      return (string) \ob_get_clean();
    }

    private function snappb_render_form_open( $step ) : void {
      ?>
      <form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" class="sbqs-form">
        <input type="hidden" name="action" value="snappbox_qs_save" />
        <input type="hidden" name="step" value="<?php echo \esc_attr( (string) (int) $step ); ?>" />
        <?php \wp_nonce_field( $this->nonce_action, '_snappbox_qs_nonce' ); ?>
      <?php
    }

    private function snappb_render_form_close( $step, $is_last = false ) : void {
      ?>
        <div class="sbqs-actions">
          <?php if ( $step > 1 ) : ?>
            <a class="button button-secondary sbqs-btn"
               href="<?php echo \esc_url( $this->snappb_url_for_step( $step - 1 ) ); ?>">
               <?php echo \esc_html_x( 'Back', 'Button', 'snappbox' ); ?>
            </a>
          <?php endif; ?>
          <button type="submit" class="button button-primary sbqs-btn">
            <?php echo \esc_html( $is_last ? \_x( 'Finish', 'Button', 'snappbox' ) : \_x( 'Save & Continue', 'Button', 'snappbox' ) ); ?>
          </button>
        </div>
      </form>
      <?php
    }

    private function snappb_render_step_1() : void {
      $settings = \maybe_unserialize( \get_option( $this->wc_option_key ) );
      $api      = \is_array( $settings ) ? ( $settings['snappbox_api'] ?? '' ) : '';

      $this->snappb_render_form_open( 1 );
      echo '<p class="sbqs-lead">' . \esc_html_x( 'Enter your SnappBox API token', 'Lead text', 'snappbox' ) . '</p>';
      ?>
      <div class="sbqs-field sbqs-row">
        <label for="sb_api"><?php echo \esc_html_x( 'API Key', 'Label', 'snappbox' ); ?></label>
        <div class="sbqs-input-row">
          <input type="text" id="sb_api" name="api" value="<?php echo \esc_attr( $api ); ?>"
                 placeholder="<?php echo \esc_attr_x( 'Paste your API key…', 'Placeholder', 'snappbox' ); ?>" />
          <a class="button button-primary sbqs-btn" target="_blank" rel="noopener"
             href="<?php echo \esc_url( 'https://snapp-box.com/connect' ); ?>">
             <?php echo \esc_html_x( 'Get API Key', 'Button', 'snappbox' ); ?>
          </a>
        </div>
      </div>
      <?php
      $this->snappb_render_form_close( 1 );
    }

    private function snappb_render_step_2() : void {
      $lat = \get_option( 'snappbox_latitude',  '35.8037761' );
      $lng = \get_option( 'snappbox_longitude', '51.4152466' );

      $settings = \maybe_unserialize( \get_option( $this->wc_option_key ) );
      $selected = ( \is_array( $settings ) && ! empty( $settings['snappbox_cities'] ) ) ? (array) $settings['snappbox_cities'] : [];

      $cities = [];
      if ( \class_exists( '\Snappbox\Api\SnappBoxCities' ) ) {
        try {
          $obj = new \Snappbox\Api\SnappBoxCities();
          $res = $obj->snappb_get_delivery_category( $lat, $lng );
          if ( ! empty( $res->cities ) && \is_array( $res->cities ) ) {
            foreach ( $res->cities as $c ) {
              if ( ! empty( $c->cityKey ) && ! empty( $c->cityName ) ) {
                $cities[ (string) $c->cityKey ] = (string) $c->cityName;
              }
            }
          }
        } catch ( \Throwable $e ) {
          // Fail silently
        }
      }

      $this->snappb_render_form_open( 2 );
      echo '<p class="sbqs-lead">' . \esc_html_x( 'Select available cities for delivery', 'Lead text', 'snappbox' ) . '</p>';

      if ( ! empty( $cities ) ) {
        ?>
        <div class="sbqs-field">
          <label for="sbqs-cies"><?php echo \esc_html_x( 'Cities', 'Label', 'snappbox' ); ?></label>
          <select id="sbqs-cies" class="sbqs-select" name="cities[]" multiple="multiple" size="8">
            <?php foreach ( $cities as $key => $name ) : ?>
              <option value="<?php echo \esc_attr( $key ); ?>" <?php \selected( \in_array( $key, $selected, true ) ); ?>>
                <?php echo \esc_html( $name ); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small><?php echo \esc_html_x( 'Hold Ctrl/⌘ to select multiple.', 'Help text', 'snappbox' ); ?></small>
        </div>
        <?php
      } else {
        echo '<p>' . \esc_html_x( 'No cities available. Set token and location first.', 'Notice', 'snappbox' ) . '</p>';
      }

      $this->snappb_render_form_close( 2 );
    }

    private function snappb_render_step_3() : void {
      $lat = \get_option( 'snappbox_latitude',  '35.8037761' );
      $lng = \get_option( 'snappbox_longitude', '51.4152466' );

      $this->snappb_render_form_open( 3 );
      echo '<p class="sbqs-lead">' . \esc_html_x( 'Place your store on the map', 'Lead text', 'snappbox' ) . '</p>';
      ?>
      <div class="sbqs-map-wrap">
        <div id="sbqs-map" class="sbqs-map"></div>
        <button type="button" id="sbqs-center-pin"
                aria-label="<?php echo \esc_attr_x( 'Set location to map center', 'ARIA', 'snappbox' ); ?>">
        </button>
      </div>
      <div class="sbqs-two">
        <div class="sbqs-field">
          <label for="sb_lat"><?php echo \esc_html_x( 'Latitude', 'Label', 'snappbox' ); ?></label>
          <input type="text" name="lat" id="sb_lat" value="<?php echo \esc_attr( $lat ); ?>" />
        </div>
        <div class="sbqs-field">
          <label for="sb_lng"><?php echo \esc_html_x( 'Longitude', 'Label', 'snappbox' ); ?></label>
          <input type="text" name="lng" id="sb_lng" value="<?php echo \esc_attr( $lng ); ?>" />
        </div>
      </div>
      <?php
      $this->snappb_render_form_close( 3 );
    }

    private function snappb_render_step_4() : void {
      $settings = \maybe_unserialize( \get_option( $this->wc_option_key ) );
      if ( ! \is_array( $settings ) ) {
        $settings = [];
      }

      $store_name  = \get_option( 'snappbox_store_name', '' );
      $store_phone = \get_option( 'snappbox_store_phone', '' );

      $enabled = isset( $settings['enabled'] ) ? $settings['enabled'] : 'yes';
      $title   = isset( $settings['title'] )   ? $settings['title']   : \__( 'SnappBox Shipping', 'snappbox' );

      $this->snappb_render_form_open( 4 );
      echo '<p class="sbqs-lead">' . \esc_html_x( 'Store information & activation', 'Lead text', 'snappbox' ) . '</p>';
      ?>
      <div class="sbqs-grid">
        <div class="sbqs-field">
          <label for="sb_store_name"><?php echo \esc_html_x( 'Store name', 'Label', 'snappbox' ); ?></label>
          <input type="text" id="sb_store_name" name="store_name" value="<?php echo \esc_attr( $store_name ); ?>" />
        </div>
        <div class="sbqs-field">
          <label for="sb_store_phone"><?php echo \esc_html_x( 'Mobile number', 'Label', 'snappbox' ); ?></label>
          <input type="text" id="sb_store_phone" name="store_phone" value="<?php echo \esc_attr( $store_phone ); ?>" placeholder="0912…" />
        </div>
        <div class="sbqs-field">
          <label for="sb_method_title"><?php echo \esc_html_x( 'Shipping method title', 'Label', 'snappbox' ); ?></label>
          <input type="text" id="sb_method_title" name="method_title" value="<?php echo \esc_attr( $title ); ?>"
                 placeholder="<?php echo \esc_attr_x( 'SnappBox Shipping', 'Placeholder', 'snappbox' ); ?>" />
        </div>
        <label class="sbqs-check">
          <input type="checkbox" name="enabled" value="yes" <?php \checked( $enabled === 'yes' ); ?> />
          <?php echo \esc_html_x( 'Enable this shipping method', 'Checkbox', 'snappbox' ); ?>
        </label>
      </div>
      <?php
      $this->snappb_render_form_close( 4 );
    }

    private function snappb_render_step_5() : void {
      $settings = \maybe_unserialize( \get_option( $this->wc_option_key ) );
      if ( ! \is_array( $settings ) ) {
        $settings = [];
      }

      $ondelivery    = ( isset( $settings['ondelivery'] ) && $settings['ondelivery'] === 'yes' );
      $fixed_price   = $settings['fixed_price']   ?? '';
      $free_delivery = $settings['free_delivery'] ?? '';
      $base_cost     = $settings['base_cost']     ?? '';
      $cost_per_kg   = $settings['cost_per_kg']   ?? '';

      $this->snappb_render_form_open( 5 );
      echo '<p class="sbqs-lead">' . \esc_html_x( 'Other settings & rates', 'Lead text', 'snappbox' ) . '</p>';
      ?>
      <div class="sbqs-grid">
        <label class="sbqs-check">
          <input type="checkbox" name="ondelivery" value="yes" <?php \checked( $ondelivery ); ?> />
          <?php echo \esc_html_x( 'Pay on SnappBox delivery', 'Checkbox', 'snappbox' ); ?>
        </label>
        <div class="sbqs-field">
          <label for="sb_fixed_price"><?php echo \esc_html_x( 'Fixed price', 'Label', 'snappbox' ); ?></label>
          <input type="text" id="sb_fixed_price" name="fixed_price" value="<?php echo \esc_attr( $fixed_price ); ?>" />
        </div>
        <div class="sbqs-field">
          <label for="sb_free_delivery"><?php echo \esc_html_x( 'Free delivery threshold', 'Label', 'snappbox' ); ?></label>
          <input type="text" id="sb_free_delivery" name="free_delivery" value="<?php echo \esc_attr( $free_delivery ); ?>" />
        </div>
        <div class="sbqs-field">
          <label for="sb_base_cost"><?php echo \esc_html_x( 'Base cost', 'Label', 'snappbox' ); ?></label>
          <input type="text" id="sb_base_cost" name="base_cost" value="<?php echo \esc_attr( $base_cost ); ?>" />
        </div>
        <?php /* Example: Cost per KG
        <div class="sbqs-field">
          <label for="sb_cost_per_kg"><?php echo \esc_html_x( 'Cost per KG', 'Label', 'snappbox' ); ?></label>
          <input type="text" id="sb_cost_per_kg" name="cost_per_kg" value="<?php echo \esc_attr( $cost_per_kg ); ?>" />
        </div>
        */ ?>
      </div>
      <?php
      $this->snappb_render_form_close( 5, true );
    }

    public function snappb_handle_save() : void {
      if ( ! \current_user_can( 'manage_woocommerce' ) ) {
        \wp_die(
          \esc_html__( 'Forbidden', 'snappbox' ),
          '',
          [ 'response' => 403 ]
        );
      }

      \check_admin_referer( $this->nonce_action, '_snappbox_qs_nonce' );

      $step_raw = isset( $_POST['step'] ) ? \sanitize_text_field( \wp_unslash( $_POST['step'] ) ) : '1';
      $step     = (int) $step_raw;

      $settings = \maybe_unserialize( \get_option( $this->wc_option_key ) );
      if ( ! \is_array( $settings ) ) {
        $settings = [];
      }

      switch ( $step ) {
        case 1: {
          $api = isset( $_POST['api'] ) ? \sanitize_text_field( \wp_unslash( $_POST['api'] ) ) : '';
          $settings['snappbox_api'] = $api;
          \update_option( $this->wc_option_key, $settings );
          $this->snappb_redirect_step( 2 );
          break;
        }

        case 2: {
          $cities_raw = isset( $_POST['cities'] ) ? (array) sanitize_text_field(\wp_unslash( $_POST['cities'] )) : [];
          $cities_san = \array_values( \array_filter( \array_map(
            static function( $v ) {
              return \sanitize_text_field( (string) $v );
            },
            $cities_raw
          ) ) );

          $settings['snappbox_cities'] = $cities_san;

          \update_option( $this->wc_option_key, $settings );
          $this->snappb_redirect_step( 3 );
          break;
        }

        case 3: {
          $lat = $this->snappb_normalize_number( isset( $_POST['lat'] ) ? \sanitize_text_field( \wp_unslash( $_POST['lat'] ) ) : '' );
          $lng = $this->snappb_normalize_number( isset( $_POST['lng'] ) ? \sanitize_text_field( \wp_unslash( $_POST['lng'] ) ) : '' );

          if ( $lat !== '' && \is_numeric( $lat ) ) {
            \update_option( 'snappbox_latitude', $lat );
            $settings['snappbox_latitude'] = $lat;
          }
          if ( $lng !== '' && \is_numeric( $lng ) ) {
            \update_option( 'snappbox_longitude', $lng );
            $settings['snappbox_longitude'] = $lng;
          }

          \update_option( $this->wc_option_key, $settings );
          $this->snappb_redirect_step( 4 );
          break;
        }

        case 4: {
          $store_name   = \sanitize_text_field( isset( $_POST['store_name'] )   ? \wp_unslash( $_POST['store_name'] )   : '' );
          $store_phone  = \preg_replace( '#[^0-9+\-\s]#', '', isset( $_POST['store_phone'] ) ? \sanitize_text_field( \wp_unslash( $_POST['store_phone'] ) ) : '' );
          $method_title = isset( $_POST['method_title'] ) ? \sanitize_text_field( \wp_unslash( $_POST['method_title'] ) ) : '';
          $enabled      = ( isset( $_POST['enabled'] ) && $_POST['enabled'] === 'yes' ) ? 'yes' : 'no';

          $title = ( $method_title === '' ) ? \__( 'SnappBox Shipping', 'snappbox' ) : $method_title;

          \update_option( 'snappbox_store_name',  $store_name );
          \update_option( 'snappbox_store_phone', $store_phone );

          $settings['snappbox_store_name']  = $store_name;
          $settings['snappbox_store_phone'] = $store_phone;
          $settings['enabled']              = $enabled;
          $settings['title']                = $title;

          \update_option( $this->wc_option_key, $settings );
          $this->snappb_redirect_step( 5 );
          break;
        }

        case 5: {
          $settings['ondelivery'] = ( isset( $_POST['ondelivery'] ) && $_POST['ondelivery'] === 'yes' ) ? 'yes' : 'no';

          foreach ( [ 'fixed_price', 'free_delivery', 'base_cost', 'cost_per_kg' ] as $k ) {
            if ( isset( $_POST[ $k ] ) ) {
              $settings[ $k ] = \sanitize_text_field( \wp_unslash( $_POST[ $k ] ) );
            }
          }

          \update_option( $this->wc_option_key, $settings );

          \wp_safe_redirect(
            \add_query_arg(
              [
                'page'    => 'wc-settings',
                'tab'     => 'shipping',
                'section' => 'snappbox_shipping_method',
              ],
              \admin_url( 'admin.php' )
            )
          );
          exit;
        }
      }

      // اگر به هیچ caseی نخورد (مثلاً step خارج از بازه بود)
      $this->snappb_redirect_step( 1 );
    }

    private function snappb_redirect_step( $n ) : void {
      \wp_safe_redirect( $this->snappb_url_for_step( $n ) );
      exit;
    }

    private function snappb_normalize_number( $s ) : string {
      $s = \trim( (string) $s );
      if ( $s === '' ) {
        return '';
      }
      $map = [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
      ];
      $s = \strtr( $s, $map );
      $s = \str_replace( ',', '.', $s );
      $s = \preg_replace( '/[^0-9.\-+eE]/', '', $s );
      return $s;
    }

    private function snappb_get_logo_svg() : string {
      $base_url = \defined( 'SNAPPBOX_URL' ) ? \trailingslashit( SNAPPBOX_URL ) : \plugin_dir_url( $this->plugin_file );
      $src      = $base_url . 'assets/img/sb-log.svg';
      return '<div class="sb-logo"><img src="' . \esc_url( $src ) . '" class="sb-logo" alt="' . \esc_attr_x( 'SnappBox', 'Logo alt', 'snappbox' ) . '"/></div>';
    }
  }
}

if ( \is_admin() ) {
  $GLOBALS['snappbox_quick_setup'] = new \Snappbox\SnappBox_Quick_Setup( __FILE__ );
}
