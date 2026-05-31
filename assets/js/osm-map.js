var map = L.map('osm-map').setView([51.505, -0.09], 13);
L.tileLayer((typeof SNAPPBOX_OSM !== 'undefined' && SNAPPBOX_OSM.tileUrl) || '', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(map);

var marker = L.marker([51.505, -0.09]).addTo(map);

map.on('click', function (e) {
    var lat = e.latlng.lat;
    var lng = e.latlng.lng;
    marker.setLatLng(e.latlng);
    document.getElementById('customer_latitude').value = lat;
    document.getElementById('customer_longitude').value = lng;
});
``