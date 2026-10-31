# Grille de sécurité — à vérifier avant chaque fin de phase

Cocher chaque point ; un point non coché bloque la mise en production.

## Configuration
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `display_errors=Off`
- [ ] `.env` absent du dépôt Git, `APP_KEY` unique par installation
- [ ] HTTPS forcé (`URL::forceScheme('https')`), en-tête HSTS
- [ ] En-têtes : `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
      `Referrer-Policy: same-origin`
- [ ] Utilisateur MySQL de l'application sans droit `DROP`, `ALTER`, `GRANT` en production
- [ ] Dossiers `storage/` et `bootstrap/cache/` non accessibles depuis le web

## Authentification et sessions
- [ ] `Hash::make` (bcrypt ou argon2id), longueur minimale 10
- [ ] OTP obligatoire pour gérant, caissier, comptable, administrateur
- [ ] `throttle` : 5 tentatives / minute sur login et OTP, limite sur toute l'API
- [ ] Session régénérée à la connexion, invalidée à la déconnexion
- [ ] Cookies `secure`, `http_only`, `same_site=strict`
- [ ] Jetons Sanctum avec expiration, révocables depuis la liste des appareils

## Entrées et sorties
- [ ] Toute entrée validée dans un Form Request (types, bornes, regex des montants)
- [ ] Aucune variable concaténée dans du SQL brut (`DB::raw`, `whereRaw`, `orderByRaw`, `DB::statement`)
- [ ] Tri et filtres dynamiques par liste blanche de colonnes
- [ ] Vues en `{{ }}` ; `{!! !!}` seulement sur du HTML produit par le code lui-même
- [ ] Jeton CSRF sur tous les formulaires web
- [ ] Réponses d'erreur API sans trace technique

## Accès
- [ ] Middleware `auth` sur toutes les routes non publiques
- [ ] Une Policy par modèle métier, appelée dans chaque contrôleur (`authorize` / `can`)
- [ ] Scope global par organisation sur tous les modèles métier + test d'accès croisé
- [ ] `$fillable` explicite, jamais `$guarded = []`
- [ ] Séparation des tâches : auteur ≠ validateur (testé)

## Fichiers
- [ ] Validation `mimes` + `max` sur chaque upload
- [ ] Stockage hors `public/`, téléchargement via contrôleur avec Policy
- [ ] Noms de fichiers générés (`hashName()`), jamais le nom d'origine

## Données sensibles
- [ ] Pièces d'identité, numéros de pièce : cast `encrypted`
- [ ] Aucune donnée personnelle dans les logs
- [ ] Sauvegardes chiffrées, copiées hors serveur, restauration testée

## Code
- [ ] Interdits absents : `eval`, `exec`, `shell_exec`, `system`, `passthru`, `extract`,
      `unserialize` sur donnée externe, `md5`/`sha1` pour des mots de passe
- [ ] `composer audit` sans alerte
- [ ] PHPStan niveau 6 minimum sans erreur
- [ ] Dépendances à jour, PHP 8.3+ maintenu

## Traçabilité
- [ ] Toute création, modification, connexion, export et validation journalisés (qui, quand, IP, appareil,
      avant/après)
- [ ] `audit_logs`, `operations`, `ecritures` protégés en insertion seule (triggers MySQL)
