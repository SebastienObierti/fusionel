<?php
/**
 * API Login - Fusionel
 * Endpoint: POST /api/auth/login
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

// Validate
if (empty($input['email']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email et mot de passe requis']);
    exit;
}

try {
    $pdo = db();
    
    // Find user
    $stmt = $pdo->prepare("
        SELECT id, email, password, firstname, lastname, gender, seeking, 
               subscription_type, is_banned, email_verified, city,
               (SELECT CONCAT('/uploads/photos/', filename) FROM user_photos WHERE user_id = users.id AND is_primary = 1 LIMIT 1) as main_photo
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([strtolower(trim($input['email']))]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Email ou mot de passe incorrect']);
        exit;
    }
    
    // Check password
    if (!password_verify($input['password'], $user['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Email ou mot de passe incorrect']);
        exit;
    }
    
    // Check if banned
    if ($user['is_banned']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Votre compte a été suspendu']);
        exit;
    }
    
    // Update last_seen
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Create session
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_firstname'] = $user['firstname'];
    
    // Generate token
    $token = base64_encode(json_encode([
        'user_id' => $user['id'],
        'email' => $user['email'],
        'exp' => time() + (7 * 24 * 60 * 60)
    ]));
    
    // Remove password from response
    unset($user['password']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'user' => $user,
        'token' => $token,
        'redirect' => '/app/discover.html'
    ]);
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
