# ADR 019 — Tontines : séances, dettes et tours

- Date : 2026-10-31
- Statut : accepté (règles : ADR 018)

## Décisions

- Au démarrage : toutes les séances sont planifiées (dates selon la périodicité) ; les tours sont fixés
  par **tirage au sort** (Fisher-Yates avec `random_int`, journalisé `tontines.tirage`) ou par
  l'**ordre fourni** par le gérant ; à l'**enchère**, le tour est attribué séance par séance.
  `tours` est en insertion seule : un tirage n'est jamais recalculé.
- Saisie d'une séance en **une seule action** (caissier ou gérant, session de caisse ouverte), dans
  l'ordre des séances, une seule fois (un double envoi est sans effet) ; chaque montant passe par le
  moteur avec la clé `seance:{id}:membre:{id}:{type}` ; `UNIQUE(seance, membre, type)`.
- Écritures :
  | Mouvement | Débit | Crédit |
  |---|---|---|
  | Cotisation / parts payées | caisse | fonds du cycle |
  | Cotisation non payée (reste) | dettes des membres | fonds du cycle |
  | Pénalité (absence, retard, impayé) | dettes des membres | fonds du cycle |
  | Fonds social payé / non payé | caisse / dettes | fonds social |
  | Règlement de dettes | caisse | dettes des membres |
  | Tour (rotative) | fonds du cycle (pot complet) | épargne du bénéficiaire (+ remise en produit) |
- Dette d'un membre = dettes constatées − règlements ; un règlement ne peut dépasser la dette.

## À confirmer

- AVEC : 0 part → pénalité « non payée », sans dette de parts (épargne volontaire).
- Le bénéficiaire doit avoir un compte d'épargne dans la devise du cycle, sinon la séance est refusée.
