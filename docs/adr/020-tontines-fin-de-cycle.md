# ADR 020 — Tontines : aides du fonds social et partage de fin de cycle

- Date : 2026-11-01
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- **Aides** : saisies par le gérant, **validées par le comité** (jamais le demandeur), versées sur
  l'épargne du membre, dans la limite du solde du fonds social.
- **Partage** (une fois toutes les séances tenues) : fonds du cycle réparti au prorata des parts (AVEC)
  ou à parts égales (rotative) ; **les dettes non réglées sont déduites de la part**, la dette restante
  reste due ; reste **versé sur l'épargne** ; **validation du comité** (jamais la personne qui a calculé),
  puis clôture du cycle.
- **Fonds social** : son solde est **reporté au cycle suivant** du groupe, au démarrage de celui-ci.

## Décisions techniques

- Méthode du plus fort reste (skill §5) : Σ des parts = fonds du cycle, au centime.
- Compensation : débit fonds du cycle, crédit dettes des membres ; elle réduit la dette calculée.
- Un seul partage vivant par cycle (colonne générée + UNIQUE) ; un rejet permet de recalculer ; lignes
  en insertion seule ; validation tout ou rien.
- Partage refusé tant qu'une aide attend la décision du comité.
