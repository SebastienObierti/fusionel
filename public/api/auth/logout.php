<?php
/**
 * API Logout - Fusionel
 * Endpoint: POST /api/auth/logout
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
session_destroy();

// Clear cookies
if (isset($_COOKIE['token'])) {
    setcookie('token', '', time() - 3600, '/');
}

echo json_encode([
    'success' => true,
    'message' => 'Déconnexion réussie',
    'redirect' => '/'
]);
