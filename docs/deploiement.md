# Mise en production

Guide d'installation du serveur et des applications mobiles. Chaque point coché ci-dessous est vérifié par le
code ou par un test ; les points non cochés dépendent de l'installation et sont à faire par l'exploitant.

## 1. Serveur

- PHP 8.3 ou plus (extensions : pdo_mysql, mbstring, intl, gd, zip, bcmath, openssl, redis), Composer 2.
- MySQL 8 (base `microfinance`, encodage `utf8mb4`), Redis 7 (files d'attente, cache).
- Serveur web (Nginx ou Apache) servant **uniquement** `public/` ; `storage/` et `bootstrap/cache/`
  inscriptibles par PHP et jamais exposés.
- Certificat HTTPS valide (l'application force HTTPS et envoie HSTS hors du poste de développement).

```bash
git clone … && cd microfinace
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate   # une clé unique par installation, jamais commitée
php artisan migrate --force
php artisan db:seed --class=RolesEtPermissionsSeeder --force   # à relancer après chaque mise à jour qui ajoute des droits
php artisan organisation:creer COOPEC-GOMA "Coopérative de Goma"   # crée l'organisation et son administrateur
php artisan config:cache && php artisan route:cache && php artisan view:cache
npm ci && npm run build
```

## 2. Fichier `.env` de production

| Clé | Valeur |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://…` (adresse publique) |
| `DB_*` | utilisateur MySQL **sans** droits `DROP`, `ALTER`, `GRANT` hors des mises à jour |
| `SESSION_SECURE_COOKIE` | `true` (déjà la valeur par défaut) |
| `QUEUE_CONNECTION` / `CACHE_STORE` | `redis` |
| `MAIL_*` | vrai serveur d'envoi : les codes OTP partent par e-mail |
| `OTP_CANAL` | `mail` (ou `sms` quand un fournisseur SMS sera branché) |
| `PUSH_PILOTE` | `firebase` pour envoyer les notifications ; `journal` sinon |
| `FIREBASE_PROJET` / `FIREBASE_COMPTE_SERVICE` | identifiant du projet, chemin du JSON du compte de service (hors dépôt, hors `public/`, lisible par PHP seulement) |
| `SANCTUM_EXPIRATION` | durée des jetons mobiles en minutes (10080 = 7 jours) |

Le fuseau horaire se règle **par organisation** dans l'écran Organisation (Kinshasa par défaut), pas dans `.env`.

## 3. Tâches permanentes

- **File d'attente** (reçus PDF, exports, notifications, codes OTP) : un worker toujours actif, par exemple
  avec Supervisor :

  ```ini
  [program:microfinance-file]
  command=php /var/www/microfinace/artisan queue:work redis --tries=3 --max-time=3600
  autostart=true
  autorestart=true
  user=www-data
  numprocs=2
  ```

  Après chaque mise à jour : `php artisan queue:restart`.
- **Planificateur** : une ligne de cron, `* * * * * cd /var/www/microfinace && php artisan schedule:run >> /dev/null 2>&1`.
  Tâches (heures UTC) : pénalités de retard 1 h, vérification des soldes 2 h, intérêts d'épargne le 1er à 3 h,
  rappels push 8 h. `php artisan schedule:list` les affiche.

## 4. Sauvegardes (à mettre en place par l'exploitant)

- [ ] Sauvegarde MySQL **quotidienne** (`mysqldump --single-transaction --routines --triggers`), **chiffrée**
      (gpg ou age) et **copiée hors du serveur**.
- [ ] Sauvegarde de `storage/app` (reçus PDF, logos, pièces jointes des garanties, exports).
- [ ] Restauration **testée** sur un serveur à part au moins une fois par mois, suivie de
      `php artisan finance:verifier-soldes` (aucun écart attendu).
- Les sauvegardes et le fichier `.env` ne sont **jamais** commités (CLAUDE.md §9).
- Ne jamais lancer `migrate:fresh` ni `db:wipe` sur la base de production.

## 5. Applications Android

```bash
cd mobile
# Adresse du serveur et identifiants Firebase dans ~/.gradle/gradle.properties (jamais dans le dépôt) :
#   microfinance.apiUrl=https://…/api/v1
#   microfinance.firebase.projet / .application / .cle / .expediteur
./gradlew :app-collecteur:assembleRelease :app-membre:assembleRelease
```

- Signer les APK avec une clé de publication gardée hors du dépôt (`*.jks` est ignoré par Git).
- Monter `microfinance.versionCode` à chaque livraison.
- En production, l'application refuse le HTTP en clair : le serveur doit être en HTTPS.
- Collecteurs : à la première connexion, le téléphone apparaît « en attente » dans l'écran Collecte ; le gérant
  l'autorise, fixe les paramètres de collecte et affecte la tournée.
- Imprimante thermique Bluetooth 58 mm : à appairer dans les réglages Bluetooth du téléphone du collecteur.

## 6. Grille de sécurité (skill `finance-cooperative`, references/securite.md)

Vérifié par le code ou les tests :

- [x] HTTPS forcé, HSTS, CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` (tests `EnTetesSecurite`)
- [x] Cookies `secure`, `http_only`, `same_site=strict`, session chiffrée ; OTP pour tout le personnel ; `throttle`
- [x] Jetons Sanctum à durée limitée, révocables ; téléphones des collecteurs autorisés par le gérant
- [x] Form Requests, SQL paramétré, vues en `{{ }}`, CSRF, erreurs API sans trace technique
- [x] Policies, scope par organisation et tests d'accès croisé, `$fillable` explicite, 4 yeux testés
- [x] Uploads : MIME + taille, disque local hors `public/`, noms aléatoires ; pièces d'identité chiffrées
- [x] Opérations, écritures, audit en insertion seule (triggers MySQL) ; `composer audit`, PHPStan niveau 6

À faire sur le serveur :

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `display_errors=Off`
- [ ] Utilisateur MySQL de l'application sans droits d'administration
- [ ] Sauvegardes chiffrées hors serveur et restauration testée (§4)
- [ ] Intégration continue branchée sur un dépôt distant (`.github/workflows/ci.yml` : tests, PHPStan, audit)
