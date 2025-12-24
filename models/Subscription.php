<?php
/**
 * Modèle Subscription - Gestion des abonnements et paiements
 * Âme Sœur - Site de rencontre
 */

require_once __DIR__ . '/../config/database.php';

class Subscription {
    private $db;
    
    // Plans disponibles
    private $plans = [
        'premium_monthly' => [
            'type' => 'premium',
            'name' => 'Premium Mensuel',
            'price' => 19.00,
            'period' => 'monthly',
            'duration_days' => 30,
            'features' => [
                'likes_unlimited' => true,
                'messages_unlimited' => true,
                'see_who_liked' => true,
                'boost_monthly' => 1,
                'advanced_filters' => true,
                'incognito_mode' => true,
                'super_likes_daily' => 5
            ]
        ],
        'premium_quarterly' => [
            'type' => 'premium',
            'name' => 'Premium Trimestriel',
            'price' => 45.00,
            'period' => 'quarterly',
            'duration_days' => 90,
            'features' => [
                'likes_unlimited' => true,
                'messages_unlimited' => true,
                'see_who_liked' => true,
                'boost_monthly' => 1,
                'advanced_filters' => true,
                'incognito_mode' => true,
                'super_likes_daily' => 5
            ]
        ],
        'premium_yearly' => [
            'type' => 'premium',
            'name' => 'Premium Annuel',
            'price' => 144.00,
            'period' => 'yearly',
            'duration_days' => 365,
            'features' => [
                'likes_unlimited' => true,
                'messages_unlimited' => true,
                'see_who_liked' => true,
                'boost_monthly' => 1,
                'advanced_filters' => true,
                'incognito_mode' => true,
                'super_likes_daily' => 5
            ]
        ],
        'vip_monthly' => [
            'type' => 'vip',
            'name' => 'VIP Mensuel',
            'price' => 39.00,
            'period' => 'monthly',
            'duration_days' => 30,
            'features' => [
                'likes_unlimited' => true,
                'messages_unlimited' => true,
                'see_who_liked' => true,
                'boost_monthly' => 5,
                'advanced_filters' => true,
                'incognito_mode' => true,
                'super_likes_daily' => -1, // Illimité
                'priority_messages' => true,
                'verified_badge' => true,
                'dedicated_support' => true
            ]
        ],
        'vip_quarterly' => [
            'type' => 'vip',
            'name' => 'VIP Trimestriel',
            'price' => 99.00,
            'period' => 'quarterly',
            'duration_days' => 90,
            'features' => [
                'likes_unlimited' => true,
                'messages_unlimited' => true,
                'see_who_liked' => true,
                'boost_monthly' => 5,
                'advanced_filters' => true,
                'incognito_mode' => true,
                'super_likes_daily' => -1,
                'priority_messages' => true,
                'verified_badge' => true,
                'dedicated_support' => true
            ]
        ],
        'vip_yearly' => [
            'type' => 'vip',
            'name' => 'VIP Annuel',
            'price' => 299.00,
            'period' => 'yearly',
            'duration_days' => 365,
            'features' => [
                'likes_unlimited' => true,
                'messages_unlimited' => true,
                'see_who_liked' => true,
                'boost_monthly' => 5,
                'advanced_filters' => true,
                'incognito_mode' => true,
                'super_likes_daily' => -1,
                'priority_messages' => true,
                'verified_badge' => true,
                'dedicated_support' => true
            ]
        ]
    ];
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Obtenir tous les plans disponibles
     */
    public function getPlans(): array {
        return $this->plans;
    }
    
    /**
     * Obtenir un plan spécifique
     */
    public function getPlan(string $planId): ?array {
        return $this->plans[$planId] ?? null;
    }
    
    /**
     * Obtenir l'abonnement actif d'un utilisateur
     */
    public function getActiveSubscription(int $userId): ?array {
        $sql = "SELECT * FROM subscriptions 
                WHERE user_id = :user_id AND status = 'active' AND ends_at > NOW()
                ORDER BY ends_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Créer un abonnement
     */
    public function create(int $userId, string $planId, string $paymentMethod = 'card'): array {
        $plan = $this->getPlan($planId);
        if (!$plan) {
            return ['success' => false, 'error' => 'Plan invalide'];
        }
        
        // Vérifier si l'utilisateur a déjà un abonnement actif
        $currentSub = $this->getActiveSubscription($userId);
        
        $startsAt = new DateTime();
        if ($currentSub) {
            // Prolonger l'abonnement existant
            $startsAt = new DateTime($currentSub['ends_at']);
        }
        
        $endsAt = clone $startsAt;
        $endsAt->modify("+{$plan['duration_days']} days");
        
        try {
            $this->db->beginTransaction();
            
            // Créer l'abonnement
            $sql = "INSERT INTO subscriptions (user_id, plan_type, price, billing_period, status, payment_method, starts_at, ends_at)
                    VALUES (:user_id, :plan_type, :price, :period, 'pending', :payment_method, :starts_at, :ends_at)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'plan_type' => $plan['type'],
                'price' => $plan['price'],
                'period' => $plan['period'],
                'payment_method' => $paymentMethod,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt->format('Y-m-d H:i:s')
            ]);
            
            $subscriptionId = (int) $this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'plan' => $plan,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                'amount' => $plan['price']
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Erreur création abonnement: " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de la création'];
        }
    }
    
    /**
     * Activer un abonnement après paiement
     */
    public function activate(int $subscriptionId, string $transactionId): array {
        $sql = "SELECT * FROM subscriptions WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $subscriptionId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            return ['success' => false, 'error' => 'Abonnement non trouvé'];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Activer l'abonnement
            $sql = "UPDATE subscriptions SET status = 'active', payment_id = :transaction_id WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $subscriptionId, 'transaction_id' => $transactionId]);
            
            // Créer le paiement
            $sql = "INSERT INTO payments (user_id, subscription_id, amount, payment_method, payment_provider, transaction_id, status, description)
                    VALUES (:user_id, :sub_id, :amount, :method, 'stripe', :transaction_id, 'completed', :description)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $subscription['user_id'],
                'sub_id' => $subscriptionId,
                'amount' => $subscription['price'],
                'method' => $subscription['payment_method'],
                'transaction_id' => $transactionId,
                'description' => "Abonnement {$subscription['plan_type']} - {$subscription['billing_period']}"
            ]);
            
            // Mettre à jour l'utilisateur
            $sql = "UPDATE users SET 
                    subscription_type = :type,
                    subscription_end_date = :end_date,
                    is_premium = :is_premium,
                    is_vip = :is_vip
                    WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'type' => $subscription['plan_type'],
                'end_date' => $subscription['ends_at'],
                'is_premium' => in_array($subscription['plan_type'], ['premium', 'vip']),
                'is_vip' => $subscription['plan_type'] === 'vip',
                'user_id' => $subscription['user_id']
            ]);
            
            // Créer notification
            $sql = "INSERT INTO notifications (user_id, type, title, content)
                    VALUES (:user_id, 'system', 'Abonnement activé', :content)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $subscription['user_id'],
                'content' => "Votre abonnement {$subscription['plan_type']} est maintenant actif jusqu'au " . date('d/m/Y', strtotime($subscription['ends_at']))
            ]);
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Erreur activation abonnement: " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur lors de l\'activation'];
        }
    }
    
    /**
     * Annuler un abonnement
     */
    public function cancel(int $subscriptionId, int $userId): array {
        $sql = "SELECT * FROM subscriptions WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $subscriptionId, 'user_id' => $userId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            return ['success' => false, 'error' => 'Abonnement non trouvé'];
        }
        
        if ($subscription['status'] !== 'active') {
            return ['success' => false, 'error' => 'Cet abonnement n\'est pas actif'];
        }
        
        $sql = "UPDATE subscriptions SET status = 'cancelled', auto_renew = 0, cancelled_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $subscriptionId]);
        
        // L'utilisateur garde ses avantages jusqu'à la fin de la période
        
        return [
            'success' => true,
            'message' => 'Abonnement annulé. Vous conservez vos avantages jusqu\'au ' . date('d/m/Y', strtotime($subscription['ends_at']))
        ];
    }
    
    /**
     * Vérifier et expirer les abonnements
     */
    public function checkExpiredSubscriptions(): int {
        // Marquer les abonnements expirés
        $sql = "UPDATE subscriptions SET status = 'expired' 
                WHERE status = 'active' AND ends_at < NOW()";
        $stmt = $this->db->query($sql);
        $expiredCount = $stmt->rowCount();
        
        // Réinitialiser les utilisateurs expirés
        $sql = "UPDATE users u
                SET subscription_type = 'free', is_premium = 0, is_vip = 0
                WHERE NOT EXISTS (
                    SELECT 1 FROM subscriptions s 
                    WHERE s.user_id = u.id AND s.status = 'active' AND s.ends_at > NOW()
                )
                AND u.subscription_type != 'free'";
        $this->db->query($sql);
        
        return $expiredCount;
    }
    
    /**
     * Obtenir l'historique des abonnements
     */
    public function getHistory(int $userId): array {
        $sql = "SELECT * FROM subscriptions WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Obtenir l'historique des paiements
     */
    public function getPaymentHistory(int $userId): array {
        $sql = "SELECT p.*, s.plan_type, s.billing_period 
                FROM payments p
                LEFT JOIN subscriptions s ON s.id = p.subscription_id
                WHERE p.user_id = :user_id 
                ORDER BY p.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Vérifier si l'utilisateur a accès à une fonctionnalité
     */
    public function hasFeature(int $userId, string $feature): bool {
        $subscription = $this->getActiveSubscription($userId);
        
        if (!$subscription) {
            // Fonctionnalités gratuites
            $freeFeatures = ['basic_search', 'limited_likes', 'limited_messages'];
            return in_array($feature, $freeFeatures);
        }
        
        $planId = $subscription['plan_type'] . '_' . $subscription['billing_period'];
        $plan = $this->getPlan($planId);
        
        if (!$plan) {
            // Fallback sur le type mensuel
            $plan = $this->getPlan($subscription['plan_type'] . '_monthly');
        }
        
        return isset($plan['features'][$feature]) && $plan['features'][$feature];
    }
    
    /**
     * Obtenir les fonctionnalités de l'utilisateur
     */
    public function getUserFeatures(int $userId): array {
        $subscription = $this->getActiveSubscription($userId);
        
        if (!$subscription) {
            return [
                'type' => 'free',
                'likes_daily' => 5,
                'messages_daily' => 10,
                'see_who_liked' => false,
                'boost_monthly' => 0,
                'advanced_filters' => false,
                'incognito_mode' => false,
                'super_likes_daily' => 1
            ];
        }
        
        $planId = $subscription['plan_type'] . '_' . $subscription['billing_period'];
        $plan = $this->getPlan($planId);
        
        if (!$plan) {
            $plan = $this->getPlan($subscription['plan_type'] . '_monthly');
        }
        
        return array_merge(
            ['type' => $subscription['plan_type']],
            $plan['features'] ?? []
        );
    }
    
    /**
     * Acheter un boost
     */
    public function purchaseBoost(int $userId, int $durationMinutes = 30): array {
        $price = $durationMinutes === 30 ? 4.99 : 9.99;
        
        try {
            $this->db->beginTransaction();
            
            // Créer le boost
            $endsAt = date('Y-m-d H:i:s', strtotime("+{$durationMinutes} minutes"));
            
            $sql = "INSERT INTO boosts (user_id, duration_minutes, ends_at) VALUES (:user_id, :duration, :ends_at)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'duration' => $durationMinutes,
                'ends_at' => $endsAt
            ]);
            
            $boostId = (int) $this->db->lastInsertId();
            
            // Activer le boost sur l'utilisateur
            $sql = "UPDATE users SET boost_active = 1, boost_end_time = :ends_at WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['ends_at' => $endsAt, 'user_id' => $userId]);
            
            // Créer le paiement
            $sql = "INSERT INTO payments (user_id, amount, payment_method, payment_provider, status, description)
                    VALUES (:user_id, :amount, 'card', 'stripe', 'completed', 'Boost de profil')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'amount' => $price]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'boost_id' => $boostId,
                'ends_at' => $endsAt
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur lors de l\'achat'];
        }
    }
    
    /**
     * Utiliser un boost gratuit (abonnement)
     */
    public function useBoost(int $userId): array {
        $features = $this->getUserFeatures($userId);
        
        if ($features['boost_monthly'] <= 0) {
            return ['success' => false, 'error' => 'Aucun boost disponible'];
        }
        
        // Vérifier les boosts utilisés ce mois
        $sql = "SELECT COUNT(*) FROM boosts 
                WHERE user_id = :user_id 
                AND started_at >= DATE_FORMAT(NOW(), '%Y-%m-01')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $usedThisMonth = (int) $stmt->fetchColumn();
        
        if ($usedThisMonth >= $features['boost_monthly']) {
            return ['success' => false, 'error' => 'Vous avez utilisé tous vos boosts ce mois'];
        }
        
        // Créer le boost
        $endsAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        
        $sql = "INSERT INTO boosts (user_id, duration_minutes, ends_at) VALUES (:user_id, 30, :ends_at)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'ends_at' => $endsAt]);
        
        $sql = "UPDATE users SET boost_active = 1, boost_end_time = :ends_at WHERE id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ends_at' => $endsAt, 'user_id' => $userId]);
        
        return [
            'success' => true,
            'ends_at' => $endsAt,
            'remaining' => $features['boost_monthly'] - $usedThisMonth - 1
        ];
    }
    
    /**
     * Désactiver les boosts expirés
     */
    public function deactivateExpiredBoosts(): int {
        $sql = "UPDATE boosts SET is_active = 0 WHERE is_active = 1 AND ends_at < NOW()";
        $stmt = $this->db->query($sql);
        $count = $stmt->rowCount();
        
        $sql = "UPDATE users SET boost_active = 0 WHERE boost_active = 1 AND boost_end_time < NOW()";
        $this->db->query($sql);
        
        return $count;
    }
}
