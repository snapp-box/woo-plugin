<?php

namespace Snappbox\Map;

class SnappBoxMap
{
    public function snappbox_map(array $args = [])
    {
        $args = \wp_parse_args($args, [
            'latitude' => '',
            'longitude' => '',
            'mapName' => 'snappbox-map',
            'className' => '',
            'latInputName' => 'woocommerce_snappbox_shipping_method_snappbox_latitude',
            'longInputName' => 'woocommerce_snappbox_shipping_method_snappbox_longitude',
            'width' => '100%',
            'height' => '400px',
            'guidenceMap' => true,
            'movable' => true,
            'showPolygon' => true,
            'centerPinId' => 'center-pin',
            'addressInputId' => 'branch-address',
            'autoFillInputId' => '',
            'autoFill' => '',
            'reverseGeocode' => true,
            'nearbySubmitSelector' => '',
            'validateInitialLocation' => false,
        ]);
        $this->snappb_enqueue_maplibre_assets();

        $config = [
            'containerId' => (string) $args['mapName'],
            'latitude' => (float) $args['latitude'],
            'longitude' => (float) $args['longitude'],
            'latInputName' => (string) $args['latInputName'],
            'longInputName' => (string) $args['longInputName'],
            'movable' => (bool) $args['movable'],
            'showPolygon' => (bool) $args['showPolygon'],
            'centerPinId' => (string) $args['centerPinId'],
            'addressInputId' => (string) $args['addressInputId'],
            'autoFillInputId' => (string) $args['autoFillInputId'],
            'autoFill' => (string) $args['autoFill'],
            'reverseGeocode' => (bool) $args['reverseGeocode'],
            'nearbySubmitSelector' => (string) $args['nearbySubmitSelector'],
            'validateInitialLocation' => (bool) $args['validateInitialLocation'],
            'styleUrl' => SNAPPBOX_MAP_URL,
            'rtlPluginUrl' => \trailingslashit(SNAPPBOX_URL) . 'assets/js/map/mapbox-gl-rtl-text.js',
            'reverseUrl' => SNAPPBOX_REVERSE_URL,
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'nearbyNonce' => \wp_create_nonce('snappbox_nearby'),
            'reverseHeaders' => [
                'Accept' => 'application/json',
                'X-Smapp-Key' => \Snappbox\EnvConfig::get('SNAPPBOX_SMAPP_KEY'),
                'Authorization' => \Snappbox\EnvConfig::get('SNAPPBOX_SMAPP_AUTHORIZATION'),
            ],
            'messages' => [
                'drawPolygon' => \__('Draw area', 'snappbox'),
                'deletePolygon' => \__('Delete area', 'snappbox'),
                'polygonMustCover' => \__('The selected area must cover the saved location.', 'snappbox'),
            ],
        ];
?>
        <div id="<?php echo \esc_attr($args['mapName']); ?>"
            class="<?php echo \esc_attr($args['className']); ?>"
            data-snappbox-map="<?php echo \esc_attr(\wp_json_encode($config)); ?>"
            style="height:<?php echo \esc_attr($args['height']); ?>;width:<?php echo \esc_attr($args['width']); ?>;position:relative;">
            <?php if ($args['showPolygon'] == true) { ?>
                <div class="map-helper">
                    <p><?php _e('Move the pin on the map to pinpoint the stores location.', 'snappbox'); ?></p>
                </div>
            <?php }
            if ($args['movable']) : ?>
                <button id="<?php echo \esc_attr($args['centerPinId']); ?>" type="button" aria-label="<?php \esc_attr_e('Set this location', 'snappbox'); ?>"></button>
            <?php endif; ?>
            <input type="hidden" data-snappbox-polygon
                name="woocommerce_snappbox_shipping_method[polygon_coords]"
                id="woocommerce_snappbox_shipping_method_polygon_coords"
                value="<?php echo \esc_attr(\get_option('polygon_coords', '')); ?>">
            <?php if ($args['guidenceMap']) : ?>
                <div class="guidence-map"><img src="<?php echo \esc_url(SNAPPBOX_URL . '/assets/img/Vector.svg'); ?>" alt="">
                    <div class="guidence-text clearfix">
                        <p><?php \esc_html_e('Place points on the map to define your service area.', 'snappbox'); ?></p>
                        <a href="#"><?php \esc_html_e('Got it', 'snappbox'); ?></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    public function snappb_zone_alert_modal()
    {
    ?><div id="snapp-modal" style="display:none;">
            <div class="snapp-modal-content">
                <span class="snapp-close">&times;</span>
                <p id="snapp-modal-message"></p>
            </div>
        </div><?php
            }

            protected function snappb_enqueue_maplibre_assets()
            {
                if (!\wp_script_is('maplibre-gl', 'registered')) {
                    \wp_register_script('maplibre-gl', \trailingslashit(SNAPPBOX_URL) . 'assets/js/map/maplibre-gl.js', [], '5.9.0', true);
                }
                if (!\wp_style_is('maplibre-gl', 'registered')) {
                    \wp_register_style('maplibre-gl', \trailingslashit(SNAPPBOX_URL) . 'assets/css/maplibre-gl.css', [], '5.9.0');
                }
                if (!\wp_script_is('maplibre-draw', 'registered')) {
                    \wp_register_script('maplibre-draw', \trailingslashit(SNAPPBOX_URL) . 'assets/js/map/mapbox-gl-draw.js', ['maplibre-gl'], '1.5.0', true);
                }
                if (!\wp_style_is('maplibre-draw-css', 'registered')) {
                    \wp_register_style('maplibre-draw-css', \trailingslashit(SNAPPBOX_URL) . 'assets/css/mapbox-gl-draw.css', [], '1.5.0');
                }
                \wp_enqueue_script('maplibre-gl');
                \wp_enqueue_style('maplibre-gl');
                \wp_enqueue_script('maplibre-draw');
                \wp_enqueue_style('maplibre-draw-css');
                \wp_enqueue_script('turf', \trailingslashit(SNAPPBOX_URL) . 'assets/js/map/turf.min.js', [], '7.2.0', true);
                \wp_enqueue_script(
                    'snappbox-map-component',
                    \trailingslashit(SNAPPBOX_URL) . 'assets/js/map/snappbox-map-component.js',
                    ['maplibre-gl', 'maplibre-draw', 'turf'],
                    '1.0.8',
                    true
                );
            }
        }
