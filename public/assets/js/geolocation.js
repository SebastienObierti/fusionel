/**
 * FUSIONEL - Géolocalisation Client
 * 
 * Utilisation:
 * 
 * // Demander la position et l'envoyer au serveur
 * FusionelGeo.updateMyLocation().then(result => console.log(result));
 * 
 * // Trouver les utilisateurs dans un rayon de 10km
 * FusionelGeo.findNearby(10).then(users => console.log(users));
 * 
 * // Géocoder une ville
 * FusionelGeo.geocode('Toulouse').then(coords => console.log(coords));
 */

const FusionelGeo = {
    
    API_URL: '/api/geolocation.php',
    
    /**
     * Obtenir la position actuelle du navigateur
     * @returns {Promise<{latitude: number, longitude: number}>}
     */
    getCurrentPosition() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Géolocalisation non supportée par votre navigateur'));
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    });
                },
                (error) => {
                    let message = 'Erreur de géolocalisation';
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            message = 'Vous avez refusé la géolocalisation';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = 'Position non disponible';
                            break;
                        case error.TIMEOUT:
                            message = 'Délai dépassé';
                            break;
                    }
                    reject(new Error(message));
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000 // Cache 5 minutes
                }
            );
        });
    },
    
    /**
     * Mettre à jour ma position sur le serveur
     * @param {object} options - { useGPS: true } ou { city: 'Toulouse' }
     * @returns {Promise}
     */
    async updateMyLocation(options = { useGPS: true }) {
        let body = {};
        
        if (options.city) {
            body = { city: options.city };
        } else if (options.useGPS) {
            try {
                const pos = await this.getCurrentPosition();
                body = {
                    latitude: pos.latitude,
                    longitude: pos.longitude
                };
            } catch (e) {
                throw e;
            }
        } else if (options.latitude && options.longitude) {
            body = {
                latitude: options.latitude,
                longitude: options.longitude
            };
        }
        
        const response = await fetch(`${this.API_URL}?action=update`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(body)
        });
        
        return response.json();
    },
    
    /**
     * Trouver les utilisateurs proches
     * @param {number} radiusKm - Rayon en km (défaut: 50)
     * @param {object} filters - { gender: 'female', min_age: 25, max_age: 40 }
     * @returns {Promise<array>}
     */
    async findNearby(radiusKm = 50, filters = {}) {
        const params = new URLSearchParams({
            action: 'nearby',
            radius: radiusKm,
            ...filters
        });
        
        const response = await fetch(`${this.API_URL}?${params}`, {
            credentials: 'include'
        });
        
        return response.json();
    },
    
    /**
     * Géocoder une ville
     * @param {string} city
     * @returns {Promise<{latitude, longitude}>}
     */
    async geocode(city) {
        const response = await fetch(`${this.API_URL}?action=geocode&city=${encodeURIComponent(city)}`);
        return response.json();
    },
    
    /**
     * Obtenir ma position enregistrée
     * @returns {Promise}
     */
    async getMyLocation() {
        const response = await fetch(`${this.API_URL}?action=me`, {
            credentials: 'include'
        });
        return response.json();
    },
    
    /**
     * Calculer la distance entre deux utilisateurs
     * @param {number} userId1
     * @param {number} userId2
     * @returns {Promise<{distance_km: number}>}
     */
    async getDistance(userId1, userId2) {
        const response = await fetch(`${this.API_URL}?action=distance&user1=${userId1}&user2=${userId2}`);
        return response.json();
    },
    
    /**
     * Formater la distance pour l'affichage
     * @param {number} km
     * @returns {string}
     */
    formatDistance(km) {
        if (km === null || km === undefined) return '';
        if (km < 1) return `${Math.round(km * 1000)} m`;
        if (km < 10) return `${km.toFixed(1)} km`;
        return `${Math.round(km)} km`;
    },
    
    /**
     * Composant UI - Bouton de localisation
     * @param {HTMLElement} container
     * @param {function} onSuccess
     */
    createLocationButton(container, onSuccess) {
        const btn = document.createElement('button');
        btn.className = 'btn btn-outline location-btn';
        btn.innerHTML = '📍 Me localiser';
        
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            btn.innerHTML = '⏳ Localisation...';
            
            try {
                const result = await this.updateMyLocation({ useGPS: true });
                
                if (result.success) {
                    btn.innerHTML = '✅ Position mise à jour';
                    if (onSuccess) onSuccess(result);
                } else {
                    btn.innerHTML = '❌ ' + (result.error || 'Erreur');
                }
            } catch (e) {
                btn.innerHTML = '❌ ' + e.message;
            }
            
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '📍 Me localiser';
            }, 3000);
        });
        
        container.appendChild(btn);
        return btn;
    },
    
    /**
     * Composant UI - Slider de rayon
     * @param {HTMLElement} container
     * @param {function} onChange
     */
    createRadiusSlider(container, onChange) {
        const wrapper = document.createElement('div');
        wrapper.className = 'radius-slider-wrapper';
        wrapper.innerHTML = `
            <label>
                <span>Distance: <strong id="radiusValue">50</strong> km</span>
                <input type="range" id="radiusSlider" min="5" max="200" value="50" step="5">
            </label>
        `;
        
        const slider = wrapper.querySelector('#radiusSlider');
        const value = wrapper.querySelector('#radiusValue');
        
        slider.addEventListener('input', () => {
            value.textContent = slider.value;
        });
        
        slider.addEventListener('change', () => {
            if (onChange) onChange(parseInt(slider.value));
        });
        
        container.appendChild(wrapper);
        return slider;
    }
};

// Export pour utilisation en module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FusionelGeo;
}
