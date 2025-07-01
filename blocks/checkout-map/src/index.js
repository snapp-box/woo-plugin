import { registerCheckoutBlockExtensionCallbacks } from '@woocommerce/extend-cart-checkout-block';
import { useEffect } from '@wordpress/element';

registerCheckoutBlockExtensionCallbacks('snappbox/checkout-map', () => {
    return {
        Checkout: () => {
            useEffect(() => {
                if (!window.L) {
                    const leafletScript = document.createElement('script');
                    leafletScript.src = 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js';
                    leafletScript.onload = initializeMap;
                    document.body.appendChild(leafletScript);

                    const leafletCss = document.createElement('link');
                    leafletCss.rel = 'stylesheet';
                    leafletCss.href = 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css';
                    document.head.appendChild(leafletCss);
                } else {
                    initializeMap();
                }

                function initializeMap() {
                    const defaultLat = 35.6892;
                    const defaultLng = 51.3890;
                    const map = L.map('osm-map').setView([defaultLat, defaultLng], 12);
                    L.tileLayer('https://raster.snappmaps.ir/styles/snapp-style/{z}/{x}/{y}{r}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(map);
                    const marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
                    marker.on('dragend', () => {
                        const position = marker.getLatLng();
                        document.getElementById('customer_latitude').value = position.lat;
                        document.getElementById('customer_longitude').value = position.lng;
                    });
                    document.getElementById('customer_latitude').value = defaultLat;
                    document.getElementById('customer_longitude').value = defaultLng;
                }
            }, []);

            return (
                <div>
                    <h3>موقعیت خود را انتخاب کنید</h3>
                    <div id="osm-map" style={{ height: '400px' }}></div>
                    <input type="hidden" id="customer_latitude" name="customer_latitude" />
                    <input type="hidden" id="customer_longitude" name="customer_longitude" />
                </div>
            );
        }
    };
});
