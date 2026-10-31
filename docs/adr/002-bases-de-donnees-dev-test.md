# ADR 002 — Bases de données de développement et de test

- Date : 2026-10-31
- Statut : accepté

## Contexte

La production tourne sur MySQL 8. Le poste de développement a MariaDB 11.8, dont le comportement
diffère (verrous, colonnes générées, triggers). SQLite ignore `lockForUpdate()`.

## Décision

- `docker-compose.yml` fournit MySQL 8.4 (port 3307) et Redis. Deux bases : `microfinance`
  (développement) et `microfinance_test` (tests MySQL).
- `php artisan test` : SQLite en mémoire, groupe `mysql` exclu (`phpunit.xml`).
- `composer test:mysql` : groupe `mysql` sur `microfinance_test`.
- `Tests\TestCase` refuse de lancer les tests sur toute base qui n'est pas `:memory:` ou `*_test`,
  pour que `RefreshDatabase` ne vide jamais la base de développement.
- `log_bin_trust_function_creators=1` dans le conteneur et en CI : les migrations créent des triggers
  « insertion seule » avec l'utilisateur applicatif.
- Redis via `predis/predis` (l'extension `phpredis` n'est pas installée sur le poste).

## Conséquences

- En production, le DBA devra soit activer `log_bin_trust_function_creators`, soit lancer les
  migrations avec un compte ayant le droit de créer des triggers (le compte applicatif reste sans
  `DROP`/`ALTER`/`GRANT`, cf. references/securite.md).
