# MédiRDV

Système complet de réservation de créneaux médicaux : **espace patient**, **espace praticien**, **gestion des disponibilités** et **rappels automatiques par email**.

Stack : PHP 8.1+ (PDO) · MySQL 5.7+ / MariaDB 10.3+ · JavaScript natif · Bootstrap 5.3 (inclus en local, aucune dépendance CDN).

## Fonctionnalités

**Patient**
- Annuaire avec recherche en direct (spécialité, ville, nom) et prochain créneau libre affiché
- Fiche praticien, bande de jours, créneaux réellement libres, réservation en un clic
- Tableau de bord, liste des rendez-vous (à venir / passés / annulés), annulation, profil
- Emails de confirmation, d'annulation et de rappel

**Praticien**
- Tableau de bord : agenda du jour (frise), demandes à confirmer, compteurs
- Agenda complet filtrable ; actions : confirmer, annuler, marquer terminé / absent
- Disponibilités : plages horaires par jour (plusieurs plages possibles), congés et fermetures
- Profil : spécialité, adresse, durée de consultation, tarif, confirmation automatique ou manuelle

**Sécurité**
- Mots de passe hachés (`password_hash`), sessions durcies, régénération d'ID à la connexion
- Jeton CSRF sur tous les formulaires et appels API, requêtes préparées PDO, sortie échappée
- Blocage après 5 échecs de connexion (15 min), réinitialisation de mot de passe par lien à usage unique (1 h)
- Anti double-réservation : verrou transactionnel sur le praticien + revérification du créneau
- Dossiers `config/`, `includes/`, `database/`, `storage/`, `cron/` fermés par `.htaccess`

## Installation avec WAMP (Windows)

1. Copiez le dossier `medirdv` dans `C:\wamp64\www\`.
2. Lancez WAMP et attendez l'icône **verte** dans la barre des tâches (Apache + MySQL/MariaDB démarrés).
3. Vérifiez que la version de PHP active est **8.1 ou plus** (clic gauche sur l'icône WAMP > PHP > Version).
4. Vérifiez `config/config.php` (par défaut : utilisateur `root`, mot de passe vide, base `medirdv`, ce qui correspond à WAMP).
5. Dans PowerShell, à la racine du projet (adaptez le numéro de version de PHP à celui installé, visible dans `C:\wamp64\bin\php\`) :

   ```powershell
   cd C:\wamp64\www\medirdv
   C:\wamp64\bin\php\php8.3.14\php.exe database\install.php --seed
   ```

   (sans `--seed` : tables seulement, sans comptes de démonstration)
6. Ouvrez http://localhost/medirdv/public/

> Alternative sans Apache : `php -S localhost:8000 -t public` (avec le même `php.exe`), puis http://localhost:8000
> Autre alternative pour la base : importer `database/schema.sql` dans phpMyAdmin, puis lancer `php database\seed.php`.

### Comptes de démonstration

| Rôle | Email | Mot de passe |
|---|---|---|
| Patient | patient@medirdv.test | `Patient123` |
| Praticien | dr.adjovi@medirdv.test | `Praticien123` |

Autres praticiens : `dr.zinsou@`, `dr.hounkpatin@` (confirmation manuelle), `dr.dossou@`, `dr.agbodjan@`, `dr.bio@` `medirdv.test`.

## Emails et rappels automatiques

**1. Choisir le mode d'envoi** dans `config/config.php` (ou variables d'environnement) :

| `mail.driver` | Usage |
|---|---|
| `log` (défaut) | Rien n'est envoyé : chaque email est enregistré en HTML dans `storage/mails/` (ouvrez-le dans le navigateur). Idéal en local. |
| `mail` | Fonction `mail()` de PHP (hébergement mutualisé). |
| `smtp` | Serveur SMTP : renseignez `smtp.host`, `port`, `secure` (`tls`/`ssl`), `user`, `pass`. |

Exemple Gmail : hôte `smtp.gmail.com`, port `587`, `tls`, avec un **mot de passe d'application**.

**2. Planifier les rappels** (par défaut 24 h avant le rendez-vous, réglable via `booking.reminder_hours`).

Sous Windows, planificateur de tâches, toutes les 10 minutes (adaptez le chemin de `php.exe`) :

```powershell
schtasks /Create /SC MINUTE /MO 10 /TN "MediRDV Rappels" /TR "C:\wamp64\bin\php\php8.3.14\php.exe C:\wamp64\www\medirdv\cron\send_reminders.php"
```

Sous Linux (crontab) : `*/10 * * * * php /chemin/medirdv/cron/send_reminders.php`

Sans tâche planifiée, un filet de sécurité envoie les rappels dus pendant la navigation (au plus toutes les 10 minutes, option `reminders.on_request`). Chaque rappel n'est envoyé qu'une seule fois.

## Mise en ligne

- Placez `APP_ENV=prod` (ou `app.env` = `prod` dans la config) : les erreurs ne sont plus affichées et les comptes de démo disparaissent de la page de connexion.
- Renseignez `app.url` avec l'adresse publique (utilisée dans les liens des emails).
- Pointez la racine web sur le dossier `public/`.
- Utilisez HTTPS (le cookie de session passe automatiquement en `Secure`).
- Supprimez les comptes de démonstration.

## Structure

```
config/        configuration
database/      schema.sql, install.php, seed.php
includes/      logique métier (auth, créneaux, rendez-vous, emails, rappels, vues)
public/        pages, API JSON (public/api), assets (CSS, JS, Bootstrap local)
public/patient, public/praticien   espaces connectés
cron/          send_reminders.php
storage/       emails enregistrés (mode log)
```

## Règles métier réglables (`config/config.php` → `booking`)

`horizon_days` (45) · `min_notice_hours` (2) · `cancel_limit_hours` (2, pour les patients) · `reminder_hours` (24).
Un patient peut avoir au maximum 3 rendez-vous à venir avec un même praticien. Fuseau horaire : `Africa/Porto-Novo`.
