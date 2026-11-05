# Microfinance

Application de gestion financière pour **coopératives, tontines, groupes d'épargne (AVEC) et institutions de
microfinance**. Une seule installation sert plusieurs organisations ; les règles de chacune (produits,
plafonds, pénalités, fuseau horaire…) sont des **paramètres**, jamais du code spécifique à un client.

Priorité absolue : **zéro perte, zéro doublon, traçabilité totale** de chaque montant.

- Application web (bureau) pour le personnel : gérant, caissier, comptable, agent de crédit, comité, administrateur.
- Application Android **Collecteur** : collecte sur le terrain, hors-ligne, avec reçu sur imprimante Bluetooth.
- Application Android **Membre** : soldes, mouvements, tontines, prêts, relevés et notifications.
- Devises : dollar américain (USD) et franc congolais (CDF), sans conversion implicite.

---

## Fonctionnalités

| Domaine | Ce que fait l'application |
|---|---|
| **Membres** | Inscription avec numéro unique par agence, détection des doublons (téléphone, pièce d'identité chiffrée), statuts, accès mobile remis au guichet |
| **Épargne** | Produits paramétrables (solde minimum, frais, blocage, taux), dépôts, retraits, virements, validation 4 yeux au-delà d'un seuil, reçus PDF numérotés, relevés |
| **Caisse** | Caisses par agence, sessions avec billetage à l'ouverture et à la clôture, écart bloquant validé par le gérant |
| **Change** | Taux d'achat et de vente du jour, conversion USD ⇄ CDF entre deux comptes d'un membre, marge comptabilisée |
| **Intérêts et ristournes** | Intérêts mensuels calculés puis validés ; ristourne répartie au prorata, financée par l'affectation du résultat annuel |
| **Tontines** | Groupes rotatifs et AVEC, cycles, feuille de séance en une saisie (présences, cotisations, fonds social), tours par tirage, ordre fixé ou enchère, pénalités, aides du comité, partage de fin de cycle |
| **Crédit** | Produits, demande et analyse, approbation comité puis gérant au-delà d'un seuil, échéancier linéaire ou dégressif, garanties (épargne bloquée, cautions, biens), remboursements, pénalités journalières, PAR 30 / 90, prélèvement de la garantie en cas de défaut |
| **Collecte** | Téléphones autorisés par le gérant, tournées, synchronisation par lots sans doublon, caisse du collecteur, versements au guichet avec contrôle des écarts |
| **Comptabilité** | Correspondance SYSCOHADA paramétrable, journal, grand livre, balance par devise, clôture mensuelle (comptable puis gérant), affectation du résultat |
| **Rapports** | Tableau de bord par rôle ; exports PDF et CSV en file d'attente (états comptables, relevés, portefeuille de crédit, caisse et collecte) |
| **Notifications** | Push Firebase aux membres : opérations, tour de tontine, décision de prêt, rappel d'échéance |

## Principes de conception

- **Moteur financier unique** : toute écriture d'argent passe par `MoteurFinancier::executer()` — transaction
  SQL, verrous ordonnés, partie double équilibrée, clé d'idempotence.
- **Montants en centimes entiers** (`Montant`, `brick/math`) : jamais de nombre à virgule.
- **Insertion seule** pour les opérations, les écritures et le journal d'audit (triggers MySQL) ; une erreur se
  corrige par une **extourne** validée par une seconde personne.
- **Solde = somme des écritures** ; un contrôle nocturne compare les soldes au grand livre.
- **Séparation des tâches** : annulations, gros décaissements, écarts de caisse, prêts et clôtures sont validés
  par une autre personne que leur auteur.
- **Multi-organisation** : chaque requête est filtrée sur l'organisation de l'utilisateur connecté.

Les décisions et leurs raisons sont consignées dans [`docs/adr/`](docs/adr/) (une fiche par décision).

## Architecture

| Couche | Technologie |
|---|---|
| Serveur | PHP 8.3, Laravel 13, architecture modulaire (`app/Domain/<Module>`) |
| Web | Blade, Bootstrap 5, Vite |
| Base de données | MySQL 8 (production), SQLite (tests) |
| Files d'attente et cache | Redis |
| API mobile | REST `/api/v1`, Laravel Sanctum, documentation OpenAPI 3.1 |
| Mobile | Kotlin, Jetpack Compose, Hilt, Room, Retrofit, WorkManager |

```
app/Domain/         Organisation, Membres, Finance, Epargne, Caisse, Change, Tontines, Credit,
                    Collecte, Comptabilite, Rapports, Notifications
app/Http/           contrôleurs web et API, Form Requests, Resources
database/           migrations
docs/               décisions (adr/), API (api/openapi.json), mise en production (deploiement.md)
mobile/             applications Android (app-collecteur, app-membre) et modules partagés
tests/              tests Pest (Feature, Unit, Arch, Concurrence)
```

## Démarrage en développement

Prérequis : PHP 8.3+, Composer 2, Node.js 20+, Docker (MySQL 8.4 et Redis).

```bash
docker compose up -d                  # MySQL (port 3307) et Redis
composer install
cp .env.example .env && php artisan key:generate
# dans .env : APP_ENV=local, APP_DEBUG=true, SESSION_SECURE_COOKIE=false, DB_PASSWORD=…
php artisan migrate --seed            # rôles, droits et organisation de démonstration
npm ci && npm run build               # ou npm run dev pendant le développement
php artisan serve                     # http://localhost:8000
php artisan queue:work                # codes de connexion, reçus, exports, notifications
```

Comptes de démonstration (en local uniquement) : `<rôle>@demo.test` / `password`
(`admin`, `gerant`, `comptable`, `caissier`, `collecteur`, `agent_credit`, `comite`). Le code de connexion à
usage unique est écrit dans `storage/logs` tant que `MAIL_MAILER=log`.

Créer une organisation réelle et son premier administrateur :

```bash
php artisan organisation:creer COOPEC-GOMA "Coopérative de Goma"
```

## Tests et qualité

Une tâche n'est terminée que si tout passe :

```bash
./vendor/bin/pint                     # formatage
./vendor/bin/phpstan analyse          # analyse statique (niveau 6)
composer audit                        # failles connues des dépendances
php artisan test                      # suite complète (SQLite en mémoire)
composer test:mysql                   # concurrence et verrous (base microfinance_test, conteneur MySQL)
php artisan finance:verifier-soldes   # soldes recalculés depuis le grand livre
```

Les tests couvrent les règles d'argent (équilibre, idempotence, arrondis au centime), l'accès croisé entre
organisations, la séparation des tâches et la concurrence réelle sur MySQL (retraits, remboursements et
synchronisations simultanés).

## Applications mobiles

```bash
cd mobile
./gradlew :app-collecteur:assembleDebug -Pmicrofinance.apiUrl=http://10.0.2.2:8000/api/v1
./gradlew :app-membre:assembleDebug     -Pmicrofinance.apiUrl=http://10.0.2.2:8000/api/v1
./gradlew testDebugUnitTest             # tests unitaires et d'interface (Robolectric)
```

JDK 21 et Android SDK (API 37) requis. En version de production, l'application exige un serveur en HTTPS ; les
identifiants Firebase et la clé de signature restent hors du dépôt (voir [`docs/deploiement.md`](docs/deploiement.md)).

## Documentation

- [`docs/deploiement.md`](docs/deploiement.md) — mise en production : serveur, `.env`, file d'attente,
  planificateur, sauvegardes, applications Android, grille de sécurité.
- [`docs/api/openapi.json`](docs/api/openapi.json) — API `/api/v1` (un test vérifie que chaque route y figure).
- [`docs/adr/`](docs/adr/) — décisions d'architecture et règles métier validées.
- [`CLAUDE.md`](CLAUDE.md) — règles absolues du projet et planification.

## Licence

Logiciel propriétaire — © 2026 ghislain-dev, tous droits réservés. Voir [`LICENSE`](LICENSE).
