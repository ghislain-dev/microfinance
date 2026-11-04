# ADR 024 — Fuseau horaire par organisation

- Date : 2026-11-04
- Statut : accepté (choix du porteur du projet : un fuseau par organisation)

## Contexte

L'application tournait en UTC : entre minuit UTC et minuit local, le « jour » d'une opération, du taux de
change, d'une pénalité, d'un rappel ou d'une clôture était celui de la veille (Kinshasa UTC+1, Lubumbashi UTC+2).

## Décision

- Colonne `organisations.fuseau` (identifiant IANA), **Africa/Kinshasa par défaut**, modifiable par
  l'administrateur (écran Organisation : Kinshasa ou Lubumbashi).
- La base et l'application restent en **UTC** pour les horodatages ; seul le jour et l'affichage changent.
- `App\Support\Temps\Horloge` : jour local de l'organisation (`aujourdhui`), début du jour local en UTC
  (`debutDuJour`), affichage local des horodatages (`local`, `affiche`).
- Jour local utilisé pour : date de valeur par défaut des opérations (calculée par le moteur selon
  l'organisation des comptes), taux de change du jour, ouverture et blocage des comptes d'épargne, intérêts
  et ristournes, décaissement et échéancier, pénalités et rappels (commandes : par organisation), PAR,
  tournée, clôture, tableau de bord.

## Conséquences

- Les commandes planifiées tournent à heure fixe UTC (1 h, 2 h, 3 h, 8 h) : 2 h à 3 h plus tard en heure locale.
- Les tests placent l'horloge en UTC : 23 h UTC est déjà le lendemain à Kinshasa.
