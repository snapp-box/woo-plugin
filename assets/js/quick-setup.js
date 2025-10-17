// داخل quick-setup.js — بخش map logic
(function($){
    if (typeof SNB_QS !== 'undefined' && SNB_QS.isStep3 && typeof maplibregl !== 'undefined') {
      // URL پلاگین RTL (مثال)
      var rtlPluginUrl = 'https://unpkg.com/@mapbox/mapbox-gl-rtl-text@0.3.0/dist/mapbox-gl-rtl-text.js';
  
      // ست کردن RTL plugin
      try {
        maplibregl.setRTLTextPlugin(rtlPluginUrl, true);
      } catch(e) {
        console.error('RTL plugin failed to load', e);
      }
  
      var latInput = document.getElementById('sb_lat');
      var lngInput = document.getElementById('sb_lng');
      var mapStyle = SNB_QS.mapStyle || '';
  
      var lat = parseFloat(latInput ? latInput.value : '0') || 0;
      var lng = parseFloat(lngInput ? lngInput.value : '0') || 0;
  
      var map = new maplibregl.Map({
        container: 'sbqs-map',
        style: mapStyle,
        center: [lng, lat],
        zoom: 16,
        attributionControl: true
      });
  
      try {
        map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');
      } catch(e){}
  
      function updateInputsFrom(center) {
        if (!latInput || !lngInput) return;
        latInput.value = Number(center.lat).toFixed(7);
        lngInput.value = Number(center.lng).toFixed(7);
      }
      function setToCenter() {
        updateInputsFrom(map.getCenter());
      }
  
      map.on('load', setToCenter);
      map.on('moveend', setToCenter);
  
      map.on('click', function(e){
        map.easeTo({ center: e.lngLat });
        updateInputsFrom(e.lngLat);
      });
  
      var pinBtn = document.getElementById('sbqs-center-pin');
      if (pinBtn) {
        pinBtn.addEventListener('click', function(){
          setToCenter();
        });
      }
    }
  })(jQuery);
  