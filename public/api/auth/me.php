<?php
/**
 * API Me - Fusionel
 * Endpoint: GET /api/auth/me
 * Récupère les infos de l'utilisateur connecté
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

session_start();

// Check session
$userId = $_SESSION['user_id'] ?? null;

// Or check Authorization header
if (!$userId) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        $decoded = json_decode(base64_decode($token), true);
        if ($decoded && isset($decoded['user_id']) && isset($decoded['exp'])) {
            if ($decoded['exp'] > time()) {
                $userId = $decoded['user_id'];
            }
        }
    }
}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

try {
    $pdo = db();
    
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.email, u.firstname, u.lastname, u.gender, u.seeking,
            u.birthdate, u.city, u.bio, u.job, u.subscription_type,
            u.subscription_end_date, u.is_verified, u.email_verified,
            u.created_at, u.last_seen, u.latitude, u.longitude,
            (SELECT CONCAT('/uploads/photos/', filename) FROM user_photos WHERE user_id = u.id AND is_primary = 1 LIMIT 1) as main_photo,
            (SELECT COUNT(*) FROM user_photos WHERE user_id = u.id) as photo_count,
            (SELECT COUNT(*) FROM likes WHERE liked_id = u.id) as likes_received,
            (SELECT COUNT(*) FROM matches WHERE user1_id = u.id OR user2_id = u.id) as matches_count
        FROM users u
        WHERE u.id = ? AND u.is_banned = 0
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Utilisateur non trouvé']);
        exit;
    }
    
    // Update last_seen
    $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$userId]);
    
    // Calculate profile completion percentage
    $fields = ['firstname', 'birthdate', 'city', 'gender', 'seeking', 'bio', 'job'];
    $completed = 0;
    foreach ($fields as $field) {
        if (!empty($user[$field])) $completed++;
    }
    if ($user['photo_count'] > 0) $completed++;
    $user['profile_completion'] = round(($completed / 8) * 100);
    
    // Check if subscription is active
    $user['is_premium'] = in_array($user['subscription_type'], ['premium', 'vip']);
    if ($user['subscription_end_date']) {
        $user['subscription_active'] = strtotime($user['subscription_end_date']) > time();
    } else {
        $user['subscription_active'] = false;
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log("Me error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
