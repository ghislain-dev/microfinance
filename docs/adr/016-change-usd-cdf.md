# ADR 016 — Taux de change et conversion USD/CDF

- Date : 2026-10-31
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- Taux du jour saisi par le **gérant ou le comptable** (`change.saisir_taux`) : un **taux d'achat** et un
  **taux de vente** (CDF pour 1 USD), achat ≤ vente.
- Conversion **entre deux comptes d'un même membre** (pas de change d'espèces au guichet).
- USD → CDF au taux d'achat, CDF → USD au taux de vente ; montant reçu **arrondi au centime inférieur**.
- L'écart avec le taux moyen ((achat + vente) / 2) est la **marge**, comptabilisée en produit.

## Décisions techniques

- Taux stockés en **millionièmes** (entiers) ; calculs en `brick/math`, sans float.
- Deux opérations liées dans une même transaction (CLAUDE.md §4.5), une par devise, via un compte de
  **position de change** par devise :
  - devise cédée : débit épargne du membre, crédit position ;
  - devise reçue : débit position (contre-valeur au taux moyen), crédit épargne du membre,
    crédit produit « marge de change ».
- `conversions` (insertion seule) relie les deux opérations et garde le taux appliqué.
- `taux_change` en insertion seule : une correction crée une nouvelle ligne, la plus récente du jour
  s'applique ; sans taux du jour, aucune conversion.
- Chaque compte reçoit un mouvement (`change_sortant` / `change_entrant`) et donc un reçu.

## À confirmer

- La conversion est faite par les rôles du virement (caissier, gérant) avec ses règles (membre actif,
  compte non bloqué, solde minimum) ; pas de seuil de validation (l'argent reste au membre).
- « Aujourd'hui » suit désormais le fuseau de l'organisation (ADR 024).
