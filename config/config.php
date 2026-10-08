<?php
/**
 * Configuration de MédiRDV.
 * Chaque valeur peut être surchargée par une variable d'environnement,
 * ou directement modifiée ici.
 */
return [
    'app' => [
        'name'     => 'MédiRDV',
        // URL publique du site (sans slash final). Laisser vide pour la détecter automatiquement.
        'url'      => getenv('APP_URL') ?: '',
        'timezone' => 'Africa/Porto-Novo',
        // 'dev' affiche les erreurs et les comptes de démonstration ; passer à 'prod' en ligne.
        'env'      => getenv('APP_ENV') ?: 'dev',
    ],

    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => (int)(getenv('DB_PORT') ?: 3306),
        'name'    => getenv('DB_NAME') ?: 'medirdv',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],

    'mail' => [
        // 'log'  : n'envoie rien, enregistre chaque email dans storage/mails (idéal en local)
        // 'mail' : fonction mail() de PHP (hébergement mutualisé)
        // 'smtp' : serveur SMTP (Gmail, Brevo, Mailgun, OVH...)
        'driver'     => getenv('MAIL_DRIVER') ?: 'log',
        'from_email' => getenv('MAIL_FROM') ?: 'no-reply@medirdv.local',
        'from_name'  => 'MédiRDV',
        'smtp' => [
            'host'   => getenv('SMTP_HOST') ?: 'smtp.example.com',
            'port'   => (int)(getenv('SMTP_PORT') ?: 587),
            'secure' => getenv('SMTP_SECURE') ?: 'tls',   // 'tls' (STARTTLS), 'ssl' ou ''
            'user'   => getenv('SMTP_USER') ?: '',
            'pass'   => getenv('SMTP_PASS') ?: '',
        ],
    ],

    'booking' => [
        'horizon_days'      => 45,  // jusqu'à combien de jours à l'avance on peut réserver
        'min_notice_hours'  => 2,   // délai minimum avant un rendez-vous
        'cancel_limit_hours'=> 2,   // un patient ne peut plus annuler en dessous de ce délai
        'reminder_hours'    => 24,  // rappel envoyé X heures avant le rendez-vous
    ],

    'reminders' => [
        // Si true, les rappels dus sont aussi envoyés pendant la navigation (toutes les 10 min max),
        // pratique quand aucune tâche planifiée n'est configurée.
        'on_request' => true,
    ],
];
