(function () {
  "use strict";

  function $(id) { return document.getElementById(id); }
  function pickCity(addr) {
    return addr.city || addr.town || addr.village || addr.municipality || addr.hamlet || addr.suburb || "";
  }
  function buildAddress(addr) {
    var parts = [];
    if (addr.house_number) parts.push(addr.house_number);
    if (addr.road) parts.push(addr.road);
    if (addr.suburb) parts.push(addr.suburb);
    return parts.join(" ");
  }

  function reverseGeocode(lat, lng) {
    var url = SNAPPBOX_MAP.reverseUrl
      + "?display=true&lat=" + encodeURIComponent(lat)
      + "&lon=" + encodeURIComponent(lng)
      + "&language=fa&type=biker";

    var headers = SNAPPBOX_MAP.reverseHeaders || {};
    return fetch(url, { headers: headers })
      .then(function (r) { if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
      .then(function (data) {
        var addr = data.address || {};
        $("customer_latitude").value = lat;
        $("customer_longitude").value = lng;
        $("customer_address").value =
          (data.result && data.result.displayName) ? data.result.displayName : buildAddress(addr);

        var sa = document.querySelector("#billing_address_1");
        if (sa && (SNAPPBOX_MAP.autoFill === "yes")) {
          sa.value = $("customer_address").value;
        }
      });
  }

  function nominatim(lat, lng) {
    var url = "https://api.teh-1.snappmaps.ir/reverse/v1"
      + "?lat=" + encodeURIComponent(lat)
      + "&lon=" + encodeURIComponent(lng)
      + "&language=en";
  
    return fetch(url, {
      headers: {
        "Accept": "application/json",
        "Authorization": "pk.eyJ1IjoibWVpaCIsImEiOiJjamY2aTJxenIxank3MzNsbmY0anhwaG9mIn0.egsUz_uibSftB0sjSWb9qw",
        "X-Smapp-Key": "aa22e8eef7d348d32f492d8a0c755f4d",
        "User-Agent": "SnappBoxWoo/1.0"
      }
    })
      .then(function (r) {
        if (!r.ok) throw new Error("HTTP " + r.status);
        return r.json();
      })
      .then(function (data) {
        var city = "";
  
        if (data && data.result && Array.isArray(data.result.components)) {
          var cityItem = data.result.components.find(function (item) {
            return item.type === "city";
          });
          if (cityItem) {
            city = cityItem.name || "";
          }
        }
  
        $("customer_city").value = city;
        $("customer_postcode").value = "";
        $("customer_state").value = "";
        $("customer_country").value = "IR";
  
      })
      .catch(function (err) {
        console.error("Reverse geocode failed:", err);
      });
  }
  

  function onSet(lat, lng) {
    reverseGeocode(lat, lng).catch(console.error);
    nominatim(lat, lng).catch(console.error);
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (typeof maplibregl === "undefined" || typeof SNAPPBOX_MAP === "undefined") return;

    var defaultLat = Number(SNAPPBOX_MAP.defaultLat || 0);
    var defaultLng = Number(SNAPPBOX_MAP.defaultLng || 0);

    maplibregl.setRTLTextPlugin(SNAPPBOX_MAP.rtlPlugin, null, true);

    var map = new maplibregl.Map({
      container: "osm-map",
      style: SNAPPBOX_MAP.styleUrl,
      center: [defaultLng, defaultLat],
      zoom: 12,
      attributionControl: true
    });

    map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), "top-right");
    window.snappboxMap = map;

    var chosenMarker = null;
    function updateMarkerTo(lat, lng) {
      if (!chosenMarker) {
        chosenMarker = new maplibregl.Marker({ anchor: "bottom" })
          .setLngLat([lng, lat])
          .addTo(map);
      } else {
        chosenMarker.setLngLat([lng, lat]);
      }
    }

    var pendingUserMove = false;

    map.on("movestart", function (e) {
      pendingUserMove = !!(e && e.originalEvent);
    });

    map.on("moveend", function () {
      try {
        var c = map.getCenter();
        var lat = c.lat, lng = c.lng;
        updateMarkerTo(lat, lng);
        if (pendingUserMove) {
          onSet(lat, lng);        
          pendingUserMove = false;
        }
      } catch (e) {
        console.error(e);
      }
    });

    var centerPinBtn = document.getElementById("center-pin");
    if (centerPinBtn) {
      centerPinBtn.addEventListener("click", function () {
        var c = map.getCenter();
        updateMarkerTo(c.lat, c.lng);
        onSet(c.lat, c.lng); 
      });
    }

    updateMarkerTo(defaultLat, defaultLng);
  });
})();