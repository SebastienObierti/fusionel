<?php
/**
 * Modèle Notification - Gestion des notifications
 * Âme Sœur - Site de rencontre
 */

require_once __DIR__ . '/../config/database.php';

class Notification {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Créer une notification
     */
    public function create(int $userId, string $type, string $title, ?string $content = null, ?int $relatedUserId = null, ?string $entityType = null, ?int $entityId = null): int {
        $sql = "INSERT INTO notifications (user_id, type, title, content, related_user_id, related_entity_type, related_entity_id)
                VALUES (:user_id, :type, :title, :content, :related_user, :entity_type, :entity_id)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'related_user' => $relatedUserId,
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Obtenir les notifications d'un utilisateur
     */
    public function getNotifications(int $userId, int $limit = 50, int $offset = 0, bool $unreadOnly = false): array {
        $sql = "SELECT n.*, 
                       u.firstname as related_user_name,
                       (SELECT filename FROM user_photos WHERE user_id = u.id AND is_primary = 1 LIMIT 1) as related_user_photo
                FROM notifications n
                LEFT JOIN users u ON u.id = n.related_user_id
                WHERE n.user_id = :user_id";
        
        if ($unreadOnly) {
            $sql .= " AND n.is_read = 0";
        }
        
        $sql .= " ORDER BY n.created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtenir le nombre de notifications non lues
     */
    public function getUnreadCount(int $userId): int {
        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(int $notificationId, int $userId): bool {
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    }
    
    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(int $userId): bool {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }
    
    /**
     * Supprimer une notification
     */
    public function delete(int $notificationId, int $userId): bool {
        $sql = "DELETE FROM notifications WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    }
    
    /**
     * Supprimer les anciennes notifications
     */
    public function deleteOld(int $daysOld = 30): int {
        $sql = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['days' => $daysOld]);
        return $stmt->rowCount();
    }
    
    /**
     * Créer une notification de match
     */
    public function notifyMatch(int $user1Id, int $user2Id, int $matchId): void {
        $this->create($user1Id, 'match', 'Nouveau match ! 💕', 'Vous avez un nouveau match !', $user2Id, 'match', $matchId);
        $this->create($user2Id, 'match', 'Nouveau match ! 💕', 'Vous avez un nouveau match !', $user1Id, 'match', $matchId);
    }
    
    /**
     * Créer une notification de like
     */
    public function notifyLike(int $likedUserId, int $likerUserId, bool $isSuperLike = false): void {
        $type = $isSuperLike ? 'super_like' : 'like';
        $title = $isSuperLike ? 'Vous avez reçu un Super Like ! ⭐' : 'Quelqu\'un vous a liké !';
        $this->create($likedUserId, $type, $title, null, $likerUserId, 'like', null);
    }
    
    /**
     * Créer une notification de message
     */
    public function notifyMessage(int $recipientId, int $senderId, int $conversationId): void {
        $this->create($recipientId, 'message', 'Nouveau message', null, $senderId, 'conversation', $conversationId);
    }
    
    /**
     * Créer une notification de visite de profil
     */
    public function notifyProfileView(int $viewedUserId, int $viewerUserId): void {
        // Vérifier les paramètres de notification de l'utilisateur
        $settings = $this->getNotificationSettings($viewedUserId);
        if ($settings && !$settings['push_profile_views']) {
            return;
        }
        
        $this->create($viewedUserId, 'profile_view', 'Quelqu\'un a visité votre profil', null, $viewerUserId, 'profile', $viewerUserId);
    }
    
    /**
     * Créer une notification système
     */
    public function notifySystem(int $userId, string $title, string $content): void {
        $this->create($userId, 'system', $title, $content);
    }
    
    /**
     * Créer une notification promotionnelle
     */
    public function notifyPromo(int $userId, string $title, string $content): void {
        // Vérifier les paramètres de notification
        $settings = $this->getNotificationSettings($userId);
        if ($settings && !$settings['email_promotions']) {
            return;
        }
        
        $this->create($userId, 'promo', $title, $content);
    }
    
    /**
     * Obtenir les paramètres de notification d'un utilisateur
     */
    public function getNotificationSettings(int $userId): ?array {
        $sql = "SELECT * FROM notification_settings WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Mettre à jour les paramètres de notification
     */
    public function updateSettings(int $userId, array $settings): bool {
        $allowedFields = [
            'email_matches', 'email_messages', 'email_likes', 'email_profile_views', 'email_promotions',
            'push_matches', 'push_messages', 'push_likes', 'push_profile_views'
        ];
        
        $updates = [];
        $params = ['user_id' => $userId];
        
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = :$key";
                $params[$key] = (bool) $value;
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $sql = "UPDATE notification_settings SET " . implode(', ', $updates) . " WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Envoyer les notifications par email (à implémenter avec un service email)
     */
    public function sendEmailNotifications(): int {
        $sql = "SELECT n.*, u.email, u.firstname
                FROM notifications n
                JOIN users u ON u.id = n.user_id
                JOIN notification_settings ns ON ns.user_id = u.id
                WHERE n.is_pushed = 0
                AND (
                    (n.type = 'match' AND ns.email_matches = 1) OR
                    (n.type = 'message' AND ns.email_messages = 1) OR
                    (n.type IN ('like', 'super_like') AND ns.email_likes = 1) OR
                    (n.type = 'profile_view' AND ns.email_profile_views = 1)
                )
                LIMIT 100";
        
        $stmt = $this->db->query($sql);
        $notifications = $stmt->fetchAll();
        
        $sentCount = 0;
        
        foreach ($notifications as $notif) {
            // Ici, implémenter l'envoi réel d'email
            // $this->sendEmail($notif['email'], $notif['title'], $notif['content']);
            
            // Marquer comme envoyé
            $sql = "UPDATE notifications SET is_pushed = 1 WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $notif['id']]);
            
            $sentCount++;
        }
        
        return $sentCount;
    }
    
    /**
     * Obtenir les notifications groupées par type
     */
    public function getGroupedNotifications(int $userId): array {
        $sql = "SELECT type, COUNT(*) as count, MAX(created_at) as last_at
                FROM notifications
                WHERE user_id = :user_id AND is_read = 0
                GROUP BY type
                ORDER BY last_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
