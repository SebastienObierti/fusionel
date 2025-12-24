<?php
/**
 * Modèle Message - Gestion des messages et conversations
 * Fusionel.fr
 */

class Message {
    private $pdo;
    
    public function __construct() {
        $this->pdo = db();
    }
    
    /**
     * Envoyer un message
     */
    public function send($conversationId, $senderId, $content, $type = 'text', $mediaUrl = null) {
        // Vérifier que l'utilisateur fait partie de la conversation
        if (!$this->isParticipant($conversationId, $senderId)) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO messages (conversation_id, sender_id, content, message_type, media_url, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$conversationId, $senderId, $content, $type, $mediaUrl]);
            $messageId = $this->pdo->lastInsertId();
            
            // Mettre à jour la conversation
            $stmt = $this->pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$conversationId]);
            
            return $messageId;
            
        } catch (Exception $e) {
            error_log("Erreur envoi message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtenir les messages d'une conversation
     */
    public function getMessages($conversationId, $userId, $limit = 50, $beforeId = null) {
        if (!$this->isParticipant($conversationId, $userId)) {
            return [];
        }
        
        $sql = "
            SELECT m.*, u.firstname as sender_name,
                   (SELECT filename FROM user_photos WHERE user_id = m.sender_id AND is_primary = 1 LIMIT 1) as sender_photo
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ?
        ";
        
        $params = [$conversationId];
        
        if ($beforeId) {
            $sql .= " AND m.id < ?";
            $params[] = $beforeId;
        }
        
        $sql .= " ORDER BY m.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Inverser pour avoir l'ordre chronologique
        return array_reverse($messages);
    }
    
    /**
     * Marquer les messages comme lus
     */
    public function markAsRead($conversationId, $userId) {
        $stmt = $this->pdo->prepare("
            UPDATE messages 
            SET read_at = NOW() 
            WHERE conversation_id = ? AND sender_id != ? AND read_at IS NULL
        ");
        return $stmt->execute([$conversationId, $userId]);
    }
    
    /**
     * Obtenir les conversations de l'utilisateur
     */
    public function getConversations($userId) {
        $sql = "
            SELECT 
                c.id,
                c.match_id,
                c.created_at,
                c.updated_at,
                CASE WHEN m.user1_id = :user_id THEN u2.id ELSE u1.id END as other_user_id,
                CASE WHEN m.user1_id = :user_id2 THEN u2.firstname ELSE u1.firstname END as firstname,
                CASE WHEN m.user1_id = :user_id3 THEN u2.is_online ELSE u1.is_online END as is_online,
                (SELECT filename FROM user_photos WHERE user_id = CASE WHEN m.user1_id = :user_id4 THEN u2.id ELSE u1.id END AND is_primary = 1 LIMIT 1) as other_photo,
                (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_preview,
                (SELECT sender_id FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_sender_id,
                (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_at,
                (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != :user_id5 AND read_at IS NULL) as unread_count
            FROM conversations c
            JOIN matches m ON c.match_id = m.id
            JOIN users u1 ON m.user1_id = u1.id
            JOIN users u2 ON m.user2_id = u2.id
            WHERE (m.user1_id = :user_id6 OR m.user2_id = :user_id7)
            AND m.is_active = 1
            AND u1.is_banned = 0 AND u2.is_banned = 0
            ORDER BY COALESCE(c.updated_at, c.created_at) DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $params = [];
        for ($i = 1; $i <= 7; $i++) {
            $params[":user_id" . ($i > 1 ? $i : '')] = $userId;
        }
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir une conversation
     */
    public function getConversation($conversationId, $userId) {
        $sql = "
            SELECT 
                c.id,
                c.match_id,
                CASE WHEN m.user1_id = ? THEN u2.id ELSE u1.id END as other_user_id,
                CASE WHEN m.user1_id = ? THEN u2.firstname ELSE u1.firstname END as firstname,
                CASE WHEN m.user1_id = ? THEN u2.is_online ELSE u1.is_online END as is_online,
                (SELECT filename FROM user_photos WHERE user_id = CASE WHEN m.user1_id = ? THEN u2.id ELSE u1.id END AND is_primary = 1 LIMIT 1) as other_photo
            FROM conversations c
            JOIN matches m ON c.match_id = m.id
            JOIN users u1 ON m.user1_id = u1.id
            JOIN users u2 ON m.user2_id = u2.id
            WHERE c.id = ?
            AND (m.user1_id = ? OR m.user2_id = ?)
            AND m.is_active = 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $userId, $conversationId, $userId, $userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Vérifier si l'utilisateur est participant à la conversation
     */
    public function isParticipant($conversationId, $userId) {
        $sql = "
            SELECT c.id FROM conversations c
            JOIN matches m ON c.match_id = m.id
            WHERE c.id = ?
            AND (m.user1_id = ? OR m.user2_id = ?)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId, $userId, $userId]);
        
        return (bool)$stmt->fetch();
    }
    
    /**
     * Obtenir le nombre de messages non lus
     */
    public function getUnreadCount($userId) {
        $sql = "
            SELECT COUNT(*) as count
            FROM messages msg
            JOIN conversations c ON msg.conversation_id = c.id
            JOIN matches m ON c.match_id = m.id
            WHERE msg.sender_id != ?
            AND msg.read_at IS NULL
            AND (m.user1_id = ? OR m.user2_id = ?)
            AND m.is_active = 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $userId]);
        $result = $stmt->fetch();
        
        return (int)($result['count'] ?? 0);
    }
    
    /**
     * Supprimer un message (soft delete)
     */
    public function deleteMessage($messageId, $userId) {
        $stmt = $this->pdo->prepare("
            UPDATE messages 
            SET deleted_by_sender = CASE WHEN sender_id = ? THEN 1 ELSE deleted_by_sender END,
                deleted_by_receiver = CASE WHEN sender_id != ? THEN 1 ELSE deleted_by_receiver END
            WHERE id = ?
        ");
        return $stmt->execute([$userId, $userId, $messageId]);
    }
    
    /**
     * Obtenir les icebreakers
     */
    public function getIcebreakers() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM icebreakers WHERE is_active = 1 ORDER BY RAND() LIMIT 5");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Table peut ne pas exister
            return [
                ['id' => 1, 'text' => 'Salut ! Comment vas-tu ?'],
                ['id' => 2, 'text' => 'Ton profil m\'a vraiment plu !'],
                ['id' => 3, 'text' => 'On a des points communs je crois...'],
                ['id' => 4, 'text' => 'Quel est ton film préféré ?'],
                ['id' => 5, 'text' => 'Tu fais quoi ce week-end ?']
            ];
        }
    }
    
    /**
     * Rechercher dans les messages
     */
    public function search($userId, $query) {
        if (strlen($query) < 2) {
            return [];
        }
        
        $sql = "
            SELECT msg.*, c.id as conversation_id,
                   u.firstname as sender_name
            FROM messages msg
            JOIN conversations c ON msg.conversation_id = c.id
            JOIN matches m ON c.match_id = m.id
            JOIN users u ON msg.sender_id = u.id
            WHERE (m.user1_id = ? OR m.user2_id = ?)
            AND msg.content LIKE ?
            ORDER BY msg.created_at DESC
            LIMIT 50
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $userId, '%' . $query . '%']);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les statistiques de messages
     */
    public function getMessageStats($userId) {
        $stats = [];
        
        // Messages envoyés
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM messages msg
            JOIN conversations c ON msg.conversation_id = c.id
            JOIN matches m ON c.match_id = m.id
            WHERE msg.sender_id = ?
            AND (m.user1_id = ? OR m.user2_id = ?)
        ");
        $stmt->execute([$userId, $userId, $userId]);
        $stats['sent'] = (int)$stmt->fetchColumn();
        
        // Messages reçus
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM messages msg
            JOIN conversations c ON msg.conversation_id = c.id
            JOIN matches m ON c.match_id = m.id
            WHERE msg.sender_id != ?
            AND (m.user1_id = ? OR m.user2_id = ?)
        ");
        $stmt->execute([$userId, $userId, $userId]);
        $stats['received'] = (int)$stmt->fetchColumn();
        
        // Conversations actives
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT c.id) FROM conversations c
            JOIN matches m ON c.match_id = m.id
            WHERE (m.user1_id = ? OR m.user2_id = ?)
            AND m.is_active = 1
        ");
        $stmt->execute([$userId, $userId]);
        $stats['conversations'] = (int)$stmt->fetchColumn();
        
        return $stats;
    }
}
