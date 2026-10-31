# ADR 018 — Tontines : groupes, cycles et paramètres

- Date : 2026-10-31
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- Deux types de groupes : **rotative** (cotisation fixe, un bénéficiaire par séance) et **AVEC**
  (achat de parts, partage en fin de cycle).
- Groupes, inscriptions et cycles gérés par l'**admin et le gérant** (`tontines.gerer`) ; le **comité**
  (`tontines.valider`) valide le partage de fin de cycle et les aides du fonds social.
- Cotisations encaissées **en espèces à la caisse** (session ouverte) ; pot du tour versé **sur le compte
  d'épargne** du bénéficiaire.
- Pénalités automatiques : **absence, retard, cotisation non payée** (montants fixes du cycle) ; elles
  deviennent une **dette** du membre, payée plus tard, et **reviennent au cycle**.
- Enchère : la remise va à **l'organisation** (produit).
- Fonds social : **cotisation fixe par séance** + **aides décidées par le comité**.
- AVEC : prix fixe de la part, **1 à 5 parts par séance**.

## Décisions techniques

- Cycle numéroté par groupe ; paramètres modifiables en préparation, **figés au démarrage**.
- Démarrage : participants = membres actifs du groupe à cet instant (au moins deux) ; ouverture des
  comptes du cycle : fonds (nature cycle de tontine), fonds social, pénalités dues (créances).
- Rotative : une séance par participant ; AVEC : nombre de séances fixé au paramétrage.
- Un seul cycle en cours par groupe (colonne générée + UNIQUE).
- Périodicités proposées : chaque semaine, toutes les deux semaines, chaque mois (à confirmer).

## Règles de séance (validées le 2026-10-31, mises en œuvre à l'étape suivante)

- Rotative : le bénéficiaire reçoit **toujours le pot complet** (participants × cotisation) ; le manque
  est avancé par le fonds du cycle et devient une dette du membre défaillant.
- Cotisation **payable en partie** : le reste devient une dette et la pénalité « non payée » s'applique.
- Tirage au sort : **une fois, au démarrage** du cycle (ordre complet connu d'avance).
- Fonds social : obligatoire à chaque séance ; non payé → **dette, sans pénalité**.
