# ADR 001 — Laravel 13 au lieu de Laravel 11

- Date : 2026-10-31
- Statut : accepté

## Contexte

Le plan initial prévoyait Laravel 11. Cette version ne reçoit plus de correctifs de sécurité
depuis mars 2026, ce qui contredit la grille de sécurité (« dépendances à jour »).

## Décision

Le projet utilise Laravel 13 (version maintenue), avec PHP 8.3 minimum
(`config.platform.php = 8.3.0` dans `composer.json`, pour que les dépendances restent
compatibles 8.3 même si le poste de développement a PHP 8.4).

## Conséquences

- CLAUDE.md mis à jour (§1 et §8).
- Les tests de concurrence (`#[Group('mysql')]`) tournent sur un conteneur Docker `mysql:8.4`,
  le poste de développement ayant MariaDB.
