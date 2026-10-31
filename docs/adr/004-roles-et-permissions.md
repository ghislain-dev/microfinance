# ADR 004 — Rôles, permissions et création des organisations

- Date : 2026-10-31
- Statut : accepté (matrice validée par le porteur du projet)

## Contexte

Huit rôles : admin, gérant, comptable, caissier, collecteur, agent de crédit, comité, membre.
Chaque organisation attribue ses propres rôles à ses utilisateurs.

## Décision

- `spatie/laravel-permission` en mode **teams** sur `organisation_id`. Les rôles et permissions sont
  **globaux** (même matrice pour toutes les organisations) ; leur **attribution** est propre à chaque
  organisation. Un rôle reçu dans l'organisation B ne vaut rien dans A (testé).
- L'équipe active est l'organisation de l'utilisateur connecté (écouteur `Authenticated`).
  Toute action qui évalue des droits hors requête (console, jobs) fixe `setPermissionsTeamId()` elle-même.
- Matrice Phase 0 dans `App\Domain\Organisation\Acces\Role::droits()` ; la spécification est recopiée
  à la main dans `tests/Feature/Acces/MatricePermissionsTest.php` (une case = un test).
- Un admin ne modifie jamais ses propres rôles et ne désactive jamais son propre compte.
- Le membre n'a aucune permission web (application mobile uniquement).
- **Pas de super-administrateur web** : `php artisan organisation:creer {code} {nom}` crée l'organisation
  et son premier admin. Aucun compte web ne voit plusieurs organisations.
- `RolesEtPermissionsSeeder` est idempotent, à relancer à chaque déploiement (il est aussi appelé par
  `organisation:creer`).

## Conséquences

- Chaque nouvelle phase ajoute ses permissions (dépôt, retrait, validation…) à `Droit` et à la matrice,
  après validation.
- Les permissions sur l'argent qui exigent les 4 yeux seront vérifiées en plus dans les actions
  (auteur ≠ validateur), pas seulement par rôle.
