# ADR 008 — Plan de comptes simplifié et table des comptes

- Date : 2026-10-31
- Statut : accepté (choix du porteur du projet)

## Contexte

Le moteur financier a besoin de comptes à débiter et créditer. Le plan comptable complet (SYSCOHADA,
balance, clôtures) est prévu en Phase 6.

## Décision

- Montants : value object `App\Support\Montant\Montant` (entier de centimes + `Devise` USD/CDF),
  cast `MontantCast` pour les colonnes BIGINT. `brick/money` reste disponible pour des calculs avancés.
- `comptes` dans `Domain/Finance` : numéro unique par organisation (fourni à l'ouverture, aucun format
  imposé tant que le cahier des charges ne le fixe pas), agence facultative (comptes du siège),
  `syscohada` facultatif à compléter en Phase 6.
- `NatureCompte` : caisse, épargne, parts sociales, cycle de tontine, fonds social, prêt, créance
  membre, produit, charge. Chaque nature a un **sens normal** (débit ou crédit) dans lequel le solde est
  lu.
- **Découvert** : caisse, épargne et parts sociales ne passent jamais sous zéro ; les autres natures
  n'ont pas de limite. La règle est portée par la nature ; elle deviendra un paramètre des types de
  comptes en Phase 2.
- `solde_cache` n'est jamais assignable en masse ; seul le moteur l'écrit.

## Conséquences

- Le moteur (étape 4) vérifie le découvert sur le solde « sens normal » après mouvement, sous verrou.
