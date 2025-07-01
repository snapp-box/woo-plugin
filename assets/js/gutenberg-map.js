document.addEventListener('DOMContentLoaded', function () {
    let interval = setInterval(() => {
        const checkoutForm = document.querySelector('[data-block-name="woocommerce/checkout-billing-address-block"]') || document.querySelector('.wc-block-checkout__form');

        if (checkoutForm && !document.getElementById('snappbox-map')) {
            const mapContainer = document.createElement('div');
            mapContainer.innerHTML = `
                <h3 style="margin-top:20px">موقعیت خود را انتخاب کنید</h3>
                <div id="snappbox-map" style="height: 400px; margin-bottom: 20px;"></div>
                <input type="hidden" name="customer_latitude" id="customer_latitude">
                <input type="hidden" name="customer_longitude" id="customer_longitude">
            `;
            checkoutForm.appendChild(mapContainer);

            const map = L.map('snappbox-map').setView([35.6892, 51.3890], 12);
            L.tileLayer('https://raster.snappmaps.ir/styles/snapp-style/{z}/{x}/{y}{r}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            const marker = L.marker([35.6892, 51.3890], { draggable: true }).addTo(map);
            document.getElementById('customer_latitude').value = 35.6892;
            document.getElementById('customer_longitude').value = 51.3890;

            marker.on('dragend', function () {
                const pos = marker.getLatLng();
                document.getElementById('customer_latitude').value = pos.lat;
                document.getElementById('customer_longitude').value = pos.lng;
            });

            clearInterval(interval);
        }
    }, 500);
});