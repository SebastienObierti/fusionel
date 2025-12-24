<?php
/**
 * FUSIONEL - API Subscription Controller
 * Endpoints pour gérer les abonnements PayPal
 * 
 * À inclure dans votre index.php principal
 */

// ==================== SUBSCRIPTION ENDPOINTS ====================

/**
 * GET /api/subscription/plans
 * Liste des plans disponibles
 */
function getSubscriptionPlans($pdo) {
    $stmt = $pdo->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order");
    respond(['plans' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

/**
 * GET /api/subscription/status
 * Statut de l'abonnement de l'utilisateur connecté
 */
function getSubscriptionStatus($pdo, $userId) {
    // Récupérer l'abonnement actif
    $stmt = $pdo->prepare("
        SELECT s.*, sp.name as plan_name, sp.features,
               DATEDIFF(s.ends_at, NOW()) as days_remaining
        FROM subscriptions s
        LEFT JOIN subscription_plans sp ON s.plan_type = sp.slug
        WHERE s.user_id = ? AND s.status = 'active'
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les infos utilisateur
    $stmt = $pdo->prepare("
        SELECT subscription_type, is_premium, is_vip, subscription_end_date,
               likes_today, super_likes_this_week, boosts_this_month
        FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculer les limites selon le plan
    $limits = getLimitsForPlan($user['subscription_type'] ?? 'free');
    
    respond([
        'subscription' => $subscription,
        'subscription_type' => $user['subscription_type'] ?? 'free',
        'is_premium' => (bool)$user['is_premium'],
        'is_vip' => (bool)$user['is_vip'],
        'ends_at' => $user['subscription_end_date'],
        'days_remaining' => $subscription['days_remaining'] ?? null,
        'usage' => [
            'likes_today' => (int)$user['likes_today'],
            'super_likes_this_week' => (int)$user['super_likes_this_week'],
            'boosts_this_month' => (int)$user['boosts_this_month']
        ],
        'limits' => $limits
    ]);
}

/**
 * POST /api/subscription/activate
 * Activer un abonnement après paiement PayPal
 */
function activateSubscription($pdo, $userId, $input) {
    $plan = $input['plan'] ?? '';
    $period = $input['period'] ?? 'monthly';
    $paypalOrderId = $input['paypal_order_id'] ?? '';
    $paypalPayerId = $input['paypal_payer_id'] ?? '';
    $paypalPayerEmail = $input['paypal_payer_email'] ?? '';
    $amount = floatval($input['amount'] ?? 0);
    
    // Validation
    if (!in_array($plan, ['premium', 'vip'])) {
        error('Plan invalide');
    }
    if (!in_array($period, ['monthly', 'quarterly', 'yearly'])) {
        error('Période invalide');
    }
    if (!$paypalOrderId) {
        error('PayPal order_id requis');
    }
    
    // Vérifier si la transaction n'a pas déjà été utilisée
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE paypal_order_id = ?");
    $stmt->execute([$paypalOrderId]);
    if ($stmt->fetch()) {
        error('Cette transaction a déjà été traitée');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Calculer la date de fin
        $durations = [
            'monthly' => '+1 month',
            'quarterly' => '+3 months',
            'yearly' => '+1 year'
        ];
        $endsAt = date('Y-m-d H:i:s', strtotime($durations[$period]));
        
        // Annuler les anciens abonnements actifs
        $pdo->prepare("
            UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW()
            WHERE user_id = ? AND status = 'active'
        ")->execute([$userId]);
        
        // Créer le nouvel abonnement
        $stmt = $pdo->prepare("
            INSERT INTO subscriptions 
            (user_id, plan_type, billing_period, price, currency, status, starts_at, ends_at, 
             paypal_order_id, paypal_payer_id, auto_renew)
            VALUES (?, ?, ?, ?, 'EUR', 'active', NOW(), ?, ?, ?, 1)
        ");
        $stmt->execute([$userId, $plan, $period, $amount, $endsAt, $paypalOrderId, $paypalPayerId]);
        $subscriptionId = $pdo->lastInsertId();
        
        // Enregistrer le paiement
        $stmt = $pdo->prepare("
            INSERT INTO payments 
            (user_id, subscription_id, amount, currency, payment_type, status, 
             payment_method, transaction_id, paypal_order_id, paypal_payer_email, 
             description, paid_at)
            VALUES (?, ?, ?, 'EUR', 'subscription', 'completed', 'paypal', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId, $subscriptionId, $amount, $paypalOrderId, $paypalOrderId,
            $paypalPayerEmail, "Abonnement $plan - $period"
        ]);
        $paymentId = $pdo->lastInsertId();
        
        // Mettre à jour l'utilisateur
        $stmt = $pdo->prepare("
            UPDATE users SET 
                subscription_type = ?,
                subscription_id = ?,
                subscription_end_date = ?,
                is_premium = ?,
                is_vip = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $plan,
            $subscriptionId,
            $endsAt,
            ($plan === 'premium' || $plan === 'vip') ? 1 : 0,
            ($plan === 'vip') ? 1 : 0,
            $userId
        ]);
        
        // Historique
        $pdo->prepare("
            INSERT INTO subscription_history 
            (user_id, subscription_id, action, new_plan, new_status, notes)
            VALUES (?, ?, 'activated', ?, 'active', ?)
        ")->execute([$userId, $subscriptionId, $plan, "Paiement PayPal: $paypalOrderId"]);
        
        $pdo->commit();
        
        respond([
            'success' => true,
            'subscription' => [
                'id' => $subscriptionId,
                'plan' => $plan,
                'period' => $period,
                'starts_at' => date('Y-m-d H:i:s'),
                'ends_at' => $endsAt,
                'amount' => $amount
            ],
            'payment_id' => $paymentId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Erreur lors de l\'activation: ' . $e->getMessage());
    }
}

/**
 * POST /api/subscription/cancel
 * Annuler l'abonnement (reste actif jusqu'à la fin de la période)
 */
function cancelSubscription($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT id, plan_type, ends_at FROM subscriptions 
        WHERE user_id = ? AND status = 'active'
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscription) {
        error('Aucun abonnement actif');
    }
    
    // Marquer comme annulé mais garder actif jusqu'à la fin
    $pdo->prepare("
        UPDATE subscriptions 
        SET auto_renew = 0, cancelled_at = NOW()
        WHERE id = ?
    ")->execute([$subscription['id']]);
    
    // Historique
    $pdo->prepare("
        INSERT INTO subscription_history 
        (user_id, subscription_id, action, old_status, new_status, notes)
        VALUES (?, ?, 'cancelled', 'active', 'active', 'Annulation par l\'utilisateur - Reste actif jusqu\'au terme')
    ")->execute([$userId, $subscription['id']]);
    
    respond([
        'success' => true,
        'message' => 'Abonnement annulé. Vous conservez l\'accès jusqu\'au ' . date('d/m/Y', strtotime($subscription['ends_at'])),
        'ends_at' => $subscription['ends_at']
    ]);
}

/**
 * GET /api/subscription/history
 * Historique des paiements
 */
function getPaymentHistory($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT p.*, s.plan_type, s.billing_period
        FROM payments p
        LEFT JOIN subscriptions s ON p.subscription_id = s.id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    
    respond(['payments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

/**
 * GET /api/subscription/invoice/:payment_id
 * Générer une facture
 */
function getInvoice($pdo, $userId, $paymentId) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.firstname, u.lastname, u.email, s.plan_type, s.billing_period
        FROM payments p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN subscriptions s ON p.subscription_id = s.id
        WHERE p.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$paymentId, $userId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        error('Paiement non trouvé', 404);
    }
    
    respond([
        'invoice' => [
            'number' => 'FUS-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT),
            'date' => $payment['paid_at'],
            'amount' => $payment['amount'],
            'currency' => $payment['currency'],
            'description' => $payment['description'],
            'status' => $payment['status'],
            'customer' => [
                'name' => trim($payment['firstname'] . ' ' . $payment['lastname']),
                'email' => $payment['email']
            ],
            'plan' => $payment['plan_type'],
            'period' => $payment['billing_period'],
            'transaction_id' => $payment['transaction_id']
        ]
    ]);
}

/**
 * POST /api/subscription/webhook
 * Webhook PayPal pour les notifications IPN
 */
function handlePayPalWebhook($pdo, $input) {
    // Log du webhook
    file_put_contents('/var/log/fusionel/paypal_webhook.log', 
        date('Y-m-d H:i:s') . ' - ' . json_encode($input) . PHP_EOL, 
        FILE_APPEND
    );
    
    $eventType = $input['event_type'] ?? '';
    $resource = $input['resource'] ?? [];
    
    switch ($eventType) {
        case 'PAYMENT.CAPTURE.COMPLETED':
            // Paiement réussi - déjà géré par le frontend
            break;
            
        case 'PAYMENT.CAPTURE.DENIED':
        case 'PAYMENT.CAPTURE.REFUNDED':
            // Paiement refusé ou remboursé
            $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? '';
            if ($orderId) {
                $pdo->prepare("
                    UPDATE payments SET status = 'refunded' WHERE paypal_order_id = ?
                ")->execute([$orderId]);
            }
            break;
            
        case 'BILLING.SUBSCRIPTION.CANCELLED':
            // Abonnement récurrent annulé
            $subscriptionId = $resource['id'] ?? '';
            if ($subscriptionId) {
                $pdo->prepare("
                    UPDATE subscriptions SET status = 'cancelled', cancelled_at = NOW()
                    WHERE paypal_subscription_id = ?
                ")->execute([$subscriptionId]);
            }
            break;
    }
    
    respond(['success' => true]);
}

/**
 * Obtenir les limites selon le plan
 */
function getLimitsForPlan($planType) {
    $limits = [
        'free' => [
            'likes_per_day' => 5,
            'super_likes_per_week' => 0,
            'boosts_per_month' => 0,
            'can_see_likes' => false,
            'unlimited_likes' => false
        ],
        'premium' => [
            'likes_per_day' => -1, // illimité
            'super_likes_per_week' => 5,
            'boosts_per_month' => 1,
            'can_see_likes' => true,
            'unlimited_likes' => true
        ],
        'vip' => [
            'likes_per_day' => -1,
            'super_likes_per_week' => -1,
            'boosts_per_month' => 5,
            'can_see_likes' => true,
            'unlimited_likes' => true,
            'priority_profile' => true
        ]
    ];
    
    return $limits[$planType] ?? $limits['free'];
}

/**
 * Vérifier si l'utilisateur peut effectuer une action
 */
function checkUserLimit($pdo, $userId, $action) {
    $stmt = $pdo->prepare("
        SELECT subscription_type, likes_today, super_likes_this_week, boosts_this_month,
               likes_reset_date, super_likes_reset_date, boosts_reset_date
        FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $limits = getLimitsForPlan($user['subscription_type'] ?? 'free');
    
    switch ($action) {
        case 'like':
            if ($limits['unlimited_likes']) return true;
            // Réinitialiser si nouveau jour
            if ($user['likes_reset_date'] !== date('Y-m-d')) {
                $pdo->prepare("UPDATE users SET likes_today = 0, likes_reset_date = CURDATE() WHERE id = ?")->execute([$userId]);
                return true;
            }
            return $user['likes_today'] < $limits['likes_per_day'];
            
        case 'super_like':
            if ($limits['super_likes_per_week'] === -1) return true;
            if ($limits['super_likes_per_week'] === 0) return false;
            return $user['super_likes_this_week'] < $limits['super_likes_per_week'];
            
        case 'boost':
            if ($limits['boosts_per_month'] === 0) return false;
            return $user['boosts_this_month'] < $limits['boosts_per_month'];
            
        case 'see_likes':
            return $limits['can_see_likes'];
    }
    
    return false;
}

/**
 * Incrémenter un compteur d'utilisation
 */
function incrementUsage($pdo, $userId, $action) {
    $fields = [
        'like' => 'likes_today',
        'super_like' => 'super_likes_this_week',
        'boost' => 'boosts_this_month'
    ];
    
    if (!isset($fields[$action])) return;
    
    $field = $fields[$action];
    $pdo->prepare("UPDATE users SET $field = $field + 1 WHERE id = ?")->execute([$userId]);
}
