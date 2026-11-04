# ADR 025 — Affectation du résultat annuel aux excédents à répartir

- Date : 2026-11-04
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- Après la **clôture de décembre**, le **comptable** propose d'affecter **tout ou partie** du résultat de l'année
  (produits − charges, par devise, dates de valeur de l'année) aux excédents à répartir ; le **gérant** valide
  ou rejette, jamais l'auteur.
- Les excédents à répartir alimentent la **ristourne** (ADR 017).

## Décisions techniques

- Les comptes de produits et de charges ne sont pas remis à zéro en fin d'année. L'affectation débite un compte
  « résultat net en instance d'affectation » (`RESULTAT-NET-{devise}`, nouvelle nature `resultat`, solde au
  crédit, peut être débiteur) et crédite `EXCEDENTS-A-REPARTIR-{devise}`. Elle est datée du jour de la
  validation : l'année affectée est clôturée.
- Restant à affecter = résultat − affectations validées ou en attente ; une seule proposition en attente par
  année et devise (colonne générée + UNIQUE).
- SYSCOHADA proposé : 1301 résultat en instance d'affectation, 1188 réserves (excédents à répartir), à vérifier
  par le comptable (ADR 023).
