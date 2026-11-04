# CLAUDE.md — Application de gestion financière (coopérative, tontine, microfinance)

Ce fichier est lu par Claude Code au début de chaque session. Il fixe le contexte, les règles
absolues et le plan de travail. Le détail fonctionnel est dans `docs/cahier-des-charges.md`.
Pour toute tâche qui touche à l'argent, aux comptes, aux tontines, aux crédits, à la caisse ou à la
synchronisation mobile, **charger le skill `finance-cooperative`** (`.claude/skills/finance-cooperative/`).

---

## 1. Le projet en 5 lignes

- Une seule application, **multi-organisation** : tontine rotative, épargne cumulative (AVEC),
  coopérative, microfinance. Les règles sont des **paramètres**, jamais du code spécifique à un client.
- **Backend** : PHP 8.3 + Laravel 13, architecture **MVC modulaire** (phase 1), puis extraction de
  quelques modules en **services** (phase 2).
- **Web** (bureau) : Blade + Bootstrap 5. **Mobile** (membres + collecteurs) : app Android qui consomme
  l'API REST `/api/v1`, avec base locale SQLite pour le hors-ligne.
- **Base** : MySQL 8 (production), SQLite (tests, base locale mobile, installation mono-poste).
- Priorité absolue : **zéro perte, zéro doublon, traçabilité totale** de chaque montant.

## 2. Commandes

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed            # base de démo
php artisan test --parallel           # tests (SQLite en mémoire)
php artisan test --group=mysql        # tests de concurrence (MySQL obligatoire, voir §5)
./vendor/bin/pint                     # formatage
./vendor/bin/phpstan analyse          # niveau 6 minimum
composer audit                        # failles connues dans les dépendances
php artisan finance:verifier-soldes   # recalcule les soldes depuis le grand livre, alerte si écart
```

Une tâche n'est **terminée** que si `pint`, `phpstan`, `composer audit` et `php artisan test` passent.

## 3. Structure du code

```
app/
  Domain/                      # un dossier par module métier
    Organisation/              # organisations, agences, paramètres, taux de change
    Membres/
    Tontines/                  # groupes, cycles, séances, cotisations, tours, partage
    Epargne/                   # comptes, parts sociales, fonds social
    Credit/                    # prêts, échéanciers, remboursements, pénalités
    Caisse/                    # caisses, sessions, billetage
    Finance/                   # MOTEUR FINANCIER : opérations, écritures, montants (cœur)
    Comptabilite/              # plan comptable, balance, clôtures
    Sync/                      # réception des lots du mobile
      Models/ Services/ Actions/ Events/ Policies/ DTO/
  Http/
    Controllers/Web/           # contrôleurs minces : valider → appeler un service → répondre
    Controllers/Api/V1/
    Requests/                  # Form Requests (toute validation ici)
    Resources/                 # format JSON de l'API
  Support/                     # Montant, Idempotence, traits multi-organisation
database/migrations/           # une migration par changement, jamais modifier une migration déjà en prod
resources/views/<module>/
routes/web.php, routes/api.php
tests/Feature/<Module>/, tests/Unit/<Module>/
docs/                          # cahier des charges, décisions d'architecture (docs/adr/)
```

Règles de dépendance entre modules :
- Un module n'utilise **que les Services/Actions publics** d'un autre module, jamais ses modèles
  directement pour écrire.
- **Seul `Domain/Finance` écrit dans `operations` et `ecritures`.** Tontines, Épargne, Crédit et Caisse
  construisent une opération et la confient au `MoteurFinancier`.
- Les modules communiquent par **événements** (`OperationEnregistree`, `CotisationRecue`…) pour
  préparer la phase 2 (services) sans réécriture.

## 4. Règles absolues — argent

1. Montants en **entiers d'unité mineure** (`BIGINT`, centimes) ou via `brick/money`. **Jamais** `float`,
   `double`, `round()` sur un montant, ni calcul d'argent en JavaScript côté client.
2. Toute écriture d'argent passe par `MoteurFinancier::executer()` : **une transaction SQL**, verrou
   `lockForUpdate()` sur les comptes (triés par id), **partie double équilibrée**, **clé d'idempotence**.
3. `operations` et `ecritures` sont **en insertion seule** : pas de `update()`, pas de `delete()`, pas de
   `SoftDeletes`. Correction = **extourne** liée à l'opération d'origine + motif + validation.
4. Solde = somme des écritures. La colonne `solde_cache` n'est qu'un cache vérifié chaque nuit.
5. Une opération = une devise. Conversion USD/CDF = deux opérations liées au taux du jour enregistré.
6. Aucune écriture dans une période clôturée. Date de valeur ≠ date de saisie.
7. Annulation, gros décaissement, écart de caisse : **validation par une seconde personne** (4 yeux),
   et jamais par l'auteur de l'opération.
8. `organisation_id` vient **toujours de l'utilisateur connecté**, jamais de la requête.

## 5. Règles absolues — sécurité PHP

- SQL : Eloquent ou Query Builder avec paramètres. Interdit : concaténer une variable dans `DB::raw`,
  `whereRaw`, `orderByRaw` (si nécessaire : liste blanche de colonnes).
- Vues : `{{ }}` uniquement. `{!! !!}` interdit sur toute donnée venant d'un utilisateur.
- Chaque route protégée par `auth` + une **Policy** ; chaque modèle métier utilise le trait
  `AppartientAOrganisation` (scope global). Tester l'accès croisé entre organisations.
- `$fillable` explicite partout ; jamais `$guarded = []`. Montants et comptes validés dans un Form Request.
- Mots de passe : `Hash::make` (bcrypt/argon2id). OTP pour gérant, caissier, comptable.
- `throttle` sur login, OTP, API. Session : `secure`, `http_only`, `same_site=strict`.
- Interdits : `eval`, `exec`, `shell_exec`, `system`, `unserialize` sur données externes, `extract()`.
- Uploads : contrôle MIME + taille, disque `local` (hors `public/`), nom aléatoire.
- Pièces d'identité et numéros sensibles : cast `encrypted`.
- `APP_DEBUG=false` en production ; `.env` jamais commité.
- **SQLite ignore `lockForUpdate()`** : les tests de concurrence et de verrouillage sont marqués
  `#[Group('mysql')]` et tournent sur MySQL.

## 6. Performance et anti-doublon

- Index sur toute colonne de recherche, de tri ou de jointure ; `UNIQUE` pour chaque règle « une seule
  fois » (voir le cahier des charges §8). Le doublon est bloqué **par la base**, pas seulement par le code.
- Pas de requête N+1 : `Model::preventLazyLoading()` activé hors production ; `with()` systématique.
- Pagination partout (`paginate`/`cursorPaginate`), jamais `->get()` sur une table d'opérations.
- Rapports lourds, SMS, exports : en **file d'attente** (Redis), jamais pendant la requête HTTP.
- API mobile : ne renvoyer que les changements depuis `?depuis=<horodatage>`, réponses compressées.

## 7. Façon de travailler avec Claude Code

1. **Une phase à la fois** (voir §8). Commencer la session par relire ce fichier et la phase en cours.
2. Pour une tâche non triviale : proposer d'abord un **plan court** (fichiers touchés, migrations, tests),
   attendre l'accord, puis coder.
3. **Test d'abord** pour toute règle d'argent : écrire le test qui échoue, puis le code.
4. Petits commits clairs en français : `feat(tontines): partage de fin de cycle`.
5. Après chaque tâche : lancer les commandes du §2, cocher la case dans §8, résumer en 3–5 lignes.
6. Toute décision d'architecture → une fiche dans `docs/adr/NNN-titre.md` (contexte, décision, conséquences).
7. Ne jamais inventer une règle métier : si le cahier des charges ne la précise pas, **demander**.

## 8. Planification

### Phase 0 — Fondations (≈ 1 semaine)
- [x] Projet Laravel 13, PHP 8.3, Pint, PHPStan, Pest/PHPUnit, CI (tests + audit à chaque push)
- [x] `.env.example`, configuration MySQL + SQLite de test
- [x] Organisations, agences, utilisateurs ; trait `AppartientAOrganisation` + scope global
- [x] Rôles et permissions (`spatie/laravel-permission`), Policies de base
- [x] Authentification web + OTP SMS, API Sanctum, `throttle` (OTP par e-mail, canal SMS prêt)
- [x] Journal d'audit en insertion seule
- [x] Mise en page Bootstrap : layout, menu par rôle, tableau de bord vide

### Phase 1 — Membres et moteur financier (≈ 3 semaines)
- [x] Membres : CRUD, numéro unique, détection de doublons (téléphone, pièce), statuts
- [x] Value object `Montant` (ou `brick/money`), plan de comptes, table `comptes`
- [x] `operations` + `ecritures` (insertion seule, triggers MySQL anti-UPDATE/DELETE)
- [x] `MoteurFinancier::executer()` : transaction, verrous, équilibre, idempotence
- [x] Extourne avec validation 4 yeux
- [x] Commande `finance:verifier-soldes` planifiée chaque nuit
- [x] Tests : équilibre, idempotence ×10, concurrence 50 retraits (MySQL)

### Phase 2 — Épargne et caisse (≈ 2 semaines)
- [x] Types de comptes paramétrables, dépôt, retrait, virement, reçu PDF
- [x] Caisses, ouverture/clôture de session, billetage, écart bloquant
- [x] Taux de change du jour, opérations USD/CDF
- [x] Intérêts / ristournes sur épargne

### Phase 3 — Tontines (≈ 2 semaines)
- [x] Groupes, cycles, paramètres (montant, périodicité, mode d’attribution des tours)
- [x] Séances : feuille de présence + cotisations en une seule saisie
- [x] Tours (tirage, ordre fixé, enchère), pénalités automatiques, fonds social
- [x] Partage de fin de cycle calculé + validation du comité

### Phase 4 — Crédit (≈ 2 semaines)
- [x] Demande, analyse, approbation multi-niveaux selon le montant, décaissement
- [x] Échéancier linéaire et dégressif (écart d'arrondi sur la dernière échéance)
- [x] Remboursements (ordre : pénalités → intérêts → capital), retards, PAR 30/90

### Phase 5 — API et applications mobiles (≈ 3 semaines)
- [x] API `/api/v1` versionnée, Resources, documentation
- [x] Endpoint de synchronisation par lots (idempotent, voir skill)
- [ ] App membres : solde, cotisations, tour, échéances, notifications
- [x] App collecteurs : saisie hors-ligne SQLite, synchronisation, reçus

### Phase 6 — Rapports et comptabilité (≈ 2 semaines)
- [x] Grand livre, balance, journal, clôture mensuelle
- [x] Tableau de bord, relevé membre, exports PDF/Excel en file d'attente

### Pilote (≈ 4 semaines)
- [ ] Une tontine réelle pendant un cycle, double saisie papier + application
- [ ] Comparaison hebdomadaire, corrections, puis ouverture générale

## 9. À ne jamais faire

- Modifier ou supprimer une opération, une écriture ou une ligne d'audit.
- Calculer un solde en additionnant des floats ou dans une vue Blade.
- Écrire de l'argent hors du `MoteurFinancier`.
- Faire confiance à un `organisation_id`, un montant ou un solde envoyé par le client (web ou mobile).
- Lancer `migrate:fresh` ou `db:wipe` sur autre chose que la base locale de développement.
- Commiter `.env`, des sauvegardes de base ou des données réelles de membres.
