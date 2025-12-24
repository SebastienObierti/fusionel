# Fusionel - Système d'Abonnement PayPal

## 📁 Structure des fichiers

```
fusionel/
├── config/
│   └── database.php              # Configuration BDD
├── cron/
│   └── subscription_manager.php  # CRON gestion abonnements
├── public/
│   ├── api/
│   │   ├── index.php             # API principale
│   │   └── subscription_controller.php  # Contrôleur abonnements
│   ├── app/
│   │   └── subscription.html     # Page abonnement frontend
│   └── admin/
│       └── subscriptions.html    # Dashboard admin
└── sql/
    └── subscriptions_tables.sql  # Tables SQL
```

## 🗄️ Installation des tables SQL

Exécutez le script SQL dans votre base de données :

```bash
mysql -u votre_user -p fusionel < sql/subscriptions_tables.sql
```

Ou via phpMyAdmin, importez le fichier `subscriptions_tables.sql`.

### Tables créées :
- `subscription_plans` - Plans disponibles (free, premium, vip)
- `subscriptions` - Abonnements utilisateurs
- `payments` - Historique des paiements
- `subscription_reminders` - Rappels envoyés
- `subscription_history` - Historique des actions

## ⚙️ Configuration CRON

Ajoutez cette ligne dans votre crontab (`crontab -e`) :

```bash
# Exécuter toutes les heures
0 * * * * /usr/bin/php /srv/web/fusionel/cron/subscription_manager.php >> /var/log/fusionel/cron.log 2>&1

# OU exécuter tous les jours à minuit
0 0 * * * /usr/bin/php /srv/web/fusionel/cron/subscription_manager.php >> /var/log/fusionel/cron.log 2>&1
```

Créez le dossier de logs :
```bash
sudo mkdir -p /var/log/fusionel
sudo chown www-data:www-data /var/log/fusionel
```

## 💳 Configuration PayPal

### 1. Créer une application PayPal

1. Allez sur https://developer.paypal.com/dashboard
2. Cliquez sur "Apps & Credentials"
3. Créez une nouvelle app
4. Copiez le **Client ID**

### 2. Configurer le frontend

Éditez `subscription.html` et remplacez :

```html
<script src="https://www.paypal.com/sdk/js?client-id=YOUR_PAYPAL_CLIENT_ID&currency=EUR&intent=capture"></script>
```

Par votre vrai Client ID :

```html
<script src="https://www.paypal.com/sdk/js?client-id=AaBbCcDdEeFf123456...&currency=EUR&intent=capture"></script>
```

### 3. Mode Sandbox vs Production

**Pour les tests (Sandbox) :**
- Utilisez le Client ID Sandbox
- Testez avec les comptes sandbox PayPal

**Pour la production :**
- Utilisez le Client ID Live
- Changez l'URL du SDK de `sandbox` à `live`

## 📧 Configuration des emails

Le CRON utilise la fonction `mail()` de PHP. Configurez votre serveur SMTP :

### Option 1 : Postfix (recommandé)
```bash
sudo apt install postfix
sudo nano /etc/postfix/main.cf
```

### Option 2 : SMTP externe (Gmail, SendGrid, etc.)
Installez PHPMailer et modifiez la fonction `sendReminderEmail()`.

## 🔗 Endpoints API

### Publics
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/subscription/plans` | Liste des plans |
| GET | `/api/subscription/status` | Statut utilisateur |
| POST | `/api/subscription/activate` | Activer abonnement |
| POST | `/api/subscription/cancel` | Annuler abonnement |
| GET | `/api/subscription/history` | Historique paiements |

### Admin
| Méthode | Endpoint | Description |
|---------|----------|-------------|
| GET | `/api/admin/subscription/stats` | Statistiques |
| GET | `/api/admin/subscriptions` | Liste abonnements |
| POST | `/api/admin/subscription/send-reminders` | Envoyer rappels |

## ⏰ Rappels automatiques

Le système envoie automatiquement des rappels :

| Délai | Type | Email | Notification |
|-------|------|-------|--------------|
| J-7 | `renewal_7days` | ✅ | ✅ |
| J-3 | `renewal_3days` | ✅ | ✅ |
| J-1 | `renewal_1day` | ✅ | ✅ |
| J-0 | `expired` | ✅ | ✅ |

## 💰 Tarification

| Plan | Mensuel | Trimestriel | Annuel |
|------|---------|-------------|--------|
| Premium | 9.99€ | 25.49€ (-15%) | 83.88€ (-30%) |
| VIP | 19.99€ | 50.99€ (-15%) | 167.88€ (-30%) |

## 🔧 Fonctionnalités par plan

### Gratuit
- 5 likes par jour
- Messagerie basique

### Premium
- Likes illimités
- 5 Super Likes / semaine
- 1 Boost / mois
- Voir qui vous a liké
- Annuler le dernier swipe

### VIP
- Tout Premium inclus
- Super Likes illimités
- 5 Boosts / mois
- Profil prioritaire
- Badge VIP vérifié
- Support prioritaire

## 🛡️ Sécurité

- Vérification des transactions PayPal
- Protection contre les doubles paiements
- Historique complet des actions
- Logs des webhooks

## 📊 Monitoring

Consultez les logs :
```bash
tail -f /var/log/fusionel/subscription_cron.log
tail -f /var/log/fusionel/paypal_webhook.log
```

## 🚀 Mise en production

1. [ ] Configurer le Client ID PayPal Live
2. [ ] Activer HTTPS (obligatoire pour PayPal)
3. [ ] Configurer le CRON
4. [ ] Configurer les emails
5. [ ] Tester un paiement complet
6. [ ] Vérifier les rappels automatiques
