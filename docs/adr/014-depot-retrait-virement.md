# ADR 014 — Dépôt, retrait, virement et gros décaissements

- Date : 2026-10-31
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- Dépôt et retrait en espèces : caissier ou gérant, **session de caisse ouverte** de l'opérateur.
- Retrait : frais du produit versés en produit ; le solde minimum doit rester (frais compris) ;
  aucun retrait avant l'échéance d'une épargne bloquée.
- Virement : caissier ou gérant (`epargne.virer`), **entre membres de l'organisation**, même devise.
- **Gros décaissement** : au-delà du seuil (par devise, fixé par admin ou gérant), une **demande** est
  créée ; le gérant ou le comptable la valide (`operations.valider_decaissement`), jamais le demandeur ;
  le retrait validé est ensuite payé au guichet, le virement validé s'exécute aussitôt.
- Statut du membre : actif → tout ; sinon **retraits seulement** (restitution).

## Décisions techniques

- Chaque opération passe par le MoteurFinancier (clé d'idempotence fournie par le formulaire) et laisse
  une ligne `mouvements_epargne` (insertion seule) liée à `operation_id` : base du reçu.
- Le solde minimum est jugé sur le solde réel, sous verrou du compte d'épargne.
- Noms courts pour les clés composées : MySQL limite les identifiants à 64 caractères.

## Décisions complémentaires (confirmées le 2026-10-31)

- Sans seuil défini pour une devise, aucune validation n'est exigée.
- Pas de frais sur les virements (seuls des frais de retrait sont paramétrés).
- Un virement sortant d'un membre non actif est refusé (seuls les retraits restent permis).
