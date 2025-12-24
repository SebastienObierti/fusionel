<?php
/**
 * Modèle Match - Gestion des likes et matchs
 * Fusionel.fr
 */

class MatchModel {
    private $pdo;
    
    public function __construct() {
        $this->pdo = db();
    }
    
    /**
     * Liker un profil
     */
    public function like($likerId, $likedId, $isSuperLike = false) {
        // Vérifier que l'utilisateur existe
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ? AND is_banned = 0");
        $stmt->execute([$likedId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'error' => 'Utilisateur non trouvé'];
        }
        
        // Vérifier si déjà liké
        $stmt = $this->pdo->prepare("SELECT id FROM likes WHERE liker_id = ? AND liked_id = ?");
        $stmt->execute([$likerId, $likedId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Déjà liké'];
        }
        
        // Vérifier si bloqué
        $stmt = $this->pdo->prepare("
            SELECT id FROM blocks 
            WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)
        ");
        $stmt->execute([$likerId, $likedId, $likedId, $likerId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Impossible de liker cet utilisateur'];
        }
        
        try {
            // Ajouter le like
            $stmt = $this->pdo->prepare("
                INSERT INTO likes (liker_id, liked_id, is_super_like, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$likerId, $likedId, $isSuperLike ? 1 : 0]);
            
            // Vérifier si c'est un match mutuel
            $stmt = $this->pdo->prepare("SELECT id FROM likes WHERE liker_id = ? AND liked_id = ?");
            $stmt->execute([$likedId, $likerId]);
            $mutualLike = $stmt->fetch();
            
            $isMatch = false;
            $matchId = null;
            $conversationId = null;
            
            if ($mutualLike) {
                // Créer le match
                $matchId = $this->createMatch($likerId, $likedId);
                $isMatch = true;
                
                // Récupérer la conversation créée
                $stmt = $this->pdo->prepare("SELECT id FROM conversations WHERE match_id = ?");
                $stmt->execute([$matchId]);
                $conv = $stmt->fetch();
                $conversationId = $conv ? $conv['id'] : null;
            }
            
            return [
                'success' => true,
                'is_match' => $isMatch,
                'match_id' => $matchId,
                'conversation_id' => $conversationId
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur like: " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors du like'];
        }
    }
    
    /**
     * Créer un match
     */
    private function createMatch($user1Id, $user2Id) {
        // S'assurer que user1_id < user2_id pour éviter les doublons
        $minId = min($user1Id, $user2Id);
        $maxId = max($user1Id, $user2Id);
        
        // Vérifier si le match existe déjà
        $stmt = $this->pdo->prepare("SELECT id FROM matches WHERE user1_id = ? AND user2_id = ?");
        $stmt->execute([$minId, $maxId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return $existing['id'];
        }
        
        // Créer le match
        $stmt = $this->pdo->prepare("
            INSERT INTO matches (user1_id, user2_id, matched_at, is_active)
            VALUES (?, ?, NOW(), 1)
        ");
        $stmt->execute([$minId, $maxId]);
        $matchId = $this->pdo->lastInsertId();
        
        // Créer la conversation
        $stmt = $this->pdo->prepare("
            INSERT INTO conversations (match_id, created_at)
            VALUES (?, NOW())
        ");
        $stmt->execute([$matchId]);
        
        return $matchId;
    }
    
    /**
     * Passer un profil (dislike)
     */
    public function pass($passerId, $passedId) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO dislikes (disliker_id, disliked_id, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE created_at = NOW()
            ");
            return $stmt->execute([$passerId, $passedId]);
        } catch (Exception $e) {
            error_log("Erreur pass: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifier si un utilisateur a liké un autre
     */
    public function hasLiked($likerId, $likedId) {
        $stmt = $this->pdo->prepare("SELECT id FROM likes WHERE liker_id = ? AND liked_id = ?");
        $stmt->execute([$likerId, $likedId]);
        return (bool)$stmt->fetch();
    }
    
    /**
     * Obtenir la liste des matchs
     */
    public function getMatches($userId) {
        $sql = "
            SELECT 
                m.id as match_id,
                m.matched_at,
                CASE WHEN m.user1_id = ? THEN u2.id ELSE u1.id END as id,
                CASE WHEN m.user1_id = ? THEN u2.firstname ELSE u1.firstname END as firstname,
                CASE WHEN m.user1_id = ? THEN u2.birthdate ELSE u1.birthdate END as birthdate,
                CASE WHEN m.user1_id = ? THEN u2.city ELSE u1.city END as city,
                CASE WHEN m.user1_id = ? THEN u2.is_online ELSE u1.is_online END as is_online,
                CASE WHEN m.user1_id = ? THEN u2.is_verified ELSE u1.is_verified END as is_verified,
                c.id as conversation_id,
                c.last_message_preview,
                c.last_message_at,
                (
                    SELECT COUNT(*) 
                    FROM messages msg 
                    WHERE msg.conversation_id = c.id 
                    AND msg.sender_id != ? 
                    AND msg.is_read = 0
                ) as unread_count,
                (
                    SELECT filename 
                    FROM user_photos 
                    WHERE user_id = CASE WHEN m.user1_id = ? THEN u2.id ELSE u1.id END 
                    AND is_primary = 1 
                    LIMIT 1
                ) as photo
            FROM matches m
            JOIN users u1 ON m.user1_id = u1.id
            JOIN users u2 ON m.user2_id = u2.id
            LEFT JOIN conversations c ON c.match_id = m.id
            WHERE (m.user1_id = ? OR m.user2_id = ?)
            AND m.is_active = 1
            AND u1.is_banned = 0 
            AND u2.is_banned = 0
            ORDER BY COALESCE(c.last_message_at, m.matched_at) DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        // 10 paramètres positionnels
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
        
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer l'âge
        foreach ($matches as &$match) {
            if (!empty($match['birthdate'])) {
                $birth = new DateTime($match['birthdate']);
                $match['age'] = $birth->diff(new DateTime())->y;
            } else {
                $match['age'] = null;
            }
        }
        
        return $matches;
    }
    
    /**
     * Obtenir les likes reçus
     */
    public function getLikesReceived($userId) {
        $sql = "
            SELECT 
                u.id, 
                u.firstname, 
                u.birthdate, 
                u.city, 
                u.gender, 
                u.is_online, 
                u.is_verified,
                l.is_super_like, 
                l.created_at as liked_at,
                (
                    SELECT filename 
                    FROM user_photos 
                    WHERE user_id = u.id 
                    AND is_primary = 1 
                    LIMIT 1
                ) as photo
            FROM likes l
            JOIN users u ON l.liker_id = u.id
            WHERE l.liked_id = ?
            AND u.is_banned = 0
            AND l.liker_id NOT IN (
                SELECT liked_id FROM likes WHERE liker_id = ?
            )
            ORDER BY l.created_at DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
        $likes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($likes as &$like) {
            if (!empty($like['birthdate'])) {
                $birth = new DateTime($like['birthdate']);
                $like['age'] = $birth->diff(new DateTime())->y;
            } else {
                $like['age'] = null;
            }
        }
        
        return $likes;
    }
    
    /**
     * Annuler un match
     */
    public function unmatch($matchId, $userId) {
        // Vérifier que l'utilisateur fait partie du match
        $stmt = $this->pdo->prepare("
            SELECT id FROM matches 
            WHERE id = ? AND (user1_id = ? OR user2_id = ?)
        ");
        $stmt->execute([$matchId, $userId, $userId]);
        
        if (!$stmt->fetch()) {
            return false;
        }
        
        // Désactiver le match
        $stmt = $this->pdo->prepare("
            UPDATE matches 
            SET is_active = 0, unmatched_by = ?, unmatched_at = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$userId, $matchId]);
    }
    
    /**
     * Bloquer un utilisateur
     */
    public function block($blockerId, $blockedId, $reason = null) {
        try {
            // Ajouter le blocage
            $stmt = $this->pdo->prepare("
                INSERT INTO blocks (blocker_id, blocked_id, reason, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE reason = VALUES(reason)
            ");
            $stmt->execute([$blockerId, $blockedId, $reason]);
            
            // Désactiver les matchs existants
            $minId = min($blockerId, $blockedId);
            $maxId = max($blockerId, $blockedId);
            
            $stmt = $this->pdo->prepare("
                UPDATE matches 
                SET is_active = 0 
                WHERE user1_id = ? AND user2_id = ?
            ");
            $stmt->execute([$minId, $maxId]);
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur block: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Débloquer un utilisateur
     */
    public function unblock($blockerId, $blockedId) {
        $stmt = $this->pdo->prepare("DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
        return $stmt->execute([$blockerId, $blockedId]);
    }
    
    /**
     * Vérifier si un utilisateur est bloqué
     */
    public function isBlocked($userId1, $userId2) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM blocks 
            WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)
        ");
        $stmt->execute([$userId1, $userId2, $userId2, $userId1]);
        return (bool)$stmt->fetch();
    }
    
    /**
     * Obtenir les utilisateurs bloqués
     */
    public function getBlockedUsers($userId) {
        $sql = "
            SELECT 
                u.id, 
                u.firstname, 
                b.created_at as blocked_at,
                (
                    SELECT filename 
                    FROM user_photos 
                    WHERE user_id = u.id 
                    AND is_primary = 1 
                    LIMIT 1
                ) as photo
            FROM blocks b
            JOIN users u ON b.blocked_id = u.id
            WHERE b.blocker_id = ?
            ORDER BY b.created_at DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les statistiques de match
     */
    public function getMatchStats($userId) {
        $stats = [];
        
        // Nombre de likes envoyés
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM likes WHERE liker_id = ?");
        $stmt->execute([$userId]);
        $stats['likes_sent'] = (int)$stmt->fetchColumn();
        
        // Nombre de likes reçus
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM likes WHERE liked_id = ?");
        $stmt->execute([$userId]);
        $stats['likes_received'] = (int)$stmt->fetchColumn();
        
        // Nombre de matchs actifs
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM matches 
            WHERE (user1_id = ? OR user2_id = ?) AND is_active = 1
        ");
        $stmt->execute([$userId, $userId]);
        $stats['matches'] = (int)$stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * Vérifier les limites de likes quotidiens
     */
    public function checkDailyLikeLimit($userId) {
        $stmt = $this->pdo->prepare("
            SELECT daily_likes_count, daily_likes_reset_date, subscription_type 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['allowed' => false, 'remaining' => 0];
        }
        
        // Définir les limites selon l'abonnement
        $limits = [
            'free' => 10,
            'premium' => 50,
            'vip' => 999999 // illimité
        ];
        
        $limit = $limits[$user['subscription_type']] ?? 10;
        $today = date('Y-m-d');
        
        // Réinitialiser si nouveau jour
        if ($user['daily_likes_reset_date'] !== $today) {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET daily_likes_count = 0, daily_likes_reset_date = ? 
                WHERE id = ?
            ");
            $stmt->execute([$today, $userId]);
            $user['daily_likes_count'] = 0;
        }
        
        $remaining = max(0, $limit - $user['daily_likes_count']);
        
        return [
            'allowed' => $remaining > 0,
            'remaining' => $remaining,
            'limit' => $limit,
            'used' => $user['daily_likes_count']
        ];
    }
    
    /**
     * Incrémenter le compteur de likes quotidiens
     */
    public function incrementDailyLikeCount($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET daily_likes_count = daily_likes_count + 1 
            WHERE id = ?
        ");
        return $stmt->execute([$userId]);
    }
}