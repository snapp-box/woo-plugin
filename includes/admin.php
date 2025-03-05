<?php
require_once(SNAPPBOX_DIR . 'includes/cities-class.php');

if (! defined('ABSPATH')) exit;
class SnappBoxAdminPage
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_leaflet_scripts'));
    }
    public function enqueue_leaflet_scripts()
    {
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet/dist/leaflet.css');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet/dist/leaflet.js', array(), null, true);
    }
    public function add_admin_page()
    {
        add_menu_page(
            'SnappBox',
            'SnappBox',
            'manage_options',
            'snappbox-page',
            array($this, 'admin_page_content'),
            '',
            6
        );
    }

    public function admin_page_content()
    {
        $latitude = get_option('snappbox_latitude', '35.8037761');
        $longitude = get_option('snappbox_longitude', '51.4152466');
?>
        <div class="wrap">
            <h1><?php esc_html_e('SnappBox Admin Page', 'sb-delivery'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('snappbox-settings');
                do_settings_sections('snappbox-page');
                ?>
                <div id="map" style="height: 400px;"></div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var map = L.map('map').setView([<?php echo esc_js($latitude); ?>, <?php echo esc_js($longitude); ?>], 16);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(map);

                        var marker = L.marker([<?php echo esc_js($latitude); ?>, <?php echo esc_js($longitude); ?>], {
                            draggable: true
                        }).addTo(map);

                        marker.on('dragend', function(e) {
                            var latLng = marker.getLatLng();
                            document.getElementById('snappbox_latitude').value = latLng.lat;
                            document.getElementById('snappbox_longitude').value = latLng.lng;
                        });
                    });
                </script>
                <?php
                submit_button();
                ?>
            </form>
        </div>
<?php
    }

    public function register_settings()
    {
        register_setting('snappbox-settings', 'snappbox_api');
        register_setting('snappbox-settings', 'snappbox_latitude');
        register_setting('snappbox-settings', 'snappbox_longitude');
        register_setting('snappbox-settings', 'snappbox_cities', [
            'type' => 'array',
            'sanitize_callback' => function ($input) {
                return array_map('sanitize_text_field', (array) $input);
            },
            'default' => [],
        ]);
        add_settings_section(
            'snappbox-page-section',
            __('SnappBox Settings Section', 'sb-delivery'),
            null,
            'snappbox-page'
        );

        add_settings_field(
            'snappbox_api',
            __('SnappBox API Key', 'sb-delivery'),
            array($this, 'snappbox_api_callback'),
            'snappbox-page',
            'snappbox-page-section'
        );
        add_settings_field(
            'snappbox_latitude',
            __('Latitude', 'sb-delivery'),
            array($this, 'snappbox_latitude_callback'),
            'snappbox-page',
            'snappbox-page-section'
        );

        add_settings_field(
            'snappbox_longitude',
            __('Longitude', 'sb-delivery'),
            array($this, 'snappbox_longitude_callback'),
            'snappbox-page',
            'snappbox-page-section'
        );
        add_settings_field(
            'snappbox_cities',
            __('Cities', 'sb-delivery'),
            array($this, 'snappbox_cities_callback'),
            'snappbox-page',
            'snappbox-page-section'
        );
    }

    public function snappbox_api_callback()
    {
        $value = get_option('snappbox_api', '');
        echo '<input type="text" name="snappbox_api" value="' . esc_attr($value) . '" />';
    }
    public function snappbox_latitude_callback()
    {
        $value = get_option('snappbox_latitude', '51.5077286');
        echo '<input type="text" id="snappbox_latitude" name="snappbox_latitude" value="' . esc_attr($value) . '" readonly />';
    }

    public function snappbox_cities_callback()
    {
        $stored_cities = get_option('snappbox_cities', []);
        if (!is_array($stored_cities)) {
            $stored_cities = []; 
        }

        $latitude = get_option('snappbox_latitude', '35.8037761');
        $longitude = get_option('snappbox_longitude', '51.4152466');
        

        if ($latitude && $longitude) {
            $citiesObj = new SnappBoxCities();
            $cities = $citiesObj->get_delivery_category($latitude, $longitude);

            echo '<select name="snappbox_cities[]" multiple style="width:500px">';
            foreach ($cities->cities as $city) {
                if ($city->cityName) {
                    $selected = in_array($city->cityKey, $stored_cities) ? 'selected' : '';
                    echo '<option value="' . esc_attr($city->cityKey) . '" ' . $selected . '>' . esc_html($city->cityName) . '</option>';
                }
            }
            echo '</select>';
        }
    }

    public function snappbox_longitude_callback()
    {
        $value = get_option('snappbox_longitude', '-0.1279688');
        echo '<input type="text" id="snappbox_longitude" name="snappbox_longitude" value="' . esc_attr($value) . '" readonly />';
    }
}
