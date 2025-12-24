<?php
/**
 * Modèle Photo - Gestion des photos utilisateurs
 * Âme Sœur - Site de rencontre
 */

require_once __DIR__ . '/../config/database.php';

class Photo {
    private $db;
    private $uploadDir;
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    private $maxFileSize = 10 * 1024 * 1024; // 10MB
    private $maxPhotos = 6;
    
    public function __construct() {
        $this->db = db();
        $this->uploadDir = __DIR__ . '/../uploads/photos/';
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Upload une photo
     */
    public function upload(int $userId, array $file): array {
        if ($this->getPhotoCount($userId) >= $this->maxPhotos) {
            return ['success' => false, 'error' => 'Nombre maximum de photos atteint'];
        }
        
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $userId . '_' . uniqid() . '.' . $extension;
        $filepath = $this->uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'error' => 'Erreur lors de l\'upload'];
        }
        
        $this->processImage($filepath);
        $thumbnailPath = $this->createThumbnail($filepath, $filename);
        $isFirst = $this->getPhotoCount($userId) === 0;
        
        $sql = "INSERT INTO user_photos (user_id, filename, original_filename, filepath, thumbnail_path, is_primary, order_position)
                VALUES (:user_id, :filename, :original, :filepath, :thumbnail, :is_primary, :order_pos)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'filename' => $filename,
            'original' => $file['name'],
            'filepath' => '/uploads/photos/' . $filename,
            'thumbnail' => $thumbnailPath,
            'is_primary' => $isFirst,
            'order_pos' => $this->getNextOrderPosition($userId)
        ]);
        
        $photoId = (int) $this->db->lastInsertId();
        
        if ($isFirst) {
            $sql = "UPDATE users SET profile_photo_id = :photo_id WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['photo_id' => $photoId, 'user_id' => $userId]);
        }
        
        return [
            'success' => true,
            'photo_id' => $photoId,
            'filename' => $filename,
            'filepath' => '/uploads/photos/' . $filename,
            'thumbnail' => $thumbnailPath
        ];
    }
    
    /**
     * Supprimer une photo
     */
    public function delete(int $photoId, int $userId): array {
        $photo = $this->getPhoto($photoId);
        if (!$photo || $photo['user_id'] != $userId) {
            return ['success' => false, 'error' => 'Photo non trouvée'];
        }
        
        $filepath = $this->uploadDir . $photo['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        if ($photo['thumbnail_path']) {
            $thumbPath = __DIR__ . '/..' . $photo['thumbnail_path'];
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }
        
        $sql = "DELETE FROM user_photos WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $photoId]);
        
        if ($photo['is_primary']) {
            $this->setNextAsPrimary($userId);
        }
        
        return ['success' => true];
    }
    
    /**
     * Définir une photo comme principale
     */
    public function setPrimary(int $photoId, int $userId): array {
        $photo = $this->getPhoto($photoId);
        if (!$photo || $photo['user_id'] != $userId) {
            return ['success' => false, 'error' => 'Photo non trouvée'];
        }
        
        $sql = "UPDATE user_photos SET is_primary = 0 WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $sql = "UPDATE user_photos SET is_primary = 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $photoId]);
        
        $sql = "UPDATE users SET profile_photo_id = :photo_id WHERE id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['photo_id' => $photoId, 'user_id' => $userId]);
        
        return ['success' => true];
    }
    
    /**
     * Réordonner les photos
     */
    public function reorder(int $userId, array $photoIds): array {
        $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
        $sql = "SELECT COUNT(*) FROM user_photos WHERE id IN ($placeholders) AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([...$photoIds, $userId]);
        
        if ($stmt->fetchColumn() != count($photoIds)) {
            return ['success' => false, 'error' => 'Photos invalides'];
        }
        
        foreach ($photoIds as $position => $photoId) {
            $sql = "UPDATE user_photos SET order_position = :pos WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['pos' => $position, 'id' => $photoId]);
        }
        
        return ['success' => true];
    }
    
    /**
     * Obtenir les photos d'un utilisateur
     */
    public function getUserPhotos(int $userId): array {
        $sql = "SELECT * FROM user_photos WHERE user_id = :user_id ORDER BY is_primary DESC, order_position ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obtenir une photo par ID
     */
    public function getPhoto(int $photoId): ?array {
        $sql = "SELECT * FROM user_photos WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $photoId]);
        $photo = $stmt->fetch();
        return $photo ?: null;
    }
    
    /**
     * Valider un fichier uploadé
     */
    private function validateFile(array $file): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Erreur lors de l\'upload'];
        }
        
        if ($file['size'] > $this->maxFileSize) {
            return ['valid' => false, 'error' => 'Fichier trop volumineux (max 10MB)'];
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            return ['valid' => false, 'error' => 'Type de fichier non autorisé'];
        }
        
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            return ['valid' => false, 'error' => 'Fichier image invalide'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Traiter et optimiser une image
     */
    private function processImage(string $filepath): void {
        $imageInfo = getimagesize($filepath);
        $maxWidth = 1200;
        $maxHeight = 1600;
        $quality = 85;
        
        switch ($imageInfo['mime']) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($filepath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($filepath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($filepath);
                break;
            default:
                return;
        }
        
        if (!$source) return;
        
        $width = imagesx($source);
        $height = imagesy($source);
        
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        if ($ratio < 1) {
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
            
            $destination = imagecreatetruecolor($newWidth, $newHeight);
            
            if ($imageInfo['mime'] === 'image/png') {
                imagealphablending($destination, false);
                imagesavealpha($destination, true);
            }
            
            imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            
            switch ($imageInfo['mime']) {
                case 'image/jpeg':
                    imagejpeg($destination, $filepath, $quality);
                    break;
                case 'image/png':
                    imagepng($destination, $filepath, 8);
                    break;
                case 'image/webp':
                    imagewebp($destination, $filepath, $quality);
                    break;
            }
            
            imagedestroy($destination);
        }
        
        imagedestroy($source);
    }
    
    /**
     * Créer une miniature
     */
    private function createThumbnail(string $filepath, string $filename): string {
        $thumbDir = $this->uploadDir . 'thumbnails/';
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }
        
        $thumbPath = $thumbDir . 'thumb_' . $filename;
        $thumbSize = 200;
        
        $imageInfo = getimagesize($filepath);
        
        switch ($imageInfo['mime']) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($filepath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($filepath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($filepath);
                break;
            default:
                return '';
        }
        
        if (!$source) return '';
        
        $width = imagesx($source);
        $height = imagesy($source);
        
        $size = min($width, $height);
        $x = ($width - $size) / 2;
        $y = ($height - $size) / 2;
        
        $thumbnail = imagecreatetruecolor($thumbSize, $thumbSize);
        
        if ($imageInfo['mime'] === 'image/png') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }
        
        imagecopyresampled($thumbnail, $source, 0, 0, (int)$x, (int)$y, $thumbSize, $thumbSize, $size, $size);
        imagejpeg($thumbnail, $thumbPath, 80);
        
        imagedestroy($source);
        imagedestroy($thumbnail);
        
        return '/uploads/photos/thumbnails/thumb_' . $filename;
    }
    
    private function getPhotoCount(int $userId): int {
        $sql = "SELECT COUNT(*) FROM user_photos WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
    
    private function getNextOrderPosition(int $userId): int {
        $sql = "SELECT COALESCE(MAX(order_position), -1) + 1 FROM user_photos WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
    
    private function setNextAsPrimary(int $userId): void {
        $sql = "SELECT id FROM user_photos WHERE user_id = :user_id ORDER BY order_position ASC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $photoId = $stmt->fetchColumn();
        
        if ($photoId) {
            $sql = "UPDATE user_photos SET is_primary = 1 WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $photoId]);
            
            $sql = "UPDATE users SET profile_photo_id = :photo_id WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['photo_id' => $photoId, 'user_id' => $userId]);
        } else {
            $sql = "UPDATE users SET profile_photo_id = NULL WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
        }
    }
    
    /**
     * Demander la vérification d'une photo
     */
    public function requestVerification(int $userId, array $selfieFile): array {
        $validation = $this->validateFile($selfieFile);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        $extension = pathinfo($selfieFile['name'], PATHINFO_EXTENSION);
        $filename = 'verify_' . $userId . '_' . uniqid() . '.' . $extension;
        $verifyDir = $this->uploadDir . 'verification/';
        
        if (!is_dir($verifyDir)) {
            mkdir($verifyDir, 0755, true);
        }
        
        $filepath = $verifyDir . $filename;
        
        if (!move_uploaded_file($selfieFile['tmp_name'], $filepath)) {
            return ['success' => false, 'error' => 'Erreur lors de l\'upload'];
        }
        
        // Récupérer la photo de profil
        $sql = "SELECT filepath FROM user_photos WHERE user_id = :user_id AND is_primary = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $profilePhoto = $stmt->fetchColumn();
        
        if (!$profilePhoto) {
            return ['success' => false, 'error' => 'Aucune photo de profil'];
        }
        
        // Créer la demande de vérification
        $sql = "INSERT INTO verification_requests (user_id, photo_url, selfie_url)
                VALUES (:user_id, :photo_url, :selfie_url)
                ON DUPLICATE KEY UPDATE selfie_url = :selfie_url2, status = 'pending', created_at = NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'photo_url' => $profilePhoto,
            'selfie_url' => '/uploads/photos/verification/' . $filename,
            'selfie_url2' => '/uploads/photos/verification/' . $filename
        ]);
        
        return ['success' => true, 'message' => 'Demande de vérification envoyée'];
    }
}
