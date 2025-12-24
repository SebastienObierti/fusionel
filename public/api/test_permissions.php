<?php
/**
 * Test des permissions - À SUPPRIMER APRÈS USAGE
 */
header('Content-Type: application/json; charset=utf-8');

$results = [];

// 1. Chemins
$scriptDir = __DIR__;
$publicDir = dirname($scriptDir);
$rootDir = dirname($publicDir);
$configDir = $rootDir . '/config';
$settingsFile = $configDir . '/settings.json';

$results['paths'] = [
    'script_dir' => $scriptDir,
    'public_dir' => $publicDir,
    'root_dir' => $rootDir,
    'config_dir' => $configDir,
    'settings_file' => $settingsFile
];

// 2. Vérifications
$results['checks'] = [
    'config_dir_exists' => is_dir($configDir),
    'config_dir_writable' => is_writable($configDir),
    'settings_file_exists' => file_exists($settingsFile),
    'settings_file_writable' => file_exists($settingsFile) ? is_writable($settingsFile) : 'N/A'
];

// 3. Permissions détaillées
if (is_dir($configDir)) {
    $results['permissions'] = [
        'config_dir_perms' => substr(sprintf('%o', fileperms($configDir)), -4),
        'config_dir_owner' => posix_getpwuid(fileowner($configDir))['name'] ?? fileowner($configDir),
        'config_dir_group' => posix_getgrgid(filegroup($configDir))['name'] ?? filegroup($configDir)
    ];
}

// 4. Utilisateur PHP
$results['php_user'] = [
    'current_user' => get_current_user(),
    'whoami' => exec('whoami 2>/dev/null') ?: 'N/A',
    'process_user' => posix_getpwuid(posix_geteuid())['name'] ?? 'N/A'
];

// 5. Test d'écriture
$testFile = $configDir . '/test_write_' . time() . '.tmp';
$writeTest = @file_put_contents($testFile, 'test');
if ($writeTest !== false) {
    @unlink($testFile);
    $results['write_test'] = '✅ OK - Écriture possible';
} else {
    $error = error_get_last();
    $results['write_test'] = '❌ ÉCHEC - ' . ($error['message'] ?? 'Erreur inconnue');
}

// 6. Contenu du dossier config
if (is_dir($configDir)) {
    $results['config_files'] = scandir($configDir);
}

// 7. Test de sauvegarde settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['save'])) {
    $testSettings = ['test' => true, 'timestamp' => date('Y-m-d H:i:s')];
    $saveResult = @file_put_contents($settingsFile, json_encode($testSettings, JSON_PRETTY_PRINT));
    
    if ($saveResult !== false) {
        $results['save_test'] = '✅ Sauvegarde réussie (' . $saveResult . ' bytes)';
        $results['saved_content'] = json_decode(file_get_contents($settingsFile), true);
    } else {
        $error = error_get_last();
        $results['save_test'] = '❌ Échec sauvegarde: ' . ($error['message'] ?? 'Erreur');
    }
}

$results['instructions'] = [
    'Pour tester la sauvegarde' => 'Ajoutez ?save=1 à l\'URL',
    'Pour corriger les permissions' => 'chown -R www-data:www-data ' . $configDir . ' && chmod 755 ' . $configDir
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
