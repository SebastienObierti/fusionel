/**
 * Fusionel - Utilitaires Photos
 * À inclure dans toutes les pages qui affichent des photos
 */

// Configuration
const PHOTO_BASE_URL = '/uploads/photos/';
const DEFAULT_AVATAR = 'https://ui-avatars.com/api/?background=ff6b6b&color=fff&size=200&name=';

/**
 * Obtenir l'URL complète d'une photo
 * @param {string|null} photo - Le nom du fichier ou le chemin de la photo
 * @param {string} fallbackName - Nom pour l'avatar par défaut
 * @returns {string} URL de la photo
 */
function getPhotoUrl(photo, fallbackName = 'U') {
    if (!photo) {
        return DEFAULT_AVATAR + encodeURIComponent(fallbackName);
    }
    
    // Si c'est déjà une URL complète
    if (photo.startsWith('http://') || photo.startsWith('https://')) {
        return photo;
    }
    
    // Si c'est déjà un chemin absolu
    if (photo.startsWith('/uploads/')) {
        return photo;
    }
    
    // Sinon, c'est juste le filename
    return PHOTO_BASE_URL + photo;
}

/**
 * Créer un élément image avec gestion d'erreur
 * @param {string|null} photo - Le nom du fichier ou le chemin
 * @param {string} alt - Texte alternatif
 * @param {string} className - Classes CSS
 * @returns {HTMLImageElement}
 */
function createPhotoElement(photo, alt = 'Photo', className = '') {
    const img = document.createElement('img');
    img.src = getPhotoUrl(photo, alt);
    img.alt = alt;
    if (className) img.className = className;
    
    // Fallback si l'image ne charge pas
    img.onerror = function() {
        this.src = DEFAULT_AVATAR + encodeURIComponent(alt.charAt(0));
    };
    
    return img;
}

/**
 * Mettre à jour toutes les images de profil sur la page
 * Cherche les éléments avec data-photo et data-name
 */
function updateAllProfilePhotos() {
    document.querySelectorAll('[data-photo]').forEach(img => {
        const photo = img.dataset.photo;
        const name = img.dataset.name || 'U';
        img.src = getPhotoUrl(photo, name);
        img.onerror = function() {
            this.src = DEFAULT_AVATAR + encodeURIComponent(name.charAt(0));
        };
    });
}

// Exporter pour utilisation globale
window.FusionelPhotos = {
    getPhotoUrl,
    createPhotoElement,
    updateAllProfilePhotos,
    PHOTO_BASE_URL,
    DEFAULT_AVATAR
};

console.log('📷 Fusionel Photos Utils loaded');
