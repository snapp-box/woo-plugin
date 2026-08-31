(function (window, document) {
  "use strict";

  function getConfig(el) {
    try { return JSON.parse(el.dataset.snappboxMap || "{}"); }
    catch (error) { console.error("Invalid SnappBox map configuration", error); return null; }
  }
  function byName(name) {
    if (!name) return null;
    return document.querySelector('[name="' + CSS.escape(name) + '"]');
  }
  function modal(message) {
    var el = document.getElementById("snapp-modal");
    if (!el) return;
    if (message) {
      var text = el.querySelector("#snapp-modal-message, p");
      if (text) text.textContent = message;
      el.style.display = "block";
    } else { el.style.display = "none"; }
  }
  function disable(selector, value) {
    if (!selector) return;
    document.querySelectorAll(selector).forEach(function (el) { el.disabled = value; });
  }
  function normalizedPolygon(value) {
    if (!value) return null;
    var coords = typeof value === "string" ? JSON.parse(value) : value;
    if (!Array.isArray(coords) || !coords.length) return null;
    return Array.isArray(coords[0][0]) ? coords : [coords];
  }

  function initialize(container) {
    if (container.dataset.snappboxInitialized === "true") return;
    var config = getConfig(container);
    if (!config || !window.maplibregl) return;
    container.dataset.snappboxInitialized = "true";

    window.maplibregl.setRTLTextPlugin(config.rtlPluginUrl, null, true);
    var map = new window.maplibregl.Map({
      container: container, style: config.styleUrl,
      center: [Number(config.longitude), Number(config.latitude)], zoom: 16,
      attributionControl: true, dragPan: config.movable, scrollZoom: config.movable,
      boxZoom: config.movable, dragRotate: config.movable, keyboard: config.movable,
      doubleClickZoom: config.movable, touchZoomRotate: config.movable
    });
    map.getCanvas().style.cursor = "pointer";
    var marker = new window.maplibregl.Marker({ draggable: false, anchor: "bottom" })
      .setLngLat([Number(config.longitude), Number(config.latitude)]).addTo(map);
    var draw = null;
    var currentPolygon = null;
    var markerLocked = false;
    var polygonInput = container.querySelector("[data-snappbox-polygon]");

    function updateCoordinates(lat, lng) {
      var latInput = byName(config.latInputName), lngInput = byName(config.longInputName);
      if (latInput) latInput.value = Number(lat).toFixed(9);
      if (lngInput) lngInput.value = Number(lng).toFixed(9);
    }
    function updatePolygon(coords) {
      currentPolygon = coords || null;
      var value = coords ? JSON.stringify(coords) : "";
      if (polygonInput) polygonInput.value = value;
      var editInput = document.getElementById("polygon");
      if (editInput && editInput !== polygonInput) editInput.value = value;
    }
    function setPolygon(value) {
      if (!draw) return false;
      draw.deleteAll();
      if (typeof value === "undefined") {
        var editInput = document.getElementById("polygon");
        value = editInput && editInput.value ? editInput.value : polygonInput && polygonInput.value;
      }
      try {
        var coords = normalizedPolygon(value);
        if (!coords) { updatePolygon(null); return false; }
        draw.add({ type: "Feature", properties: {}, geometry: { type: "Polygon", coordinates: coords } });
        updatePolygon(coords[0]);
        return true;
      } catch (error) {
        console.error("Invalid polygon coordinates", error); updatePolygon(null); return false;
      }
    }
    function contains(coords, lng, lat) {
      if (!coords || !window.turf) return true;
      return window.turf.booleanPointInPolygon(window.turf.point([lng, lat]), window.turf.polygon([coords]));
    }
    function validatePolygon() {
      var data = draw.getAll();
      if (!data.features.length) { updatePolygon(null); return; }
      while (data.features.length > 1) draw.delete(data.features.shift().id);
      var coords = data.features[0].geometry.coordinates[0], saved = marker.getLngLat();
      if (!contains(coords, saved.lng, saved.lat)) {
        modal(config.messages.polygonMustCover); draw.delete(data.features[0].id); updatePolygon(null); return;
      }
      modal(); updatePolygon(coords);
    }
    function reverseGeocode(lat, lng) {
      var url = config.reverseUrl + "?display=true&lat=" + encodeURIComponent(lat) +
        "&lon=" + encodeURIComponent(lng) + "&language=fa&type=biker";
      return fetch(url, { headers: config.reverseHeaders || {} }).then(function (response) {
        if (!response.ok) throw new Error("HTTP " + response.status); return response.json();
      }).then(function (data) {
        var name = data && data.result ? data.result.displayName : "";
        var address = config.addressInputId && document.getElementById(config.addressInputId);
        var autofill = config.autoFillInputId && document.getElementById(config.autoFillInputId);
        if (address) address.value = name;
        if (autofill && config.autoFill === "yes") {
          autofill.value = name; autofill.dispatchEvent(new Event("change", { bubbles: true }));
        }
      });
    }
    function validateNearby(center) {
      var body = new URLSearchParams({
        action: "snapp_nearby", nonce: config.nearbyNonce,
        lat: center.lat, lng: center.lng
      });
      fetch(config.ajaxUrl, { method: "POST", credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }, body: body.toString()
      }).then(function (response) { return response.json(); }).then(function (response) {
        var message = response && response.data && response.data.message;
        modal(message || ""); disable(config.nearbySubmitSelector, Boolean(message));
      }).catch(function (error) { console.error("Nearby location validation failed", error); });
    }
    function saveCenter() {
      if (markerLocked) return;
      var center = map.getCenter();
      if (!contains(currentPolygon, center.lng, center.lat)) { modal(config.messages.polygonMustCover); return; }
      updateCoordinates(center.lat, center.lng); marker.setLngLat([center.lng, center.lat]);
      if (config.reverseGeocode) reverseGeocode(center.lat, center.lng).catch(console.error);
    }

    window.snappboxMaps = window.snappboxMaps || {};
    window.snappboxMaps[config.containerId] = {
      map: map,
      marker: marker,
      setPolygon: setPolygon,
      setMarkerLocked: function (locked) { markerLocked = Boolean(locked); }
    };
    updateCoordinates(config.latitude, config.longitude);
    if (window.ResizeObserver) new ResizeObserver(function () {
      if (container.offsetParent !== null) map.resize();
    }).observe(container);
    var pendingUserMove = false;
    if (config.movable) {
      map.on("movestart", function (event) {
        pendingUserMove = Boolean(event && event.originalEvent);
      });
      map.on("moveend", function () {
        if (markerLocked) return;
        var center = map.getCenter();
        marker.setLngLat([center.lng, center.lat]);
        if (!pendingUserMove) return;
        pendingUserMove = false;

        if (!contains(currentPolygon, center.lng, center.lat)) {
          modal(config.messages.polygonMustCover);
          return;
        }

        updateCoordinates(center.lat, center.lng);
        if (config.reverseGeocode) reverseGeocode(center.lat, center.lng).catch(console.error);
        validateNearby(center);
      });
    }
    var centerButton = config.centerPinId && document.getElementById(config.centerPinId);
    if (centerButton) centerButton.addEventListener("click", saveCenter);

    map.on("load", function () {
      map.addControl(new window.maplibregl.NavigationControl({ visualizePitch: true }), "top-right");
      draw = new window.MapboxDraw({ displayControlsDefault: false,
        controls: { polygon: config.showPolygon, trash: config.showPolygon } });
      map.addControl(draw, "bottom-left");
      var polygonButton = container.querySelector(".mapbox-gl-draw_polygon");
      var trashButton = container.querySelector(".mapbox-gl-draw_trash");
      if (polygonButton) {
        polygonButton.textContent = config.messages.drawPolygon;
        polygonButton.title = config.messages.drawPolygon;
        polygonButton.setAttribute("aria-label", config.messages.drawPolygon);
      }
      if (trashButton) {
        trashButton.textContent = config.messages.deletePolygon;
        trashButton.title = config.messages.deletePolygon;
        trashButton.setAttribute("aria-label", config.messages.deletePolygon);
      }
      setPolygon();
      map.on("draw.create", validatePolygon); map.on("draw.update", validatePolygon);
      map.on("draw.delete", function () { updatePolygon(null); });
      if (config.validateInitialLocation) validateNearby(map.getCenter());
    });
  }

  function initializeAll(root) { (root || document).querySelectorAll("[data-snappbox-map]").forEach(initialize); }
  document.addEventListener("click", function (event) {
    if (event.target.closest(".snapp-close, .snapp-map-close") || event.target.id === "snapp-modal") modal();
  });
  window.SnappBoxMap = { initialize: initializeAll };
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", function () { initializeAll(); });
  else initializeAll();
})(window, document);
