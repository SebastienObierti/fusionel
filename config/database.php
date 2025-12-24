<?php
/**
 * Fusionel.fr - Configuration Base de Données
 * 
 * Modifiez les paramètres ci-dessous selon votre configuration
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_fusionel');        // Nom de votre base de données
define('DB_USER', 'seb31t');             // Votre utilisateur MySQL
define('DB_PASS', 'Bmwmpowerm3917=$*m');                 // Votre mot de passe MySQL
define('DB_CHARSET', 'utf8mb4');

/**
 * Classe Database - Singleton PDO
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            // Log l'erreur
            error_log("Erreur de connexion à la base de données: " . $e->getMessage());
            throw new Exception("Impossible de se connecter à la base de données");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    // Empêcher le clonage
    private function __clone() {}
    
    // Empêcher la désérialisation
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Fonction helper pour obtenir la connexion PDO
 * 
 * @return PDO
 */
function db() {
    return Database::getInstance()->getConnection();
}

// Test de connexion au chargement (optionnel, à commenter en production)
// try {
//     $pdo = db();
//     // echo "Connexion à la base de données réussie !";
// } catch (Exception $e) {
//     die("Erreur: " . $e->getMessage());
// }
