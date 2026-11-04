# ADR 009 — Moteur financier

- Date : 2026-10-31
- Statut : accepté

## Contexte

CLAUDE.md §4 : toute écriture d'argent passe par `MoteurFinancier::executer()` (transaction, verrous
triés, partie double, idempotence), aucune écriture en période clôturée, solde = somme des écritures.

## Décision

- `executer(cle, type, lignes, dateValeur?, motif?, extourneDe?, validateur?)` :
  1. clé déjà connue → renvoie l'opération existante, rien n'est créé ;
  2. au moins deux lignes, une seule devise, Σ débits = Σ crédits (en `Montant`, sans dépassement) ;
  3. transaction (3 essais en cas d'interblocage) ; `lockForUpdate` sur les comptes triés par id ;
  4. comptes existants, actifs, de la devise de l'opération, tous de la même organisation (celle de
     l'utilisateur connecté, grâce au scope ; ou celle des comptes pour une commande planifiée) ;
  5. date de valeur strictement après la dernière clôture de l'organisation ;
  6. découvert : pour une caisse, une épargne ou des parts qui diminuent, le solde **réel** (somme des
     écritures, sous verrou) ne doit pas devenir négatif — le cache n'est jamais cru ;
  7. insertion de l'opération et des écritures, mise à jour de `solde_cache`, ligne d'audit,
     événement `OperationEnregistree` émis après validation de la transaction ;
  8. collision sur la clé (deux requêtes simultanées) → on renvoie l'opération de la requête gagnante.
- Clôture provisoire : table `clotures_comptables` en insertion seule, commande
  clôture mensuelle demandée par le comptable et validée par le gérant (ADR 023 ; la commande console
  `finance:cloturer` de la Phase 1 a été retirée) — jamais dans le futur, jamais en arrière.
- Refus métier : `OperationDesequilibree`, `CompteIndisponible`, `PeriodeCloturee`, `SoldeInsuffisant`
  (tous `RefusFinancier`) ; rien n'est écrit.

## Conséquences

- Le calcul du solde réel coûte une somme indexée par compte à chaque retrait ; à surveiller quand les
  volumes grandiront (piste : solde arrêté par période en Phase 6).
- `today()` suit le fuseau de l'application (UTC aujourd'hui) : le fuseau des organisations reste à décider.
- Les tests de concurrence (SQLite ignore les verrous) sont à l'étape 7, sur MySQL.
