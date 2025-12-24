<?php
/**
 * Modèle Search - Recherche et découverte de profils
 * Fusionel.fr
 */

class Search {
    private $pdo;
    
    public function __construct() {
        $this->pdo = db();
    }
    
    /**
     * Découvrir des profils
     */
    public function discover($userId, $filters = [], $limit = 20) {
        // Récupérer l'utilisateur et ses préférences
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $this->pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Paramètres de recherche
        $lookingFor = $prefs['looking_for'] ?? $user['looking_for'] ?? 'femme';
        $minAge = $filters['min_age'] ?? $prefs['min_age'] ?? 18;
        $maxAge = $filters['max_age'] ?? $prefs['max_age'] ?? 99;
        
        $sql = "
            SELECT 
                u.id, u.firstname, u.birthdate, u.city, u.bio, u.gender,
                u.is_online, u.is_verified, u.profile_completion, u.last_activity,
                u.job, u.height, u.body_type,
                (SELECT filename FROM user_photos WHERE user_id = u.id AND is_primary = 1 LIMIT 1) as photo,
                (SELECT COUNT(*) FROM user_photos WHERE user_id = u.id) as photo_count
            FROM users u
            WHERE u.id != :user_id
            AND u.gender = :looking_for
            AND u.is_banned = 0
            AND TIMESTAMPDIFF(YEAR, u.birthdate, CURDATE()) BETWEEN :min_age AND :max_age
            AND u.id NOT IN (SELECT liked_id FROM likes WHERE liker_id = :user_id2)
            AND u.id NOT IN (SELECT disliked_id FROM dislikes WHERE disliker_id = :user_id3)
            AND u.id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = :user_id4)
            AND u.id NOT IN (SELECT blocker_id FROM blocks WHERE blocked_id = :user_id5)
        ";
        
        // Filtres optionnels
        if (!empty($filters['city'])) {
            $sql .= " AND u.city LIKE :city";
        }
        
        if (!empty($filters['online_only'])) {
            $sql .= " AND u.is_online = 1";
        }
        
        // Tri par pertinence
        $sql .= " ORDER BY u.is_online DESC, u.is_verified DESC, u.profile_completion DESC, RAND()";
        $sql .= " LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id2', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id3', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id4', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id5', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':looking_for', $lookingFor, PDO::PARAM_STR);
        $stmt->bindValue(':min_age', $minAge, PDO::PARAM_INT);
        $stmt->bindValue(':max_age', $maxAge, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        if (!empty($filters['city'])) {
            $stmt->bindValue(':city', '%' . $filters['city'] . '%', PDO::PARAM_STR);
        }
        
        $stmt->execute();
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer l'âge et ajouter les intérêts
        foreach ($profiles as &$profile) {
            $profile['age'] = $this->calculateAge($profile['birthdate']);
            $profile['interests'] = $this->getUserInterests($profile['id']);
        }
        
        return $profiles;
    }
    
    /**
     * Obtenir les nouveaux profils
     */
    public function getNewProfiles($userId, $limit = 10) {
        $filters = [];
        
        $stmt = $this->pdo->prepare("SELECT looking_for FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch();
        $lookingFor = $prefs['looking_for'] ?? 'femme';
        
        $sql = "
            SELECT 
                u.id, u.firstname, u.birthdate, u.city, u.bio, u.gender,
                u.is_online, u.is_verified, u.created_at,
                (SELECT filename FROM user_photos WHERE user_id = u.id AND is_primary = 1 LIMIT 1) as photo
            FROM users u
            WHERE u.id != ?
            AND u.gender = ?
            AND u.is_banned = 0
            AND u.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND u.id NOT IN (SELECT liked_id FROM likes WHERE liker_id = ?)
            AND u.id NOT IN (SELECT disliked_id FROM dislikes WHERE disliker_id = ?)
            ORDER BY u.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $lookingFor, $userId, $userId, $limit]);
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($profiles as &$profile) {
            $profile['age'] = $this->calculateAge($profile['birthdate']);
        }
        
        return $profiles;
    }
    
    /**
     * Obtenir les profils en ligne
     */
    public function getOnlineProfiles($userId, $limit = 20) {
        $stmt = $this->pdo->prepare("SELECT looking_for FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch();
        $lookingFor = $prefs['looking_for'] ?? 'femme';
        
        $sql = "
            SELECT 
                u.id, u.firstname, u.birthdate, u.city, u.bio, u.gender, u.is_verified,
                (SELECT filename FROM user_photos WHERE user_id = u.id AND is_primary = 1 LIMIT 1) as photo
            FROM users u
            WHERE u.id != ?
            AND u.gender = ?
            AND u.is_banned = 0
            AND u.is_online = 1
            AND u.id NOT IN (SELECT liked_id FROM likes WHERE liker_id = ?)
            ORDER BY u.last_activity DESC
            LIMIT ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $lookingFor, $userId, $limit]);
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($profiles as &$profile) {
            $profile['age'] = $this->calculateAge($profile['birthdate']);
            $profile['is_online'] = true;
        }
        
        return $profiles;
    }
    
    /**
     * Obtenir les profils à proximité
     */
    public function getNearbyProfiles($userId, $radius = 25) {
        // Pour l'instant, on retourne les profils de la même ville
        $stmt = $this->pdo->prepare("SELECT city FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user['city']) {
            return [];
        }
        
        return $this->discover($userId, ['city' => $user['city']], 20);
    }
    
    /**
     * Obtenir le profil complet d'un utilisateur
     */
    public function getFullProfile($profileId, $viewerId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? AND is_banned = 0");
        $stmt->execute([$profileId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profile) {
            return null;
        }
        
        unset($profile['password']);
        
        // Calculer l'âge
        $profile['age'] = $this->calculateAge($profile['birthdate']);
        
        // Photos
        $profile['photos'] = $this->getUserPhotos($profileId);
        
        // Intérêts
        $profile['interests'] = $this->getUserInterests($profileId);
        
        // Vérifier si liké
        $stmt = $this->pdo->prepare("SELECT id FROM likes WHERE liker_id = ? AND liked_id = ?");
        $stmt->execute([$viewerId, $profileId]);
        $profile['i_liked'] = (bool)$stmt->fetch();
        
        // Vérifier si il m'a liké
        $stmt = $this->pdo->prepare("SELECT id FROM likes WHERE liker_id = ? AND liked_id = ?");
        $stmt->execute([$profileId, $viewerId]);
        $profile['has_liked_me'] = (bool)$stmt->fetch();
        
        // Vérifier si match
        $stmt = $this->pdo->prepare("
            SELECT id FROM matches 
            WHERE ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?))
            AND is_active = 1
        ");
        $stmt->execute([$viewerId, $profileId, $profileId, $viewerId]);
        $profile['is_match'] = (bool)$stmt->fetch();
        
        // Enregistrer la vue
        $this->recordProfileView($viewerId, $profileId);
        
        return $profile;
    }
    
    /**
     * Enregistrer une vue de profil
     */
    public function recordProfileView($viewerId, $viewedId) {
        if ($viewerId === $viewedId) return;
        
        try {
            // Vérifier si vue récente (moins de 24h)
            $stmt = $this->pdo->prepare("
                SELECT id FROM profile_views 
                WHERE viewer_id = ? AND viewed_id = ? 
                AND viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$viewerId, $viewedId]);
            
            if (!$stmt->fetch()) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO profile_views (viewer_id, viewed_id, viewed_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$viewerId, $viewedId]);
            }
        } catch (Exception $e) {
            // Ignorer les erreurs
        }
    }
    
    /**
     * Obtenir les visiteurs du profil
     */
    public function getProfileViewers($userId) {
        $sql = "
            SELECT 
                u.id, u.firstname, u.birthdate, u.city, u.is_online,
                pv.viewed_at,
                (SELECT filename FROM user_photos WHERE user_id = u.id AND is_primary = 1 LIMIT 1) as photo
            FROM profile_views pv
            JOIN users u ON pv.viewer_id = u.id
            WHERE pv.viewed_id = ?
            AND u.is_banned = 0
            ORDER BY pv.viewed_at DESC
            LIMIT 50
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $viewers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($viewers as &$viewer) {
            $viewer['age'] = $this->calculateAge($viewer['birthdate']);
        }
        
        return $viewers;
    }
    
    /**
     * Obtenir les intérêts d'un utilisateur
     */
    public function getUserInterests($userId) {
        $sql = "
            SELECT i.* FROM interests i
            JOIN user_interests ui ON i.id = ui.interest_id
            WHERE ui.user_id = ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les photos d'un utilisateur
     */
    public function getUserPhotos($userId) {
        $sql = "SELECT * FROM user_photos WHERE user_id = ? ORDER BY is_primary DESC, order_index ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir tous les intérêts disponibles
     */
    public function getAllInterests() {
        $stmt = $this->pdo->query("SELECT * FROM interests ORDER BY category, name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calculer l'âge à partir de la date de naissance
     */
    private function calculateAge($birthdate) {
        if (!$birthdate) return null;
        $birth = new DateTime($birthdate);
        $today = new DateTime();
        return $birth->diff($today)->y;
    }
}
