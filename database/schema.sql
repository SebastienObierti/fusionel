-- =====================================================
-- BASE DE DONNÉES: Âme Sœur - Site de Rencontre
-- =====================================================

CREATE DATABASE IF NOT EXISTS db_fusionel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_fusionel;

-- =====================================================
-- TABLE: users (Utilisateurs)
-- =====================================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100),
    birthdate DATE NOT NULL,
    gender ENUM('homme', 'femme', 'autre') NOT NULL,
    seeking ENUM('homme', 'femme', 'tous') NOT NULL,
    city VARCHAR(100) NOT NULL,
    postal_code VARCHAR(10),
    country VARCHAR(100) DEFAULT 'France',
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    bio TEXT,
    job VARCHAR(150),
    company VARCHAR(150),
    education VARCHAR(200),
    height INT UNSIGNED COMMENT 'Taille en cm',
    body_type ENUM('mince', 'athletique', 'normal', 'quelques_kilos', 'rond', 'autre'),
    smoking ENUM('jamais', 'occasionnel', 'regulier'),
    drinking ENUM('jamais', 'occasionnel', 'regulier'),
    children ENUM('non', 'oui_vivant_avec', 'oui_ne_vivant_pas_avec', 'ne_souhaite_pas'),
    wants_children ENUM('oui', 'non', 'peut_etre'),
    religion VARCHAR(50),
    profile_photo_id INT UNSIGNED,
    is_verified BOOLEAN DEFAULT FALSE,
    is_premium BOOLEAN DEFAULT FALSE,
    is_vip BOOLEAN DEFAULT FALSE,
    subscription_type ENUM('free', 'premium', 'vip') DEFAULT 'free',
    subscription_end_date DATETIME,
    is_online BOOLEAN DEFAULT FALSE,
    last_seen DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    is_banned BOOLEAN DEFAULT FALSE,
    ban_reason TEXT,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    password_reset_token VARCHAR(255),
    password_reset_expires DATETIME,
    profile_completion INT UNSIGNED DEFAULT 0 COMMENT 'Pourcentage de complétion du profil',
    daily_likes_count INT UNSIGNED DEFAULT 0,
    daily_likes_reset_date DATE,
    boost_active BOOLEAN DEFAULT FALSE,
    boost_end_time DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gender (gender),
    INDEX idx_seeking (seeking),
    INDEX idx_city (city),
    INDEX idx_location (latitude, longitude),
    INDEX idx_is_active (is_active),
    INDEX idx_is_online (is_online),
    INDEX idx_subscription (subscription_type)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: user_photos (Photos des utilisateurs)
-- =====================================================
CREATE TABLE user_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    filepath VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500),
    is_primary BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE,
    is_private BOOLEAN DEFAULT FALSE,
    order_position INT UNSIGNED DEFAULT 0,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_photos (user_id),
    INDEX idx_primary (user_id, is_primary)
) ENGINE=InnoDB;

-- Ajouter la clé étrangère pour profile_photo_id
ALTER TABLE users ADD FOREIGN KEY (profile_photo_id) REFERENCES user_photos(id) ON DELETE SET NULL;

-- =====================================================
-- TABLE: interests (Centres d'intérêt)
-- =====================================================
CREATE TABLE interests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    category VARCHAR(50),
    icon VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: user_interests (Relation utilisateurs-intérêts)
-- =====================================================
CREATE TABLE user_interests (
    user_id INT UNSIGNED NOT NULL,
    interest_id INT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, interest_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (interest_id) REFERENCES interests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: personality_questions (Questions de personnalité)
-- =====================================================
CREATE TABLE personality_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    question_type ENUM('scale', 'choice', 'multiple') NOT NULL,
    category VARCHAR(50),
    options JSON COMMENT 'Options de réponse en JSON',
    weight DECIMAL(3,2) DEFAULT 1.00,
    is_active BOOLEAN DEFAULT TRUE,
    order_position INT UNSIGNED,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: user_personality_answers (Réponses au test)
-- =====================================================
CREATE TABLE user_personality_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    answer_value VARCHAR(255) NOT NULL,
    answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES personality_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_question (user_id, question_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: user_personality_scores (Scores de personnalité)
-- =====================================================
CREATE TABLE user_personality_scores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    openness DECIMAL(5,2) DEFAULT 0,
    conscientiousness DECIMAL(5,2) DEFAULT 0,
    extraversion DECIMAL(5,2) DEFAULT 0,
    agreeableness DECIMAL(5,2) DEFAULT 0,
    neuroticism DECIMAL(5,2) DEFAULT 0,
    romanticism DECIMAL(5,2) DEFAULT 0,
    adventurousness DECIMAL(5,2) DEFAULT 0,
    calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: likes (J'aime)
-- =====================================================
CREATE TABLE likes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    liker_id INT UNSIGNED NOT NULL,
    liked_id INT UNSIGNED NOT NULL,
    is_super_like BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (liker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (liked_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (liker_id, liked_id),
    INDEX idx_liked (liked_id),
    INDEX idx_liker (liker_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: dislikes (Je n'aime pas / Pass)
-- =====================================================
CREATE TABLE dislikes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disliker_id INT UNSIGNED NOT NULL,
    disliked_id INT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (disliker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (disliked_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dislike (disliker_id, disliked_id),
    INDEX idx_disliked (disliked_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: matches (Matchs mutuels)
-- =====================================================
CREATE TABLE matches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user1_id INT UNSIGNED NOT NULL,
    user2_id INT UNSIGNED NOT NULL,
    compatibility_score DECIMAL(5,2) COMMENT 'Score de compatibilité en pourcentage',
    matched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    unmatched_at DATETIME,
    unmatched_by INT UNSIGNED,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (unmatched_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_match (user1_id, user2_id),
    INDEX idx_user1 (user1_id),
    INDEX idx_user2 (user2_id),
    INDEX idx_matched_at (matched_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: conversations (Conversations)
-- =====================================================
CREATE TABLE conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id INT UNSIGNED NOT NULL UNIQUE,
    last_message_at DATETIME,
    last_message_preview VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    INDEX idx_last_message (last_message_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: messages (Messages)
-- =====================================================
CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    message_type ENUM('text', 'image', 'gif', 'audio', 'video') DEFAULT 'text',
    media_url VARCHAR(500),
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    is_deleted_by_sender BOOLEAN DEFAULT FALSE,
    is_deleted_by_receiver BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversation (conversation_id),
    INDEX idx_sender (sender_id),
    INDEX idx_created (created_at),
    INDEX idx_unread (conversation_id, is_read)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: blocks (Blocages)
-- =====================================================
CREATE TABLE blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blocker_id INT UNSIGNED NOT NULL,
    blocked_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_block (blocker_id, blocked_id),
    INDEX idx_blocked (blocked_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: reports (Signalements)
-- =====================================================
CREATE TABLE reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT UNSIGNED NOT NULL,
    reported_id INT UNSIGNED NOT NULL,
    reason ENUM('fake_profile', 'harassment', 'inappropriate_content', 'scam', 'underage', 'other') NOT NULL,
    description TEXT,
    evidence_urls JSON,
    status ENUM('pending', 'reviewing', 'resolved', 'dismissed') DEFAULT 'pending',
    admin_notes TEXT,
    reviewed_by INT UNSIGNED,
    reviewed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_reported (reported_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: profile_views (Vues de profil)
-- =====================================================
CREATE TABLE profile_views (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    viewer_id INT UNSIGNED NOT NULL,
    viewed_id INT UNSIGNED NOT NULL,
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (viewed_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_viewed (viewed_id),
    INDEX idx_viewer (viewer_id),
    INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: subscriptions (Abonnements)
-- =====================================================
CREATE TABLE subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan_type ENUM('premium', 'vip') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    billing_period ENUM('monthly', 'quarterly', 'yearly') NOT NULL,
    status ENUM('active', 'cancelled', 'expired', 'pending') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_id VARCHAR(255),
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    auto_renew BOOLEAN DEFAULT TRUE,
    cancelled_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_ends_at (ends_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: payments (Paiements)
-- =====================================================
CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    payment_method VARCHAR(50) NOT NULL,
    payment_provider VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    description VARCHAR(255),
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_transaction (transaction_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: boosts (Boosts de profil)
-- =====================================================
CREATE TABLE boosts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    boost_type ENUM('standard', 'super') DEFAULT 'standard',
    duration_minutes INT UNSIGNED DEFAULT 30,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ends_at DATETIME NOT NULL,
    views_gained INT UNSIGNED DEFAULT 0,
    likes_gained INT UNSIGNED DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_active (is_active, ends_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: notifications (Notifications)
-- =====================================================
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('match', 'like', 'super_like', 'message', 'profile_view', 'system', 'promo') NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    related_user_id INT UNSIGNED,
    related_entity_type VARCHAR(50),
    related_entity_id INT UNSIGNED,
    is_read BOOLEAN DEFAULT FALSE,
    is_pushed BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_unread (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: notification_settings (Paramètres de notification)
-- =====================================================
CREATE TABLE notification_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    email_matches BOOLEAN DEFAULT TRUE,
    email_messages BOOLEAN DEFAULT TRUE,
    email_likes BOOLEAN DEFAULT TRUE,
    email_profile_views BOOLEAN DEFAULT FALSE,
    email_promotions BOOLEAN DEFAULT TRUE,
    push_matches BOOLEAN DEFAULT TRUE,
    push_messages BOOLEAN DEFAULT TRUE,
    push_likes BOOLEAN DEFAULT TRUE,
    push_profile_views BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: user_preferences (Préférences de recherche)
-- =====================================================
CREATE TABLE user_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    min_age INT UNSIGNED DEFAULT 18,
    max_age INT UNSIGNED DEFAULT 99,
    max_distance INT UNSIGNED DEFAULT 50 COMMENT 'Distance en km',
    show_verified_only BOOLEAN DEFAULT FALSE,
    show_with_photo_only BOOLEAN DEFAULT TRUE,
    show_online_only BOOLEAN DEFAULT FALSE,
    height_min INT UNSIGNED,
    height_max INT UNSIGNED,
    body_types JSON,
    smoking_preference JSON,
    drinking_preference JSON,
    children_preference JSON,
    religion_preference JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: user_sessions (Sessions utilisateur)
-- =====================================================
CREATE TABLE user_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    device_type VARCHAR(50),
    device_name VARCHAR(100),
    browser VARCHAR(100),
    ip_address VARCHAR(45),
    location VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_token (session_token),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: icebreakers (Phrases d'accroche)
-- =====================================================
CREATE TABLE icebreakers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    text VARCHAR(255) NOT NULL,
    category VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    usage_count INT UNSIGNED DEFAULT 0,
    success_rate DECIMAL(5,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: user_activities (Activités/Journal)
-- =====================================================
CREATE TABLE user_activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    metadata JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (activity_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: admin_users (Administrateurs)
-- =====================================================
CREATE TABLE admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator', 'support') NOT NULL,
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: verification_requests (Demandes de vérification)
-- =====================================================
CREATE TABLE verification_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    photo_url VARCHAR(500) NOT NULL,
    selfie_url VARCHAR(500) NOT NULL,
    id_document_url VARCHAR(500),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason VARCHAR(255),
    reviewed_by INT UNSIGNED,
    reviewed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- =====================================================
-- INSERTION DES DONNÉES INITIALES
-- =====================================================

-- Centres d'intérêt
INSERT INTO interests (name, category, icon) VALUES
('Voyages', 'loisirs', '✈️'),
('Photographie', 'art', '📷'),
('Musique', 'art', '🎵'),
('Cinéma', 'art', '🎬'),
('Lecture', 'culture', '📚'),
('Sport', 'activités', '⚽'),
('Yoga', 'bien-être', '🧘'),
('Cuisine', 'loisirs', '👨‍🍳'),
('Randonnée', 'activités', '🥾'),
('Danse', 'art', '💃'),
('Jeux vidéo', 'loisirs', '🎮'),
('Animaux', 'lifestyle', '🐾'),
('Jardinage', 'loisirs', '🌱'),
('Art', 'art', '🎨'),
('Technologie', 'professionnel', '💻'),
('Mode', 'lifestyle', '👗'),
('Gastronomie', 'loisirs', '🍽️'),
('Vin', 'loisirs', '🍷'),
('Fitness', 'activités', '💪'),
('Méditation', 'bien-être', '🧠'),
('Théâtre', 'art', '🎭'),
('Nature', 'activités', '🌿'),
('Plage', 'activités', '🏖️'),
('Ski', 'activités', '⛷️'),
('Vélo', 'activités', '🚴'),
('Running', 'activités', '🏃'),
('Concerts', 'art', '🎤'),
('Festivals', 'loisirs', '🎪'),
('Bénévolat', 'lifestyle', '🤝'),
('Spiritualité', 'bien-être', '🕯️');

-- Questions de personnalité
INSERT INTO personality_questions (question_text, question_type, category, options, weight, order_position) VALUES
('Comment décririez-vous votre week-end idéal ?', 'choice', 'lifestyle', '["Sortir avec des amis", "Rester à la maison avec un bon livre", "Partir à l\'aventure", "Mix des deux"]', 1.00, 1),
('Dans une relation, qu\'est-ce qui compte le plus pour vous ?', 'choice', 'values', '["La communication", "La confiance", "La passion", "L\'humour"]', 1.50, 2),
('Comment gérez-vous les conflits ?', 'choice', 'personality', '["Discussion calme", "J\'ai besoin de temps", "Je préfère éviter", "Confrontation directe"]', 1.25, 3),
('Êtes-vous plutôt spontané(e) ou planificateur/trice ?', 'scale', 'personality', '{"min": 1, "max": 5, "labels": ["Très planificateur", "Très spontané"]}', 1.00, 4),
('Quelle importance accordez-vous à la famille ?', 'scale', 'values', '{"min": 1, "max": 5, "labels": ["Peu importante", "Très importante"]}', 1.25, 5),
('Comment décririez-vous votre niveau d\'énergie sociale ?', 'choice', 'personality', '["Introverti(e)", "Plutôt introverti(e)", "Plutôt extraverti(e)", "Extraverti(e)"]', 1.00, 6),
('Quelle est votre vision de l\'engagement ?', 'choice', 'relationship', '["Prêt(e) pour du sérieux", "Ouvert(e) aux possibilités", "Je prends mon temps", "Je cherche l\'amour"]', 1.50, 7),
('Aimez-vous essayer de nouvelles choses ?', 'scale', 'personality', '{"min": 1, "max": 5, "labels": ["Je préfère ma routine", "J\'adore la nouveauté"]}', 1.00, 8),
('Comment exprimez-vous votre affection ?', 'multiple', 'relationship', '["Mots doux", "Cadeaux", "Temps de qualité", "Gestes tendres", "Actes de service"]', 1.25, 9),
('Quelle place occupe votre carrière dans votre vie ?', 'scale', 'lifestyle', '{"min": 1, "max": 5, "labels": ["Équilibre vie pro/perso", "Très ambitieux/se"]}', 1.00, 10);

-- Phrases d'accroche
INSERT INTO icebreakers (text, category) VALUES
('Si tu pouvais voyager n\'importe où demain, où irais-tu ?', 'voyage'),
('Quel est ton petit plaisir coupable du dimanche ?', 'lifestyle'),
('Film préféré de tous les temps ?', 'culture'),
('Si tu devais choisir un super-pouvoir, lequel serait-ce ?', 'fun'),
('Quelle est la dernière chose qui t\'a fait vraiment rire ?', 'humour'),
('Tu préfères le café ou le thé ?', 'simple'),
('Si ta vie était un film, quel serait son titre ?', 'créatif'),
('Quel est ton endroit préféré dans ta ville ?', 'local'),
('Quelle chanson tu mets en boucle en ce moment ?', 'musique'),
('Tu cuisines ou tu commandes ? 😄', 'lifestyle');

-- Admin par défaut (mot de passe: admin123 - à changer!)
INSERT INTO admin_users (email, password, firstname, lastname, role) VALUES
('admin@amesoeur.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Principal', 'super_admin');
