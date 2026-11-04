# ADR 017 — Intérêts et ristournes sur l'épargne

- Date : 2026-10-31
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- **Intérêts** : taux annuel par produit ; base = **solde de fin de période** ; **mensuels**,
  **calculés puis validés** par le gérant ou le comptable avant versement ; arrondi au **centime inférieur**.
- **Ristourne** : montant décidé par l'assemblée, réparti au prorata de l'épargne à une date de
  référence ; saisi par le gérant ou le comptable, **validé par l'autre** (jamais le même) ; prélevé sur
  un compte « **excédents à répartir** » (nature fonds propres, jamais négatif).
- **Membres actifs seulement**, pour les intérêts comme pour la ristourne.

## Décisions techniques

- Taux stocké en points de base (325 = 3,25 %/an) ; intérêt = base × pb / 120 000 (brick/math, sans float).
- Solde à une date = somme des écritures dont la date de valeur est antérieure ou égale
  (`MoteurFinancier::soldesAu`, une requête groupée).
- Ristourne : méthode du **plus fort reste** (skill §5) : Σ des parts = montant exact.
- Lots `remunerations` (brouillon → versée ou rejetée) et lignes en insertion seule ; un seul lot vivant
  par période, devise et type (colonne générée + UNIQUE) ; un rejet permet de recalculer ; pas de lot vide.
- Validation : tout le lot ou rien (une transaction) ; chaque versement passe par le moteur et laisse un
  mouvement (« Intérêts », « Ristourne ») et un reçu.
- Commande `epargne:calculer-interets`, planifiée le 1er du mois à 3 h, pour chaque organisation et devise.

## Alimentation des excédents

- Par l'affectation du résultat annuel, après la clôture de décembre (ADR 025).
