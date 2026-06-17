<?php

namespace Snappbox\Map;

class SnappBoxMap
{
    public function snappbox_map(array $args = [])
    {

        $args = wp_parse_args($args, [
            'latitude'      => '',
            'longitude'     => '',
            'mapName'       => 'snappbox-map',
            'latInputName'  => 'woocommerce_snappbox_shipping_method_snappbox_latitude',
            'longInputName' => 'woocommerce_snappbox_shipping_method_snappbox_longitude',
            'width'         => '100%',
            'height'        => '400px',
            'guidenceMap'  => true,
            'movable'       => true,
            'showPolygon'   => true,
        ]);

        $polygon_coords = get_option('polygon_coords', '');

        $lat = !empty($args['latitude']) ? $args['latitude'] : '';
        $lng = !empty($args['longitude']) ? $args['longitude'] : '';

        $this->snappb_enqueue_maplibre_assets();

?>
        <div id="<?php echo esc_attr($args['mapName']); ?>"
            style="
            height: <?php echo esc_attr($args['height']); ?>;
            width: <?php echo esc_attr($args['width']); ?>;
            position: relative;
        ">
            <?php if ($args['movable'] == true) { ?>
                <button id="center-pin" type="button" aria-label="<?php \esc_attr_e('Set this location', 'snappbox'); ?>"></button>
            <?php } ?>
            <input type="hidden"
                name="woocommerce_snappbox_shipping_method[polygon_coords]"
                id="woocommerce_snappbox_shipping_method_polygon_coords"
                value="<?php echo esc_attr($polygon_coords); ?>">
            <?php if ($args['guidenceMap'] === true) { ?>
                <div class="guidence-map">
                    <img src="<?php echo (SNAPPBOX_URL . '/assets/img/Vector.svg'); ?>" />
                    <div class="guidence-text clearfix">
                        <p>با گذاشتن نقطه های مختلف روی نقشه و ترسیم ناحیه مورد نظر ، محدوده سرویس دهی خود را روی نقشه مشخص کنید.</p>
                        <a href="">متوجه شدم</a>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php

        $this->snappb_enqueue_maplibre_inline_script(
            $lat,
            $lng,
            $args
        );
    }
    public function snappb_zone_alert_modal()
    {
    ?>
        <div id="snapp-modal" style="display:none;">
            <div class="snapp-modal-content">
                <span class="snapp-close">&times;</span>
                <p id="snapp-modal-message"></p>
            </div>
        </div>
<?php
    }
    public function snappb_enqueue_maplibre_inline_script($lat, $lng, $args)
    {
        $defaultLat = \wp_json_encode((float) $lat);
        $defaultLng = \wp_json_encode((float) $lng);
        $rtl_plugin_url = \esc_url(\trailingslashit(SNAPPBOX_URL) . 'assets/js/mapbox-gl-rtl-text.js');
        $rtl_plugin_url_js = \wp_json_encode($rtl_plugin_url);

        $container = $args['mapName'];
        $latInputName = $args['latInputName'];
        $longInputName = $args['longInputName'];
        $movable = $args['movable'];
        $showPolygon = $args['showPolygon'];

        $autoFill   = ! empty($settings['autofill']) ? (string) $settings['autofill'] : '';
        $movable_js = $movable ? 'true' : 'false';
        $inline_js  = 'document.addEventListener("DOMContentLoaded", function() {
    
            if (typeof maplibregl === "undefined") { console.error("MapLibre not loaded"); return; }
    
            const defaultLat = ' . $defaultLat . ';
            const defaultLng = ' . $defaultLng . ';
            const movablePin = ' . $movable_js . ';
            
            // MAP INIT
            const map = new maplibregl.Map({
                container:"' . $container . '",
                style:"' . SNAPPBOX_MAP_URL . '",
                center:[defaultLng, defaultLat],
                zoom:16,
                attributionControl:true,
                dragPan: ' . $movable_js . ',
                scrollZoom: ' . $movable_js . ',
                boxZoom: ' . $movable_js . ',
                dragRotate: ' . $movable_js . ',
                keyboard: ' . $movable_js . ',
                doubleClickZoom: ' . $movable_js . ',
                touchZoomRotate: ' . $movable_js . '
            });
            

    
            maplibregl.setRTLTextPlugin(' . $rtl_plugin_url_js . ', null, true);
    
            map.getCanvas().style.cursor = "pointer";
    
            // INPUT UPDATE
            function updateInputs(lat, lng){
                console.log(lat)
                var latInput=document.querySelector(\'[name="' . $latInputName . '"]\');
                var lngInput=document.querySelector(\'[name="' . $longInputName . '"]\');
                if(latInput) latInput.value=Number(lat).toFixed(9);
                if(lngInput) lngInput.value=Number(lng).toFixed(9);
            }
    
            // SAVED LOCATION PIN (MAP MARKER)
            const savedMarker = new maplibregl.Marker({ draggable: false })
                .setLngLat([defaultLng, defaultLat])
                .addTo(map);
    
            // SAVE LOCATION (CALLED ONLY WHEN BUTTON CLICKED)
            function onSet(lat, lng){
                updateInputs(lat, lng);
                savedMarker.setLngLat([lng, lat]);
            }
            window.snappboxMaps = window.snappboxMaps || {};

            window.snappboxMaps["' . $container . '"] = {
                map: map,
                marker: savedMarker
            };
            updateInputs(defaultLat, defaultLng);
    
            // AJAX NEARBY (NO SAVING)
            let moveTimeout;
            function runNearbyAjax(c){
                jQuery.post(ajaxurl, {
                    action: "snapp_nearby",
                    lat: c.lat,
                    lng: c.lng
                }, function(res){
                    if (res && res.data && res.data.message) {
                        jQuery("#snapp-modal").css("display", "block");
                        jQuery("#snapp-modal p").html(res.data.message);
                    } else {
                        jQuery("#snapp-modal").css("display", "none");
                    }
                });
            }
                
            if(movablePin === true) {
                map.on("moveend", function(){
                    clearTimeout(moveTimeout);
                    moveTimeout = setTimeout(() => {
                        var c = map.getCenter();
                       runNearbyAjax(c);
                    }, 150);
                });
            }
    
            // DRAW + POLYGON VALIDATION
            let Draw = null;
            let currentPolygonCoords = null;
    
            map.on("load", function () {
    
                map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), "top-right");
    
                Draw = new MapboxDraw({
                    displayControlsDefault: false,
                    controls: { polygon: "' . $showPolygon . '", trash: "' . $showPolygon . '" }
                });
    
                map.addControl(Draw, "bottom-left");

                const drawCtrl = document.querySelector(".mapboxgl-ctrl-group"); // the draw group
                const polyBtn = document.querySelector(".mapbox-gl-draw_polygon");
                const trashBtn = document.querySelector(".mapbox-gl-draw_trash");
                const mapGuide = document.querySelector(".guidence-map");
                let guideShown = false;
                if (polyBtn) {
                    polyBtn.setAttribute("title", "رسم محدوده");
                    polyBtn.setAttribute("aria-label", "رسم محدوده");
                    polyBtn.textContent = "ایجاد محدوده"; 
                }
                 
                if (trashBtn) {
                    trashBtn.setAttribute("title", "حذف محدوده");
                    trashBtn.setAttribute("aria-label", "حذف");
                    trashBtn.textContent = "حذف محدوده";
                }
                

                // Load saved polygon
                const polyInputEl = document.querySelector(\'[name="woocommerce_snappbox_shipping_method_polygon_coords"]\');
                const savedPolygon = polyInputEl ? polyInputEl.value : "";
    
                if (savedPolygon && savedPolygon !== "") {
                    try {
                        let coords = JSON.parse(savedPolygon);
                        if (!Array.isArray(coords[0][0])) { coords = [coords]; }
                        currentPolygonCoords = coords[0];
    
                        Draw.add({
                            id: "saved-polygon",
                            type: "Feature",
                            properties: {},
                            geometry: { type: "Polygon", coordinates: coords }
                        });
    
                    } catch (e) {
                        console.error("Invalid polygon_coords JSON", e);
                    }
                }
    
                map.on("draw.create", validatePolygon);
                map.on("draw.update", validatePolygon);
                map.on("draw.delete", function(){ 
                    updateInputsPolygon(null);
                    jQuery(".woocommerce-save-button").removeAttr("disabled");

                });
    
                function validatePolygon() {
                    const data = Draw.getAll();
                    if (data.features.length === 0) {
                        updateInputsPolygon(null);
                        return;
                    }
    
                    if (data.features.length > 1) {
                        Draw.delete(data.features[0].id);
                    }
    
                    const polygon = data.features[0].geometry.coordinates[0];
    
                    // Current saved location pin
                    const saved = savedMarker.getLngLat();
                    const lat = saved.lat;
                    const lng = saved.lng;
                    console.log(typeof turf)
                    // Check inside polygon
                    if (typeof turf !== "undefined") {
                        const pt = turf.point([lng, lat]);
                        const poly = turf.polygon([polygon]);
   
                        const inside = turf.booleanPointInPolygon(pt, poly);
    
                        if (!inside) {
                            jQuery("#snapp-modal").css("display", "block");
                            jQuery("#snapp-modal p").html("محدوده مشخص شده باید لوکیشن ثبت‌ شده را پوشش دهد.");
                            Draw.delete(data.features[0].id);
                            updateInputsPolygon(null);
                            return;
                        }
                        else{
                            jQuery("#snapp-modal").css("display", "none");
                            jQuery(".woocommerce-save-button").removeAttr("disabled");
                        }
                        
                    }
    
                    updateInputsPolygon(polygon);
                }
    
                function updateInputsPolygon(coords) {
                    const polyInput = document.querySelector(\'[name="woocommerce_snappbox_shipping_method_polygon_coords"]\');
                    currentPolygonCoords = coords || null;
                    if (polyInput) {
                        polyInput.value = coords ? JSON.stringify(coords) : "";
                    }
                }
    
            });
    
            // SAVE LOCATION BY BUTTON CLICK
            var centerPinBtn=document.getElementById("center-pin");
            if(centerPinBtn){
                centerPinBtn.addEventListener("click", function(){
                    var c = map.getCenter();
                    
                    // If polygon exists, check
                    if (currentPolygonCoords && typeof turf !== "undefined") {
                        try {
                            var pt = turf.point([c.lng, c.lat]);
                            var poly = turf.polygon([currentPolygonCoords]);
                            var inside = turf.booleanPointInPolygon(pt, poly);
                            if (!inside) {
                                jQuery("#snapp-modal").css("display", "block");
                                jQuery("#snapp-modal p").html("محدوده مشخص شده باید لوکیشن ثبت‌شده را پوشش دهد.");
                                return;
                            }
                        } catch (e) {}
                    }
    
                    // FINAL SAVE
                    onSet(c.lat, c.lng);
                    reverseGeocode(c.lat, c.lng)
                });
            }

            function reverseGeocode(lat, lng) {
                const SNAPPBOX_MAP = {
                    autoFill: "' . $autoFill . '",
                    reverseHeaders: {
                        Accept: "application/json",
                        "X-Smapp-Key": "aa22e8eef7d348d32f492d8a0c755f4d",
                        Authorization:
                        "pk.eyJ1IjoibWVpaCIsImEiOiJjamY2aTJxenIxank3MzNsbmY0anhwaG9mIn0.egsUz_uibSftB0sjSWb9qw",
                    },
                    nominatimUrl: "' . \Snappbox\EnvConfig::get('SNAPPBOX_MAP_NOMINATIM_URL') . '",
                    };
                var url = "' . SNAPPBOX_REVERSE_URL . '"
                + "?display=true&lat=" + encodeURIComponent(lat)
                + "&lon=" + encodeURIComponent(lng)
                + "&language=fa&type=biker";

                var headers = SNAPPBOX_MAP.reverseHeaders || {};
                return fetch(url, { headers: headers })
                .then(function (r) { if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
                .then(function (data) {
                    jQuery("#branch-address").val(data.result.displayName);
                    var sa = document.querySelector("#branch-address");
                    if (sa && (SNAPPBOX_MAP.autoFill === "yes")) {
                    sa.value = data.result.displayName;
                    }
                });
            }
    
        });';

        // MODAL CLOSE
        $inline_js .= '
            jQuery(document).on("click", ".snapp-close", function(){ jQuery("#snapp-modal").fadeOut(200); });
            jQuery(document).on("click", ".snapp-map-close", function(){ jQuery(".snappbox-modal").fadeOut(200); });
            jQuery(document).on("click", "#snapp-modal", function(e){ if(e.target.id==="snapp-modal"){ jQuery("#snapp-modal").fadeOut(200);} });
            jQuery("input.snappbox-hidden-field").closest("tr").hide();
        ';

        \wp_add_inline_script('maplibre', $inline_js);
    }
    protected function snappb_enqueue_maplibre_assets()
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
                \trailingslashit(SNAPPBOX_URL) . 'assets/css/leaflet.css',
                [],
                null
            );
        }
        \wp_enqueue_style('maplibre');

        // Load MapboxDraw (works with MapLibre)
        if (! \wp_script_is('maplibre-draw', 'registered')) {
            \wp_register_script(
                'maplibre-draw',
                \trailingslashit(SNAPPBOX_URL) . 'assets/js/mapbox-gl-draw.js',
                ['maplibre'],
                null,
                true
            );
        }
        \wp_enqueue_script('maplibre-draw');

        // Draw CSS
        if (! \wp_style_is('maplibre-draw-css', 'registered')) {
            \wp_register_style(
                'maplibre-draw-css',
                \trailingslashit(SNAPPBOX_URL) . 'assets/css/mapbox-gl-draw.css'
            );
        }
        \wp_enqueue_style('maplibre-draw-css');

        \wp_enqueue_script(
            'turf',
            \trailingslashit(SNAPPBOX_URL) . 'assets/js/turf.min.js',
            [],
            null,
            true
        );
    }
}
