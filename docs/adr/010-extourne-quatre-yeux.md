# ADR 010 — Extourne avec validation 4 yeux

- Date : 2026-10-31
- Statut : accepté (validateurs choisis par le porteur du projet)

## Contexte

CLAUDE.md §4.3 : une correction se fait par extourne liée à l'opération d'origine, avec motif et
validation ; §4.7 : jamais par l'auteur de l'opération. Le skill autorisait un UPDATE de
`operations.valide_par_id` ; CLAUDE.md impose l'insertion seule stricte.

## Décision

- `MoteurFinancier::extourner(origine, motif, validateur)` crée une **nouvelle** opération de type
  `extourne`, aux écritures inversées, datée du jour, avec `extourne_de_id`, `motif` et
  `valide_par_id` renseignés **à l'insertion** : l'insertion seule reste stricte, sans exception.
- Validateur : permission `operations.extourner`, donnée au **gérant et au comptable** (pas à l'admin),
  dans la même organisation, et **jamais l'auteur** de l'opération d'origine (`OperationPolicy`).
- Une seule extourne par opération (`UNIQUE(extourne_de_id)` + contrôle), clé d'idempotence
  `extourne:{id}`, motif obligatoire, ligne d'audit `operations.extourne`.
- L'extourne passe par `executer()` : elle respecte la période ouverte (une opération d'une période
  clôturée s'extourne aujourd'hui) et le découvert (on ne rend pas une épargne négative).

## Conséquences

- La validation est en une étape : le validateur passe l'extourne lui-même. Un circuit
  « demande par le caissier → validation par le gérant » pourra être ajouté si le cahier des charges
  le demande (gros décaissements, écarts de caisse en Phase 2).
