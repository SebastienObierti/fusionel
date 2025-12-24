<?php
/**
 * Test API Settings
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

$configDir = dirname(dirname(__DIR__)) . '/config';
$settingsFile = $configDir . '/settings.json';

$result = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'config_dir' => $configDir,
    'settings_file' => $settingsFile,
    'dir_exists' => is_dir($configDir),
    'dir_writable' => is_writable($configDir)
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($settingsFile)) {
        $result['settings'] = json_decode(file_get_contents($settingsFile), true);
        $result['source'] = 'file';
    } else {
        $result['settings'] = ['site_name' => 'Fusionel', 'test' => true];
        $result['source'] = 'default';
    }
    $result['success'] = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $result['raw_input_length'] = strlen($rawInput);
    $result['raw_input_preview'] = substr($rawInput, 0, 200);
    
    $input = json_decode($rawInput, true);
    
    if ($input === null && strlen($rawInput) > 0) {
        $result['success'] = false;
        $result['error'] = 'JSON parse error: ' . json_last_error_msg();
    } elseif (empty($input)) {
        $result['success'] = false;
        $result['error'] = 'No data received';
    } else {
        $written = file_put_contents($settingsFile, json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        if ($written === false) {
            $result['success'] = false;
            $result['error'] = 'Write failed: ' . (error_get_last()['message'] ?? 'Unknown');
        } else {
            $result['success'] = true;
            $result['message'] = 'Saved';
            $result['bytes'] = $written;
        }
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
