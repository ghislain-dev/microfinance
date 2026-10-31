# ADR 007 — Interface web Bootstrap et en-têtes de sécurité

- Date : 2026-10-31
- Statut : accepté

## Contexte

CLAUDE.md §1 : web en Blade + Bootstrap 5. `references/securite.md` : CSP, `X-Frame-Options: DENY`,
`nosniff`, `Referrer-Policy: same-origin`, HSTS. Les utilisateurs ont parfois une connexion lente.

## Décision

- Bootstrap 5.3 installé par npm et compilé par Vite (Tailwind du squelette retiré). **Aucun CDN** ni
  police externe : CSP stricte possible, pages utilisables sur connexion lente ou coupée.
- Middleware global `EnTetesSecurite` (web et API) : CSP `default-src 'self'`, `object-src 'none'`,
  `frame-ancestors 'none'`, `form-action 'self'`, sans `unsafe-inline` ni `unsafe-eval` hors poste de
  développement (où le serveur Vite et les pages d'erreur détaillées l'exigent). HSTS en production.
- Menu construit par `App\Http\Navigation\MenuPrincipal` selon les permissions ; les entrées dont la page
  n'existe pas encore (Agences, Utilisateurs, Journal d'audit) sont visibles mais désactivées (« bientôt »).
- Utilisateur et menu partagés avec toutes les vues, relations chargées d'avance (mode strict : pas de N+1).

## Conséquences

- Aucun script ou style en ligne dans les vues : tout passe par `resources/js` et `resources/css`.
- Les pages Agences, Utilisateurs et Journal d'audit sont à construire (pas encore planifiées) ;
  les Policies et permissions sont prêtes.
