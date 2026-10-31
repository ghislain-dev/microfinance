# ADR 006 — Journal d'audit en insertion seule

- Date : 2026-10-31
- Statut : accepté

## Contexte

`references/securite.md` : toute création, modification, connexion, export et validation est journalisée
(qui, quand, IP, appareil, avant/après) ; `audit_logs` est protégé en insertion seule.
CLAUDE.md §9 : ne jamais modifier ni supprimer une ligne d'audit.

## Décision

- Table `audit_logs` sans `updated_at` et **sans clé étrangère** : un `ON DELETE SET NULL` ou une
  suppression en cascade réécrirait l'historique.
- Triggers `BEFORE UPDATE` et `BEFORE DELETE` sur **MySQL et SQLite** (installation mono-poste comprise) :
  refus avec « audit_logs : insertion seule ».
- Modèle `App\Support\Audit\LigneAudit` : `save()` sur une ligne existante et `delete()` lèvent
  `LigneAuditImmuable`. Lecture filtrée par organisation (trait `AppartientAOrganisation`).
- Écriture uniquement via `JournalAudit::enregistrer()` ; `password`, `remember_token`, `code_hache`,
  `token` sont remplacés par « [masqué] ».
- Journalisés aujourd'hui : création / modification / suppression d'organisation, agence, utilisateur
  (trait `EstAudite`), connexion, échec de connexion, mauvais code OTP, déconnexion, changement de rôles,
  création et révocation de jeton mobile. Les exports et validations s'ajouteront avec leurs phases.

## Conséquences

- Les opérations financières (Phase 1) suivront le même schéma de triggers pour `operations` et `ecritures`.
- Le compte MySQL de production doit pouvoir créer des triggers lors des migrations (voir ADR 002).
- Purger ou archiver le journal exigera une procédure hors application (export puis rotation de table),
  jamais un `DELETE`.
