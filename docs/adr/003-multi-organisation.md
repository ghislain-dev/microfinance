# ADR 003 — Isolement des données par organisation

- Date : 2026-10-31
- Statut : accepté

## Contexte

Une seule application sert plusieurs organisations (CLAUDE.md §1). `organisation_id` ne doit jamais
venir de la requête (§4.8) et un utilisateur de A doit recevoir 404 sur une ressource de B (skill §9).
Un utilisateur peut être rattaché à plusieurs agences de son organisation.

## Décision

Trait `App\Support\MultiOrganisation\AppartientAOrganisation`, appliqué à tout modèle métier
(test qui le vérifie pour `app/Domain/*/Models`) et à `User` :

- **Lecture** : scope global sur l'organisation de l'utilisateur connecté. Le test utilise
  `Auth::hasUser()` et non `auth()->user()` pour ne pas boucler pendant le chargement de `User`.
  Sans utilisateur (console, commandes planifiées), pas de filtre : boucler explicitement par organisation.
- **Création** : `organisation_id` est renseigné depuis l'utilisateur. Le skill propose `??=` ; nous
  **levons `OrganisationIncoherente`** si une autre valeur a été mise, plutôt que de la garder en silence.
- **Modification** : changer `organisation_id` est interdit.
- `organisation_id` n'est jamais dans `$fillable` ; le mode strict d'Eloquent lève une exception en cas
  d'assignation de masse.
- **En base** : `agences` et `users` ont `UNIQUE(id, organisation_id)` ; le pivot `agence_user` porte
  `organisation_id` et deux clés étrangères composées, ce qui rend impossible un rattachement entre
  organisations, même par SQL direct.
- L'e-mail de connexion est unique sur toute l'application ; le code d'agence est unique par organisation.

## Conséquences

- Les tests d'accès croisé utilisent `Tests\Support\DeuxOrganisations` (objet typé, lisible par PHPStan).
- Tout futur pivot entre deux modèles métier suit le même schéma (organisation_id + FK composées).
- La question d'un super-administrateur de plateforme (vue sur toutes les organisations) reste ouverte.
