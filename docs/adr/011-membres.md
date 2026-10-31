# ADR 011 — Membres : numérotation, doublons, statuts

- Date : 2026-10-31
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- Champs : nom, sexe, date de naissance, téléphone, pièce (type + numéro), agence **obligatoires** ;
  postnom, prénom, adresse facultatifs.
- Numéro : séquentiel **par agence**, `CODEAGENCE-000045`, attribué automatiquement, jamais modifié.
- Doublons dans une organisation : **même pièce = refus** (par la base) ; **même téléphone = avertissement**
  à confirmer (téléphone partagé en famille).
- Statuts : actif, suspendu, démissionnaire, exclu, décédé.
- Droits : `membres.voir` pour tout le personnel ; `membres.gerer` (inscrire, modifier, changer le
  statut) pour admin, gérant, caissier.

## Décisions techniques

- Numéro de pièce chiffré (`encrypted`) ; une **empreinte HMAC** (type + numéro, casse, espaces et
  ponctuation ignorés) porte l'unicité `UNIQUE(organisation_id, piece_empreinte)`. Pièce et empreinte
  sont masquées dans le journal d'audit.
- Téléphones normalisés en format international (`0812345678` → `+243812345678`).
- Numérotation sous verrou de l'agence + `UNIQUE(agence_id, numero_sequence)` ; vérifié par 20
  inscriptions simultanées sur MySQL (sans verrou, le test échoue).
- Pas de suppression de membre (historique financier) : on change son statut. Le numéro et l'agence
  d'inscription ne changent pas après l'inscription.
- Helper `AutoriserDansOrganisation` : évalue les droits dans l'organisation de l'acteur (rôles spatie
  « teams »), utilisé par les membres, l'extourne et l'attribution de rôles.
