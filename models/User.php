<?php
/**
 * Modèle User - Gestion des utilisateurs
 * Fusionel.fr
 */

class User {
    private $pdo;
    
    public function __construct() {
        $this->pdo = db();
    }
    
    /**
     * Trouver un utilisateur par ID
     */
    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            unset($user['password']);
        }
        
        return $user;
    }
    
    /**
     * Trouver un utilisateur par email
     */
    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mettre à jour le profil
     */
    public function updateProfile($userId, $data) {
        $allowedFields = [
            'firstname', 'lastname', 'bio', 'job', 'company', 'education',
            'city', 'postal_code', 'country', 'height', 'body_type',
            'smoking', 'drinking', 'children', 'wants_children', 'religion',
            'looking_for'
        ];
        
        $updates = [];
        $values = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $values[] = $data[$field] ?: null;
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $values[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($values);
        
        // Recalculer la complétion du profil
        if ($result) {
            $this->updateProfileCompletion($userId);
        }
        
        return $result;
    }
    
    /**
     * Obtenir les préférences
     */
    public function getPreferences($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mettre à jour les préférences
     */
    public function updatePreferences($userId, $data) {
        // Vérifier si les préférences existent
        $stmt = $this->pdo->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            $stmt = $this->pdo->prepare("
                UPDATE user_preferences SET
                    looking_for = COALESCE(?, looking_for),
                    min_age = COALESCE(?, min_age),
                    max_age = COALESCE(?, max_age),
                    max_distance = COALESCE(?, max_distance),
                    show_with_photo_only = COALESCE(?, show_with_photo_only),
                    show_verified_only = COALESCE(?, show_verified_only)
                WHERE user_id = ?
            ");
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_preferences (looking_for, min_age, max_age, max_distance, show_with_photo_only, show_verified_only, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
        }
        
        return $stmt->execute([
            $data['looking_for'] ?? null,
            $data['min_age'] ?? 18,
            $data['max_age'] ?? 50,
            $data['max_distance'] ?? 50,
            isset($data['show_with_photo_only']) ? ($data['show_with_photo_only'] ? 1 : 0) : 0,
            isset($data['show_verified_only']) ? ($data['show_verified_only'] ? 1 : 0) : 0,
            $userId
        ]);
    }
    
    /**
     * Obtenir les statistiques de l'utilisateur
     */
    public function getStats($userId) {
        $stats = [];
        
        // Likes reçus
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM likes WHERE liked_id = ?");
        $stmt->execute([$userId]);
        $stats['likes_received'] = (int)$stmt->fetchColumn();
        
        // Matchs
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM matches WHERE (user1_id = ? OR user2_id = ?) AND is_active = 1");
        $stmt->execute([$userId, $userId]);
        $stats['matches'] = (int)$stmt->fetchColumn();
        
        // Vues du profil (30 derniers jours)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM profile_views 
            WHERE viewed_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$userId]);
        $stats['profile_views'] = (int)$stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * Vérifier la limite de likes quotidiens
     */
    public function checkDailyLikeLimit($userId) {
        $user = $this->findById($userId);
        
        // Premium et VIP ont des likes illimités
        if ($user && in_array($user['subscription_type'], ['premium', 'vip'])) {
            return [
                'can_like' => true,
                'remaining' => PHP_INT_MAX,
                'limit' => PHP_INT_MAX
            ];
        }
        
        $limit = 5; // Limite pour les comptes gratuits
        
        // Compter les likes du jour
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM likes 
            WHERE liker_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$userId]);
        $count = (int)$stmt->fetchColumn();
        
        return [
            'can_like' => $count < $limit,
            'remaining' => max(0, $limit - $count),
            'limit' => $limit,
            'reset_at' => date('Y-m-d 00:00:00', strtotime('+1 day'))
        ];
    }
    
    /**
     * Incrémenter le compteur de likes quotidiens
     */
    public function incrementDailyLikes($userId) {
        // Les likes sont comptés automatiquement via la table likes
        return true;
    }
    
    /**
     * Mettre à jour la complétion du profil
     */
    public function updateProfileCompletion($userId) {
        $user = $this->findById($userId);
        if (!$user) return;
        
        $fields = ['firstname', 'bio', 'job', 'city', 'height', 'body_type'];
        $filled = 0;
        
        foreach ($fields as $field) {
            if (!empty($user[$field])) {
                $filled++;
            }
        }
        
        // Vérifier si a des photos
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM user_photos WHERE user_id = ?");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() > 0) {
            $filled += 2;
        }
        
        $completion = min(100, round(($filled / 8) * 100));
        
        $stmt = $this->pdo->prepare("UPDATE users SET profile_completion = ? WHERE id = ?");
        $stmt->execute([$completion, $userId]);
    }
    
    /**
     * Mettre à jour le statut en ligne
     */
    public function updateOnlineStatus($userId, $isOnline = true) {
        $stmt = $this->pdo->prepare("UPDATE users SET is_online = ?, last_activity = NOW() WHERE id = ?");
        return $stmt->execute([$isOnline ? 1 : 0, $userId]);
    }
    
    /**
     * Bannir un utilisateur
     */
    public function ban($userId, $reason = null) {
        $stmt = $this->pdo->prepare("UPDATE users SET is_banned = 1, ban_reason = ? WHERE id = ?");
        return $stmt->execute([$reason, $userId]);
    }
    
    /**
     * Débannir un utilisateur
     */
    public function unban($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL WHERE id = ?");
        return $stmt->execute([$userId]);
    }
    
    /**
     * Supprimer un compte
     */
    public function delete($userId) {
        // Soft delete ou hard delete selon votre politique
        $stmt = $this->pdo->prepare("UPDATE users SET is_banned = 1, email = CONCAT('deleted_', id, '@deleted.com') WHERE id = ?");
        return $stmt->execute([$userId]);
    }
}
