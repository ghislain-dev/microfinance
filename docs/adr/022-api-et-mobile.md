# ADR 022 — API v1, synchronisation et applications mobiles

- Date : 2026-11-02
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- **Deux applications Android natives** (Kotlin, Jetpack Compose, Room/SQLite) : « Membre » (consultation)
  et « Collecteur » (saisie hors-ligne).
- **Membres** : identifiants remis au guichet par le **gérant ou le caissier** depuis la fiche du membre
  (identifiant = numéro de membre, mot de passe provisoire montré une seule fois, à changer à la première
  connexion). Notifications **push** dans l'application (Firebase).
- **Collecteurs** : saisissent hors-ligne **dépôts, retraits, cotisations de tontine et remboursements
  de prêt**, pour les **membres que le gérant leur affecte** (leur tournée) seulement.
  - Retrait hors-ligne : au plus le **solde connu à la dernière synchronisation moins les retraits déjà
    saisis**, et au plus un **plafond par retrait** fixé par l'organisation ; jamais au-delà du **seuil de
    validation 4 yeux** (au-delà : le membre passe au guichet).
  - Cotisation de tontine : enregistrée pour la **prochaine séance prévue** du cycle ; la feuille de séance
    l'affiche déjà payée (pas de double encaissement).
  - Espèces : chaque collecteur a **sa propre caisse** ; il **verse** au guichet (billetage compté par le
    caissier) ; écart traité **comme une clôture de caisse** : « écart à justifier », validation du gérant,
    manquant à la charge du collecteur, excédent en produit.
  - Reçu imprimé sur place sur **imprimante thermique Bluetooth** (58 mm).
  - Sécurité : **téléphone autorisé par le gérant** (révocable), **code PIN** local, **délai maximal sans
    synchronisation** et **plafond d'espèces détenues** fixés par l'organisation : au-delà, plus de saisie.

## Décisions techniques

- API `/api/v1`, réponses JSON (`Resources`), erreurs sans trace technique, `throttle`. Les listes
  acceptent `?depuis=<horodatage ISO 8601>` et ne renvoient que les changements.
- Jetons Sanctum à capacité : `membre` (lecture de ses propres données) ou `collecteur` (tournée et
  synchronisation). Le personnel garde l'OTP (ADR 005) ; le membre se connecte avec code de
  l'organisation + numéro + mot de passe.
- Synchronisation (skill §8) : `POST /api/v1/sync/lots`, une clé UUID par opération générée au téléphone,
  réponse par opération `cree | deja_traite | rejete`, `saisi_le` conservé, date de valeur fixée par le serveur.
