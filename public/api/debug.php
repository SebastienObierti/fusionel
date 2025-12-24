<?php
/**
 * DEBUG - API Admin Fusionel
 * Accès: https://fusionel.fr/api/debug.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Debug API Admin - Fusionel</h1>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;max-width:900px;margin:auto}
.ok{color:green;font-weight:bold}.error{color:red;font-weight:bold}
pre{background:#f5f5f5;padding:15px;border-radius:8px;overflow:auto}
.section{background:#fff;border:1px solid #ddd;padding:20px;margin:20px 0;border-radius:10px;box-shadow:0 2px 5px rgba(0,0,0,0.1)}</style>";

// 1. Informations PHP
echo "<div class='section'>";
echo "<h2>1️⃣ Informations PHP</h2>";
echo "<p>Version PHP: <strong>" . phpversion() . "</strong></p>";
echo "<p>Chemin actuel (__DIR__): <code>" . __DIR__ . "</code></p>";
echo "</div>";

// 2. Recherche du fichier config
echo "<div class='section'>";
echo "<h2>2️⃣ Recherche fichier database.php</h2>";

$possiblePaths = [
    __DIR__ . '/../../config/database.php',
    __DIR__ . '/../config/database.php',
    '/srv/web/fusionel/config/database.php',
];

$configFound = false;
$configPath = null;

foreach ($possiblePaths as $path) {
    $exists = file_exists($path);
    $realPath = $exists ? realpath($path) : 'N/A';
    $status = $exists ? "<span class='ok'>✅ TROUVÉ</span>" : "<span class='error'>❌ Non trouvé</span>";
    echo "<p>$status - <code>$path</code></p>";
    if ($exists && !$configFound) {
        $configFound = true;
        $configPath = $path;
    }
}
echo "</div>";

// 3. Chargement du fichier config
echo "<div class='section'>";
echo "<h2>3️⃣ Chargement du fichier config</h2>";

if ($configFound) {
    try {
        require_once $configPath;
        echo "<p class='ok'>✅ Fichier config chargé</p>";
        echo "<p>DB_HOST: " . (defined('DB_HOST') ? DB_HOST : "Non défini") . "</p>";
        echo "<p>DB_NAME: " . (defined('DB_NAME') ? DB_NAME : "Non défini") . "</p>";
        echo "<p>DB_USER: " . (defined('DB_USER') ? DB_USER : "Non défini") . "</p>";
        echo "<p>Fonction db(): " . (function_exists('db') ? "<span class='ok'>✅ Existe</span>" : "<span class='error'>❌ N'existe pas</span>") . "</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur: " . $e->getMessage() . "</p>";
    } catch (Error $e) {
        echo "<p class='error'>❌ Erreur fatale: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>❌ Aucun fichier config trouvé!</p>";
}
echo "</div>";

// 4. Test connexion BDD
echo "<div class='section'>";
echo "<h2>4️⃣ Test connexion BDD</h2>";

$pdo = null;
if (function_exists('db')) {
    try {
        $pdo = db();
        echo "<p class='ok'>✅ Connexion PDO réussie!</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>❌ Fonction db() non disponible</p>";
}
echo "</div>";

// 5. Test tables
echo "<div class='section'>";
echo "<h2>5️⃣ Test tables</h2>";

if ($pdo) {
    $tables = ['users', 'user_photos', 'matches', 'likes', 'admin_users'];
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
    echo "<tr><th>Table</th><th>Status</th><th>Lignes</th></tr>";
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<tr><td>$table</td><td class='ok'>✅</td><td>$count</td></tr>";
        } catch (Exception $e) {
            echo "<tr><td>$table</td><td class='error'>❌</td><td>-</td></tr>";
        }
    }
    echo "</table>";
}
echo "</div>";

// 6. Test Stats JSON
echo "<div class='section'>";
echo "<h2>6️⃣ Test Stats (ce que l'API doit retourner)</h2>";

if ($pdo) {
    try {
        $stats = [];
        $stats['total_users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['premium_users'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE subscription_type='premium'")->fetchColumn();
        $stats['vip_users'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE subscription_type='vip'")->fetchColumn();
        echo "<p class='ok'>✅ Requêtes OK!</p>";
        echo "<pre>" . json_encode($stats, JSON_PRETTY_PRINT) . "</pre>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erreur: " . $e->getMessage() . "</p>";
    }
}
echo "</div>";

// Résumé
echo "<div class='section' style='background:#fffde7'>";
echo "<h2>📋 Actions</h2>";
echo "<p>Si tout est vert ici, testez: <a href='/api/admin.php?route=stats'>/api/admin.php?route=stats</a></p>";
echo "<p style='color:red'>⚠️ SUPPRIMEZ ce fichier après debug!</p>";
echo "</div>";
