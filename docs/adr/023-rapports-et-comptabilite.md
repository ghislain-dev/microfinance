# ADR 023 — Rapports, comptabilité et clôture mensuelle

- Date : 2026-11-04
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- **Correspondance SYSCOHADA paramétrable** : chaque rubrique du plan interne (nature de compte, ou compte
  interne particulier : intérêts de prêts, frais de retrait…) reçoit un numéro SYSCOHADA ; une proposition
  est fournie par défaut, **le comptable la modifie**.
- États comptables **par devise** (aucune conversion) : journal, grand livre, balance ; consultés et exportés
  par le **comptable et le gérant**.
- **Clôture mensuelle** : le **comptable** la demande, le **gérant** la valide (4 yeux, jamais la même personne) ;
  ensuite plus aucune écriture datée de ce mois. Contrôles bloquants : aucune session de caisse ouverte ou en
  écart sur le mois, aucun versement de collecteur en écart, vérification des soldes sans écart, aucun lot
  d'intérêts / ristournes ni partage de tontine en attente de validation.
- Exports **PDF + CSV**, générés en file d'attente puis téléchargés : états comptables, relevé de compte
  membre, portefeuille de crédit, caisse et collecte.
- Relevé du membre : imprimé au guichet **et téléchargeable dans l'application du membre**.
- Droits : relevé membre (caissier, gérant, comptable) ; portefeuille de crédit (gérant, comptable, agent de
  crédit) ; caisse et collecte (gérant, comptable).
- Tableau de bord : épargne, crédit, caisse et collecte, tontines et membres (chaque bloc selon les droits).

## À confirmer

- Les numéros SYSCOHADA proposés par défaut sont une **proposition à faire valider par le comptable**
  (classes 1 à 7 du SYSCOHADA révisé ; le plan propre aux institutions de microfinance de la BCC peut
  différer).
