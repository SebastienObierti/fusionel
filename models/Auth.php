<?php
/**
 * Modèle Auth - Gestion de l'authentification
 * Fusionel.fr
 */

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth {
    private $pdo;
    private $user = null;
    
    public function __construct() {
        $this->pdo = db();
        
        // Charger l'utilisateur si connecté
        if (isset($_SESSION['user_id'])) {
            $this->loadUser($_SESSION['user_id']);
        }
    }
    
    /**
     * Charger un utilisateur par ID
     */
    private function loadUser($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? AND is_banned = 0");
            $stmt->execute([$userId]);
            $this->user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($this->user) {
                unset($this->user['password']); // Ne jamais exposer le mot de passe
            }
        } catch (Exception $e) {
            $this->user = null;
        }
    }
    
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register($data) {
        $errors = [];
        
        // Validation
        if (empty($data['firstname'])) {
            $errors['firstname'] = 'Le prénom est requis';
        } elseif (strlen($data['firstname']) < 2) {
            $errors['firstname'] = 'Le prénom doit contenir au moins 2 caractères';
        }
        
        if (empty($data['email'])) {
            $errors['email'] = 'L\'email est requis';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalide';
        } else {
            // Vérifier si l'email existe
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Cet email est déjà utilisé';
            }
        }
        
        if (empty($data['password'])) {
            $errors['password'] = 'Le mot de passe est requis';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 8 caractères';
        }
        
        if (empty($data['gender'])) {
            $errors['gender'] = 'Le genre est requis';
        }
        
        if (empty($data['seeking'])) {
            $errors['seeking'] = 'Votre préférence est requise';
        }
        
        $age = isset($data['age']) ? (int)$data['age'] : 0;
        if ($age < 18) {
            $errors['age'] = 'Vous devez avoir au moins 18 ans';
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            // Calculer la date de naissance
            $birthdate = date('Y-m-d', strtotime("-{$age} years"));
            
            // Créer l'utilisateur
            $stmt = $this->pdo->prepare("
                INSERT INTO users (
                    firstname, email, password, gender, looking_for, 
                    birthdate, city, created_at, is_online, profile_completion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1, 20)
            ");
            
            $stmt->execute([
                trim($data['firstname']),
                strtolower(trim($data['email'])),
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['gender'],
                $data['seeking'],
                $birthdate,
                $data['city'] ?? null
            ]);
            
            $userId = $this->pdo->lastInsertId();
            
            // Créer les préférences par défaut
            $this->createDefaultPreferences($userId, $data['seeking']);
            
            // Créer les paramètres de notification par défaut
            $this->createDefaultNotificationSettings($userId);
            
            // Connecter l'utilisateur
            $_SESSION['user_id'] = $userId;
            $this->loadUser($userId);
            
            return [
                'success' => true,
                'user' => $this->user,
                'message' => 'Compte créé avec succès'
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur inscription: " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de la création du compte'];
        }
    }
    
    /**
     * Connexion
     */
    public function login($email, $password, $remember = false) {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Email et mot de passe requis'];
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([strtolower(trim($email))]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'error' => 'Email ou mot de passe incorrect'];
            }
            
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'error' => 'Email ou mot de passe incorrect'];
            }
            
            if ($user['is_banned']) {
                return ['success' => false, 'error' => 'Votre compte a été suspendu'];
            }
            
            // Mettre à jour le statut
            $stmt = $this->pdo->prepare("UPDATE users SET is_online = 1, last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Créer la session
            $_SESSION['user_id'] = $user['id'];
            
            // Cookie remember me
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = time() + (30 * 24 * 60 * 60); // 30 jours
                
                $stmt = $this->pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $stmt->execute([$token, $user['id']]);
                
                setcookie('remember_token', $token, $expiry, '/', '', true, true);
            }
            
            $this->loadUser($user['id']);
            
            return [
                'success' => true,
                'user' => $this->user,
                'message' => 'Connexion réussie'
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur connexion: " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur de connexion'];
        }
    }
    
    /**
     * Déconnexion
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = $this->pdo->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            } catch (Exception $e) {}
        }
        
        // Supprimer le cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        
        // Détruire la session
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $this->user = null;
    }
    
    /**
     * Vérifier si l'utilisateur est connecté
     */
    public function check() {
        // Vérifier la session
        if (isset($_SESSION['user_id']) && $this->user) {
            return true;
        }
        
        // Vérifier le cookie remember
        if (isset($_COOKIE['remember_token'])) {
            try {
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE remember_token = ? AND is_banned = 0");
                $stmt->execute([$_COOKIE['remember_token']]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $this->loadUser($user['id']);
                    return true;
                }
            } catch (Exception $e) {}
        }
        
        return false;
    }
    
    /**
     * Obtenir l'utilisateur courant
     */
    public function user() {
        return $this->user;
    }
    
    /**
     * Obtenir l'ID de l'utilisateur courant
     */
    public function id() {
        return $this->user ? $this->user['id'] : null;
    }
    
    /**
     * Demander une réinitialisation de mot de passe
     */
    public function requestPasswordReset($email) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([strtolower(trim($email))]);
            $user = $stmt->fetch();
            
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $stmt = $this->pdo->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expiry, $user['id']]);
                
                // TODO: Envoyer l'email avec le lien
                // mail($email, "Réinitialisation de mot de passe", "Lien: https://fusionel.fr/reset-password?token=$token");
            }
            
            // Toujours retourner succès pour ne pas révéler si l'email existe
            return ['success' => true, 'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé'];
            
        } catch (Exception $e) {
            return ['success' => true, 'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé'];
        }
    }
    
    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword($token, $newPassword) {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Le mot de passe doit contenir au moins 8 caractères'];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM users 
                WHERE password_reset_token = ? 
                AND password_reset_expires > NOW()
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'error' => 'Lien invalide ou expiré'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET password = ?, password_reset_token = NULL, password_reset_expires = NULL 
                WHERE id = ?
            ");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            
            return ['success' => true, 'message' => 'Mot de passe modifié avec succès'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Erreur lors de la réinitialisation'];
        }
    }
    
    /**
     * Vérifier l'email
     */
    public function verifyEmail($token) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM users WHERE email_verification_token = ?
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'error' => 'Lien invalide'];
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE users SET is_verified = 1, email_verification_token = NULL WHERE id = ?
            ");
            $stmt->execute([$user['id']]);
            
            return ['success' => true, 'message' => 'Email vérifié avec succès'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Erreur de vérification'];
        }
    }
    
    /**
     * Changer le mot de passe
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Le nouveau mot de passe doit contenir au moins 8 caractères'];
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'error' => 'Mot de passe actuel incorrect'];
            }
            
            $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            
            return ['success' => true, 'message' => 'Mot de passe modifié avec succès'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Erreur lors du changement'];
        }
    }
    
    /**
     * Obtenir les sessions actives
     */
    public function getActiveSessions($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, device_type, browser, ip_address, last_activity, created_at 
                FROM user_sessions 
                WHERE user_id = ? AND expires_at > NOW()
                ORDER BY last_activity DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Créer les préférences par défaut
     */
    private function createDefaultPreferences($userId, $lookingFor) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_preferences (user_id, looking_for, min_age, max_age, max_distance)
                VALUES (?, ?, 18, 50, 50)
            ");
            $stmt->execute([$userId, $lookingFor]);
        } catch (Exception $e) {
            // Table peut ne pas exister encore
        }
    }
    
    /**
     * Créer les paramètres de notification par défaut
     */
    private function createDefaultNotificationSettings($userId) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_settings (user_id, email_matches, email_messages, email_likes, push_enabled)
                VALUES (?, 1, 1, 1, 1)
            ");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            // Table peut ne pas exister encore
        }
    }
}

/**
 * Fonction helper globale
 */
function auth() {
    static $auth = null;
    if ($auth === null) {
        $auth = new Auth();
    }
    return $auth;
}
