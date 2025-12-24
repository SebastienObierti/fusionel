<?php
/**
 * FUSIONEL - API Géolocalisation
 * 
 * GET  ?action=nearby&radius=10           - Utilisateurs proches
 * POST ?action=update                     - Mettre à jour position
 * GET  ?action=geocode&city=Toulouse      - Géocoder une ville
 * GET  ?action=me                         - Ma position
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$rootDir = dirname(dirname(__DIR__));
require_once $rootDir . '/config/database.php';
require_once $rootDir . '/models/Geolocation.php';

session_start();
$currentUserId = $_SESSION['user_id'] ?? null;

$action = $_GET['action'] ?? 'nearby';
$geo = new Geolocation();

try {
    
    // ========== NEARBY ==========
    if ($action === 'nearby') {
        if (!$currentUserId) {
            die(json_encode(['error' => 'Non connecté', 'users' => []]));
        }
        
        $radius = min(200, max(1, intval($_GET['radius'] ?? 50)));
        $gender = $_GET['gender'] ?? null;
        $minAge = intval($_GET['min_age'] ?? 18);
        $maxAge = intval($_GET['max_age'] ?? 99);
        $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
        $withPhoto = isset($_GET['with_photo']) ? (bool)$_GET['with_photo'] : true;
        $onlineOnly = isset($_GET['online_only']) ? (bool)$_GET['online_only'] : false;
        
        $filters = [
            'gender' => $gender, // homme ou femme
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'with_photo' => $withPhoto,
            'online_only' => $onlineOnly
        ];
        
        $result = $geo->findUsersNearUser($currentUserId, $radius, $filters, $limit);
        
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    // ========== UPDATE ==========
    elseif ($action === 'update') {
        if (!$currentUserId) {
            die(json_encode(['error' => 'Non connecté']));
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Option 1: Coordonnées GPS directes
        if (!empty($input['latitude']) && !empty($input['longitude'])) {
            $lat = floatval($input['latitude']);
            $lon = floatval($input['longitude']);
            
            if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                die(json_encode(['error' => 'Coordonnées invalides']));
            }
            
            $success = $geo->updateUserLocation($currentUserId, $lat, $lon);
            
            echo json_encode([
                'success' => $success,
                'latitude' => $lat,
                'longitude' => $lon
            ]);
        }
        // Option 2: Depuis la ville
        elseif (!empty($input['city'])) {
            $pdo = db();
            $stmt = $pdo->prepare("UPDATE users SET city = ? WHERE id = ?");
            $stmt->execute([$input['city'], $currentUserId]);
            
            $coords = $geo->geocodeAddress($input['city'] . ', France');
            
            if ($coords) {
                $geo->updateUserLocation($currentUserId, $coords['latitude'], $coords['longitude']);
                echo json_encode([
                    'success' => true,
                    'city' => $input['city'],
                    'latitude' => $coords['latitude'],
                    'longitude' => $coords['longitude']
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ville non trouvée']);
            }
        }
        else {
            echo json_encode(['error' => 'Paramètres manquants']);
        }
    }
    
    // ========== GEOCODE ==========
    elseif ($action === 'geocode') {
        $city = $_GET['city'] ?? '';
        if (empty($city)) {
            die(json_encode(['error' => 'Paramètre city requis']));
        }
        
        $coords = $geo->geocodeAddress($city . ', France');
        
        if ($coords) {
            echo json_encode(['success' => true, 'city' => $city] + $coords);
        } else {
            echo json_encode(['success' => false, 'error' => 'Ville non trouvée']);
        }
    }
    
    // ========== ME ==========
    elseif ($action === 'me') {
        if (!$currentUserId) {
            die(json_encode(['error' => 'Non connecté']));
        }
        echo json_encode(['success' => true, 'location' => $geo->getUserLocation($currentUserId)]);
    }
    
    // ========== DISTANCE ==========
    elseif ($action === 'distance') {
        $userId1 = intval($_GET['user1'] ?? 0);
        $userId2 = intval($_GET['user2'] ?? 0);
        
        if (!$userId1 || !$userId2) {
            die(json_encode(['error' => 'Paramètres user1 et user2 requis']));
        }
        
        $loc1 = $geo->getUserLocation($userId1);
        $loc2 = $geo->getUserLocation($userId2);
        
        if (!$loc1['latitude'] || !$loc2['latitude']) {
            echo json_encode(['success' => false, 'error' => 'Position non définie']);
        } else {
            $distance = Geolocation::calculateDistance(
                $loc1['latitude'], $loc1['longitude'],
                $loc2['latitude'], $loc2['longitude']
            );
            echo json_encode([
                'success' => true,
                'distance_km' => $distance
            ]);
        }
    }
    
    // ========== UPDATE ALL FROM CITY ==========
    elseif ($action === 'update_all_from_city') {
        // Admin only - Met à jour les coordonnées de tous les users depuis leur ville
        $pdo = db();
        $stmt = $pdo->query("SELECT id, city FROM users WHERE latitude IS NULL AND city IS NOT NULL AND city != ''");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        foreach ($users as $user) {
            if ($geo->updateUserLocationFromCity($user['id'])) {
                $updated++;
            }
            usleep(100000); // 100ms entre chaque requête (respect API Nominatim)
        }
        
        echo json_encode(['success' => true, 'updated' => $updated, 'total' => count($users)]);
    }
    
    else {
        echo json_encode(['error' => 'Action inconnue: ' . $action]);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
