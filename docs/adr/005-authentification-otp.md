# ADR 005 — Authentification web, OTP et jetons mobiles

- Date : 2026-10-31
- Statut : accepté

## Contexte

`references/securite.md` exige un OTP pour les rôles sensibles ; il a été décidé de l'exiger pour
**tout le personnel**. Pas de budget SMS pour l'instant : le code part **par e-mail**, le canal SMS doit
exister pour plus tard. Paramètres validés : 6 chiffres, 10 minutes, 3 essais, renvoi 1 fois par minute.

## Décision

- **Connexion différée** : après le mot de passe, la session ne contient que l'id de l'utilisateur
  « en attente » ; `Auth::login()` n'est appelé qu'après un code valide. Aucune route protégée ne peut
  donc être atteinte sans OTP, même si un middleware est oublié.
- Le **membre** n'a pas d'accès web (il lui manque `tableau_de_bord.voir`) ; il passera par l'API mobile.
- **API** : `POST /api/v1/auth/jetons` en deux temps (202 « code_requis », puis 201 avec le jeton).
  L'OTP s'applique donc aussi aux jetons mobiles. Jetons Sanctum avec expiration (7 jours,
  `SANCTUM_EXPIRATION`), listés et révocables (`/api/v1/appareils`), refusés si le compte ou
  l'organisation est désactivé.
- Codes stockés en HMAC-SHA256 (clé `APP_KEY`), comparés avec `hash_equals`, un seul code valable à la
  fois, `organisation_id` sur chaque ligne. Notification en file d'attente **chiffrée** (`ShouldBeEncrypted`).
- **SMS** : interface `App\Support\Sms\EnvoyeurSms`, pilote `JournalSms` (n'envoie rien, masque le
  contenu hors poste de dev), canal de notification `sms`. Activation : `OTP_CANAL=sms` + un pilote réel.
- `throttle` : 5/min sur la connexion (e-mail + IP) et sur l'OTP (utilisateur en attente + IP),
  60/min sur l'API. Message d'échec identique que l'e-mail existe ou non ; hachage calculé dans tous les cas.

## Conséquences

- En production, `MAIL_MAILER` doit être un vrai serveur d'envoi et un worker de file (`queue:work`)
  doit tourner, sinon aucun code n'arrive.
- En développement, `MAIL_MAILER=log` écrit le code dans `storage/logs` (jamais commité).
- La durée de 7 jours des jetons est une valeur technique par défaut, à confirmer.
