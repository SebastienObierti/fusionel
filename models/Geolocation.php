<?php
/**
 * FUSIONEL - Classe Geolocation
 * Adaptée au schéma existant (gender: homme/femme, table: user_photos)
 */

class Geolocation {
    
    private $pdo;
    const EARTH_RADIUS = 6371; // km
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: db();
    }
    
    /**
     * Calculer la distance entre deux points (formule Haversine)
     */
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return null;
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return round(self::EARTH_RADIUS * $c, 2);
    }
    
    /**
     * Mettre à jour la position d'un utilisateur
     */
    public function updateUserLocation($userId, $latitude, $longitude) {
        $stmt = $this->pdo->prepare("
            UPDATE users SET latitude = ?, longitude = ?, updated_at = NOW() WHERE id = ?
        ");
        return $stmt->execute([$latitude, $longitude, $userId]);
    }
    
    /**
     * Obtenir la position d'un utilisateur
     */
    public function getUserLocation($userId) {
        $stmt = $this->pdo->prepare("SELECT latitude, longitude, city FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Trouver les utilisateurs dans un rayon donné
     */
    public function findUsersNearby($latitude, $longitude, $radiusKm = 50, $filters = [], $excludeUserId = null, $limit = 100) {
        
        // Bounding box pour optimiser
        $latDelta = $radiusKm / 111;
        $lonDelta = $radiusKm / (111 * cos(deg2rad($latitude)));
        
        $minLat = $latitude - $latDelta;
        $maxLat = $latitude + $latDelta;
        $minLon = $longitude - $lonDelta;
        $maxLon = $longitude + $lonDelta;
        
        $sql = "
            SELECT 
                u.id, u.firstname, u.birthdate, u.gender, u.city, u.bio,
                u.latitude, u.longitude, u.subscription_type, u.is_verified,
                u.is_online, u.last_seen,
                (
                    6371 * ACOS(
                        LEAST(1, GREATEST(-1,
                            COS(RADIANS(?)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(?)) +
                            SIN(RADIANS(?)) * SIN(RADIANS(latitude))
                        ))
                    )
                ) AS distance,
                (SELECT filepath FROM user_photos WHERE user_id = u.id AND is_primary = 1 LIMIT 1) AS main_photo
            FROM users u
            WHERE u.latitude IS NOT NULL 
              AND u.longitude IS NOT NULL
              AND u.latitude BETWEEN ? AND ?
              AND u.longitude BETWEEN ? AND ?
              AND u.is_active = 1
              AND u.is_banned = 0
        ";
        
        $params = [$latitude, $longitude, $latitude, $minLat, $maxLat, $minLon, $maxLon];
        
        // Exclure l'utilisateur courant
        if ($excludeUserId) {
            $sql .= " AND u.id != ?";
            $params[] = $excludeUserId;
        }
        
        // Filtre par genre (homme/femme)
        if (!empty($filters['gender'])) {
            $sql .= " AND u.gender = ?";
            $params[] = $filters['gender'];
        }
        
        // Filtre par seeking (qui cherche quoi)
        if (!empty($filters['seeking'])) {
            $sql .= " AND (u.seeking = ? OR u.seeking = 'tous')";
            $params[] = $filters['seeking'];
        }
        
        // Filtre par âge
        if (!empty($filters['min_age'])) {
            $sql .= " AND TIMESTAMPDIFF(YEAR, u.birthdate, CURDATE()) >= ?";
            $params[] = $filters['min_age'];
        }
        if (!empty($filters['max_age'])) {
            $sql .= " AND TIMESTAMPDIFF(YEAR, u.birthdate, CURDATE()) <= ?";
            $params[] = $filters['max_age'];
        }
        
        // Seulement avec photo
        if (!empty($filters['with_photo'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM user_photos WHERE user_id = u.id)";
        }
        
        // Seulement en ligne
        if (!empty($filters['online_only'])) {
            $sql .= " AND u.is_online = 1";
        }
        
        // Seulement vérifiés
        if (!empty($filters['verified_only'])) {
            $sql .= " AND u.is_verified = 1";
        }
        
        $sql .= " HAVING distance <= ? ORDER BY distance ASC LIMIT ?";
        $params[] = $radiusKm;
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as &$user) {
            $user['age'] = $this->calculateAge($user['birthdate']);
            $user['distance'] = round($user['distance'], 1);
            $user['distance_text'] = $this->formatDistance($user['distance']);
            unset($user['birthdate']);
        }
        
        return $users;
    }
    
    /**
     * Trouver les utilisateurs proches d'un utilisateur donné
     */
    public function findUsersNearUser($userId, $radiusKm = 50, $filters = [], $limit = 100) {
        $location = $this->getUserLocation($userId);
        
        if (!$location || !$location['latitude'] || !$location['longitude']) {
            return ['error' => 'Position non définie', 'users' => []];
        }
        
        // Récupérer les préférences de l'utilisateur
        $stmt = $this->pdo->prepare("SELECT gender, seeking FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Filtrer selon le seeking de l'utilisateur
        if (empty($filters['gender']) && $currentUser) {
            if ($currentUser['seeking'] === 'homme') {
                $filters['gender'] = 'homme';
            } elseif ($currentUser['seeking'] === 'femme') {
                $filters['gender'] = 'femme';
            }
            // Si 'tous', pas de filtre
        }
        
        $users = $this->findUsersNearby(
            $location['latitude'],
            $location['longitude'],
            $radiusKm,
            $filters,
            $userId,
            $limit
        );
        
        return [
            'center' => [
                'latitude' => (float)$location['latitude'],
                'longitude' => (float)$location['longitude'],
                'city' => $location['city']
            ],
            'radius_km' => $radiusKm,
            'count' => count($users),
            'users' => $users
        ];
    }
    
    /**
     * Géocoder une ville (API Nominatim gratuite)
     */
    public function geocodeAddress($address) {
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $address,
            'format' => 'json',
            'limit' => 1
        ]);
        
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Fusionel/1.0\r\n"
            ]
        ];
        
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data[0])) {
                return [
                    'latitude' => (float)$data[0]['lat'],
                    'longitude' => (float)$data[0]['lon'],
                    'display_name' => $data[0]['display_name']
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Mettre à jour la position depuis la ville
     */
    public function updateUserLocationFromCity($userId) {
        $stmt = $this->pdo->prepare("SELECT city FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || empty($user['city'])) return false;
        
        $coords = $this->geocodeAddress($user['city'] . ', France');
        
        if ($coords) {
            return $this->updateUserLocation($userId, $coords['latitude'], $coords['longitude']);
        }
        
        return false;
    }
    
    /**
     * Calculer l'âge
     */
    private function calculateAge($birthdate) {
        if (!$birthdate) return null;
        $birth = new DateTime($birthdate);
        return $birth->diff(new DateTime())->y;
    }
    
    /**
     * Formater la distance
     */
    private function formatDistance($km) {
        if ($km < 1) return 'À moins de 1 km';
        if ($km < 10) return round($km, 1) . ' km';
        return round($km) . ' km';
    }
}
