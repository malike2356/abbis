/**
 * Location Picker (Google Maps with Leaflet fallback)
 * Integrates with field reports form for location selection
 */

class LocationPicker {
    constructor(mapContainerId, inputFields) {
        this.mapContainerId = mapContainerId;
        this.inputFields = inputFields || {
            latitude: 'latitude',
            longitude: 'longitude',
            // plusCode removed - no longer needed
            locationDescription: 'location_description'
        };
        this.map = null;
        this.marker = null;
        this.geocoder = null;
        this.placesAutocomplete = null;
        this.plusCodeLibrary = null;
        
        this.init();
    }
    
    init() {
        // Try Google first
        if (typeof google !== 'undefined' && google.maps) {
            return this.initGoogle();
        }
        // Fallback to Leaflet if available
        if (typeof L !== 'undefined') {
            return this.initLeaflet();
        }
        console.error('No map provider loaded');
    }

    initGoogle() {
        
        // Initialize map
        const mapOptions = {
            center: { lat: 5.6037, lng: -0.1870 }, // Default to Accra, Ghana
            zoom: 12,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        };
        
        this.map = new google.maps.Map(document.getElementById(this.mapContainerId), mapOptions);
        this.geocoder = new google.maps.Geocoder();
        
        // Initialize Places Autocomplete for search
        const searchInput = document.getElementById('location_search');
        if (searchInput) {
            this.placesAutocomplete = new google.maps.places.Autocomplete(searchInput, {
                componentRestrictions: { country: 'gh' }, // Ghana only
                fields: ['geometry', 'formatted_address']
            });
            
            this.placesAutocomplete.addListener('place_changed', () => {
                this.handlePlaceSelect();
            });
        }
        
        // Map click event
        this.map.addListener('click', (e) => {
            this.setLocation(e.latLng.lat(), e.latLng.lng());
        });
        
        // Initialize marker
        this.marker = new google.maps.Marker({
            map: this.map,
            draggable: true
        });
        
        this.marker.addListener('dragend', () => {
            const position = this.marker.getPosition();
            this.setLocation(position.lat(), position.lng());
        });
        
        // If coordinates exist, center map on them
        const latInput = document.getElementById(this.inputFields.latitude);
        const lngInput = document.getElementById(this.inputFields.longitude);
        
        if (latInput && lngInput && latInput.value && lngInput.value) {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                this.setLocation(lat, lng, false);
            }
        }
    }
    
    handlePlaceSelect() {
        const place = this.placesAutocomplete.getPlace();
        
        if (!place.geometry) {
            console.warn('No geometry found for selected place');
            return;
        }
        
        // Center map and set marker
        this.map.setCenter(place.geometry.location);
        this.map.setZoom(16);
        
        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();
        
        this.setLocation(lat, lng);
        
        // Update location description
        const descInput = document.getElementById(this.inputFields.locationDescription);
        if (descInput && place.formatted_address) {
            descInput.value = place.formatted_address;
        }
        
        // Update Plus Code if available
        // Plus Code removed - no longer needed
    }
    
    setLocation(lat, lng, reverseGeocode = true) {
        // Update input fields
        const latInput = document.getElementById(this.inputFields.latitude);
        const lngInput = document.getElementById(this.inputFields.longitude);
        
        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);
        
        // Update marker position
        const position = new google.maps.LatLng(lat, lng);
        this.marker.setPosition(position);
        this.map.setCenter(position);
        
        // Plus Code generation removed - no longer needed
        
        // Reverse geocode for address
        if (reverseGeocode) {
            this.reverseGeocode(lat, lng);
        }
    }
    
    generatePlusCode(lat, lng) {
        // Use Open Location Code (Plus Codes) if available
        try {
            if (typeof OpenLocationCode !== 'undefined' && typeof OpenLocationCode.encode === 'function') {
                // Plus Code removed - no longer needed
                return;
            }
            if (typeof openlocationcode !== 'undefined' && typeof openlocationcode.encode === 'function') {
                // Plus Code removed - no longer needed
                return;
            }
        } catch (e) {}
        // Otherwise rely on Google Places (if used) to populate
    }
    
    reverseGeocode(lat, lng) {
        this.geocoder.geocode(
            { location: { lat, lng } },
            (results, status) => {
                if (status === 'OK' && results[0]) {
                    const descInput = document.getElementById(this.inputFields.locationDescription);
                    if (descInput && !descInput.value) {
                        descInput.value = results[0].formatted_address;
                    }
                    
                    // Check for Plus Code in result
                    for (let component of results[0].address_components) {
                        // Plus Code removed - no longer needed
                    }
                }
            }
        );
    }
    initLeaflet() {
        const container = document.getElementById(this.mapContainerId);
        if (!container) return;
        const defaultLatLng = [5.6037, -0.1870];
        this.map = L.map(container).setView(defaultLatLng, 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(this.map);
        this.marker = L.marker(defaultLatLng, { draggable: true }).addTo(this.map);
        this.marker.on('dragend', (e) => {
            const p = e.target.getLatLng();
            this.setLeafletLocation(p.lat, p.lng);
        });
        this.map.on('click', (e) => {
            this.setLeafletLocation(e.latlng.lat, e.latlng.lng);
        });
        // Center on existing inputs if present
        const latInput = document.getElementById(this.inputFields.latitude);
        const lngInput = document.getElementById(this.inputFields.longitude);
        if (latInput && lngInput && latInput.value && lngInput.value) {
            const lat = parseFloat(latInput.value), lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                this.setLeafletLocation(lat, lng, false);
            }
        }
        // Basic search using Nominatim
        const searchInput = document.getElementById('location_search');
        if (searchInput) {
            searchInput.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    const q = searchInput.value.trim();
                    if (!q) return;
                    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q))
                        .then(r=>r.json()).then(list => {
                            if (list && list[0]) {
                                const lat = parseFloat(list[0].lat), lng = parseFloat(list[0].lon);
                                this.setLeafletLocation(lat, lng);
                                const descInput = document.getElementById(this.inputFields.locationDescription);
                                if (descInput) descInput.value = list[0].display_name || q;
                            }
                        }).catch(()=>{});
                }
            });
        }
    }

    setLeafletLocation(lat, lng, reverseGeocode = true) {
        const latInput = document.getElementById(this.inputFields.latitude);
        const lngInput = document.getElementById(this.inputFields.longitude);
        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);
        this.marker.setLatLng([lat, lng]);
        this.map.setView([lat, lng], 16);
        this.generatePlusCode(lat, lng);
        if (reverseGeocode) {
            fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng)
                .then(r=>r.json()).then(res => {
                    const descInput = document.getElementById(this.inputFields.locationDescription);
                    if (descInput && res && res.display_name && !descInput.value) {
                        descInput.value = res.display_name;
                    }
                }).catch(()=>{});
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('map-container')) {
            window.locationPicker = new LocationPicker('map-container');
        }
    });
} else {
    if (document.getElementById('map-container')) {
        window.locationPicker = new LocationPicker('map-container');
    }
}

