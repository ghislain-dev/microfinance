# ADR 012 — Caisses, sessions, billetage et écarts

- Date : 2026-10-31
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- Une caisse (guichet) appartient à une agence ; créée par l'admin ou le gérant (`caisses.gerer`).
- Tenue de caisse (`caisse.operer`) : caissier ou gérant, **rattaché à l'agence** de la caisse.
- Billetage : **billets seulement** — USD 1, 2, 5, 10, 20, 50, 100 ; CDF 50 à 20 000.
- **Comptage à l'ouverture et à la clôture** ; tout écart bloque (ouverture ou clôture) jusqu'à la
  validation du gérant (`caisse.valider_ecart`, règle du skill), jamais sur sa propre session, avec motif.
- Écart validé : **manquant = créance sur le caissier** (nature `creance_personnel`, un compte par caissier
  et par devise) ; **excédent = produit** (« Excédents de caisse »). Comptabilisé par le MoteurFinancier.

## Décisions techniques

- Une caisse = un compte de nature caisse par devise (`caisse_comptes`).
- Session : colonne générée `active_flag` (1 si non fermée, NULL sinon) avec `UNIQUE(caisse_id, active_flag)`
  et `UNIQUE(caissier_id, active_flag)` : une session active par caisse et par caissier, garantie par la
  base (MySQL et SQLite). Vérifié : 10 ouvertures simultanées → 1 seule réussit.
- Le solde théorique est le **solde réel** du compte de caisse (somme des écritures).
- `billetages` et `ecarts_caisse` en insertion seule ; les sessions sont journalisées (audit).

## Décision complémentaire (confirmée le 2026-10-31)

- Un écart constaté **à l'ouverture** est imputé au caissier qui ouvre la session ; le gérant peut
  l'extourner ensuite si la responsabilité est autre.
