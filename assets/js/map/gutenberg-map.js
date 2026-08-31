(function (window, document) {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var interval = window.setInterval(function () {
            var checkoutForm = document.querySelector('[data-block-name="woocommerce/checkout-billing-address-block"]')
                || document.querySelector(".wc-block-checkout__form");

            if (!checkoutForm || document.getElementById("snappbox-map") || !window.maplibregl) {
                return;
            }

            var config = window.SNAPPBOX_BLOCK_MAP || {};
            var mapContainer = document.createElement("div");
            var title = document.createElement("h3");
            var mapElement = document.createElement("div");
            var latitudeInput = document.createElement("input");
            var longitudeInput = document.createElement("input");

            title.textContent = config.title || "Select your location";
            title.style.marginTop = "20px";
            mapElement.id = "snappbox-map";
            mapElement.style.height = "400px";
            mapElement.style.marginBottom = "20px";
            latitudeInput.type = longitudeInput.type = "hidden";
            latitudeInput.name = latitudeInput.id = "customer_latitude";
            longitudeInput.name = longitudeInput.id = "customer_longitude";
            mapContainer.append(title, mapElement, latitudeInput, longitudeInput);
            checkoutForm.appendChild(mapContainer);

            var initial = { lat: 35.6892, lng: 51.3890 };
            var map = new window.maplibregl.Map({
                container: mapElement,
                style: config.styleUrl,
                center: [initial.lng, initial.lat],
                zoom: 12
            });
            var marker = new window.maplibregl.Marker({ anchor: "bottom" })
                .setLngLat([initial.lng, initial.lat])
                .addTo(map);

            function pinToCenter() {
                var center = map.getCenter();
                marker.setLngLat(center);
                latitudeInput.value = center.lat.toFixed(9);
                longitudeInput.value = center.lng.toFixed(9);
            }

            latitudeInput.value = initial.lat;
            longitudeInput.value = initial.lng;
            map.on("move", function () { marker.setLngLat(map.getCenter()); });
            map.on("moveend", pinToCenter);
            window.clearInterval(interval);
        }, 500);
    });
}(window, document));
