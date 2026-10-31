# ADR 015 — Reçus PDF

- Date : 2026-10-31
- Statut : accepté (contenu fixé par le porteur du projet)

## Règles validées

- Un reçu pour **chaque mouvement** (dépôt, retrait, virement émis et reçu : un reçu par compte).
- Format **A5**. Mentions : numéro, date, organisation et **logo**, agence, membre, compte, opération,
  montant, **montant en lettres**, frais, **solde après opération**, **nom du caissier**,
  **lignes de signature** du membre et du caissier.

## Décisions techniques

- Numéro `REC-000001`, séquentiel par organisation, attribué dans la transaction de l'opération
  (verrou sur l'organisation) ; le solde est photographié à ce moment : une réimpression montre le même.
- PDF généré **en file d'attente** (`GenererRecuPdf`, après validation de la transaction) avec
  `barryvdh/laravel-dompdf` ; polices intégrées partiellement (≈ 28 Ko par reçu au lieu de 1,2 Mo).
- Stockage sur le disque privé, nom aléatoire ; téléchargement par contrôleur + `RecuPolicy`.
- Logo : envoi par l'admin (PNG/JPEG, 512 Ko, 2000 × 2000 max), disque privé, nom aléatoire, intégré au
  PDF en base64 (dompdf n'accède jamais au réseau).
- Montant en lettres : `MontantEnLettres`, orthographe traditionnelle, dollars américains / cents,
  francs congolais / centimes.
