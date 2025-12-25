<?php
/**
 * API Registration - Fusionel
 * Endpoint: POST /api/auth/register
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Load database config
require_once __DIR__ . '/../../config/database.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$requiredFields = ['email', 'password', 'firstname', 'gender', 'birthdate', 'city', 'seeking'];
$errors = [];

foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        $errors[] = "Le champ '$field' est requis";
    }
}

// Validate email format
if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Format d'email invalide";
}

// Validate password strength (min 8 chars, 1 uppercase, 1 lowercase, 1 number)
if (!empty($input['password'])) {
    if (strlen($input['password']) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
    }
    if (!preg_match('/[A-Z]/', $input['password'])) {
        $errors[] = "Le mot de passe doit contenir au moins une majuscule";
    }
    if (!preg_match('/[a-z]/', $input['password'])) {
        $errors[] = "Le mot de passe doit contenir au moins une minuscule";
    }
    if (!preg_match('/[0-9]/', $input['password'])) {
        $errors[] = "Le mot de passe doit contenir au moins un chiffre";
    }
}

// Validate birthdate (must be 18+)
if (!empty($input['birthdate'])) {
    $birthdate = new DateTime($input['birthdate']);
    $today = new DateTime();
    $age = $today->diff($birthdate)->y;
    if ($age < 18) {
        $errors[] = "Vous devez avoir au moins 18 ans";
    }
}

// Validate gender
$validGenders = ['homme', 'femme', 'autre'];
if (!empty($input['gender']) && !in_array($input['gender'], $validGenders)) {
    $errors[] = "Genre invalide";
}

// Validate seeking
if (!empty($input['seeking']) && !in_array($input['seeking'], $validGenders)) {
    $errors[] = "Préférence de recherche invalide";
}

// Return errors if any
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors, 'error' => $errors[0]]);
    exit;
}

try {
    $pdo = db();
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cet email est déjà utilisé']);
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    
    // Generate email verification token
    $verificationToken = bin2hex(random_bytes(32));
    
    // Insert user
    $sql = "INSERT INTO users (
        email, 
        password, 
        firstname, 
        lastname,
        gender, 
        seeking,
        birthdate, 
        city,
        bio,
        job,
        subscription_type,
        email_verification_token,
        email_verified,
        is_verified,
        is_banned,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'free', ?, 0, 0, 0, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        strtolower(trim($input['email'])),
        $hashedPassword,
        trim($input['firstname']),
        trim($input['lastname'] ?? ''),
        $input['gender'],
        $input['seeking'],
        $input['birthdate'],
        trim($input['city']),
        trim($input['bio'] ?? ''),
        trim($input['job'] ?? ''),
        $verificationToken
    ]);
    
    if ($result) {
        $userId = $pdo->lastInsertId();
        
        // Send verification email (optional - implement later)
        // sendVerificationEmail($input['email'], $verificationToken);
        
        // Create session
        session_start();
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $input['email'];
        $_SESSION['user_firstname'] = $input['firstname'];
        
        // Generate JWT token (simple version)
        $token = base64_encode(json_encode([
            'user_id' => $userId,
            'email' => $input['email'],
            'exp' => time() + (7 * 24 * 60 * 60) // 7 days
        ]));
        
        echo json_encode([
            'success' => true,
            'message' => 'Inscription réussie !',
            'user' => [
                'id' => $userId,
                'email' => $input['email'],
                'firstname' => $input['firstname'],
                'gender' => $input['gender'],
                'subscription_type' => 'free'
            ],
            'token' => $token,
            'redirect' => '/app/profile.html'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'inscription']);
    }
    
} catch (PDOException $e) {
    error_log("Registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
}
