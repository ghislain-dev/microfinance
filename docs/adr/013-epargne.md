# ADR 013 — Produits et comptes d'épargne

- Date : 2026-10-31
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- Types de comptes (produits) paramétrés par l'admin et le gérant (`epargne.parametrer`) : code, libellé,
  devise, **solde minimum**, **dépôt minimum**, **frais de retrait** (montant fixe, versé en produit),
  **durée de blocage en mois** (vide = retraits libres).
- Ouverture d'un compte par l'admin, le gérant ou le caissier (`epargne.ouvrir_compte`).
- **Plusieurs comptes du même type** par membre : numéro `NUMÉRO-MEMBRE-TYPE-rang` (ex. `BUK-000045-DAT-2`).
- Échéance de blocage = date d'ouverture + durée du type, fixée à l'ouverture.

## Décisions techniques

- Un compte d'épargne = un compte du grand livre de nature épargne (jamais négatif), même numéro,
  agence du membre. Le solde affiché est le solde réel (somme des écritures).
- Code et devise d'un type sont figés après création ; les autres paramètres (minimums, frais)
  s'appliquent aux comptes existants au moment de chaque opération ; la durée de blocage ne vaut que pour
  les nouveaux comptes.
- Ouverture refusée pour un membre non actif ou un type retiré (choix de prudence, à confirmer).
- Rang attribué sous verrou du membre ; `UNIQUE(membre_id, type, rang)`.
