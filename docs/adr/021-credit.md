# ADR 021 — Crédit : produits, demandes, approbation, échéancier, remboursements

- Date : 2026-11-01
- Statut : accepté (règles fixées par le porteur du projet)

## Règles validées

- **Taux d'intérêt mensuel** (points de base : 300 = 3 % par mois), appliqué au capital restant dû.
  Échéances hebdomadaires ou toutes les deux semaines : taux de la période = taux mensuel × 12 / 52
  (× 12 / 26), calculé en `brick/math`.
- **Périodicité des échéances paramétrable** par produit : chaque semaine, toutes les deux semaines, chaque mois.
- **Méthode choisie à chaque prêt** : linéaire (capital constant) ou dégressif (annuité constante).
- **Aucuns frais de dossier.**
- **Éligibilité** : membre actif ; **un seul prêt à la fois** (bloqué par la base) ; **ancienneté minimale**
  (mois, par produit) ; **plafond = N × épargne** du membre dans la devise du prêt (N par produit), vérifié
  à la demande et au décaissement.
- **Circuit** : l'agent de crédit (ou le gérant) saisit la demande et son analyse ; **un membre du comité**
  approuve ou rejette ; **au-delà d'un seuil** (par organisation et par devise) le **gérant** approuve en plus.
  Personne n'approuve sa propre demande, et le même utilisateur n'approuve pas deux niveaux.
- **Décaissement sur le compte d'épargne** du membre, par le gérant ou le comptable, jamais par l'auteur
  de la demande.
- **Garanties** : épargne bloquée (pourcentage du prêt fixé par produit, exigé disponible au décaissement,
  non retirable jusqu'au remboursement complet puis libéré seul) ; caution d'un autre membre (engagement
  enregistré, sans blocage de son épargne) ; bien matériel (description, sans écriture).
- **Remboursement manuel**, par le caissier ou le gérant : en espèces à la caisse ou par débit de l'épargne.
  Ordre d'imputation : pénalités → intérêts → capital, échéance la plus ancienne d'abord.
- **Remboursement anticipé** autorisé : le membre paie le capital restant **et tous les intérêts prévus**.
- **Pénalité de retard** : pourcentage **par jour** (par produit) sur le **capital impayé** de l'échéance,
  après un **délai de grâce** (jours, par produit), **sans plafond**, constatée chaque nuit.

## Décisions techniques

- Échéancier (skill §6) : intérêt de chaque échéance arrondi au centime en `HALF_EVEN` ; annuité dégressive
  calculée en `BigDecimal` (échelle 10) puis arrondie au centime ; **l'écart d'arrondi du capital va sur
  la dernière échéance** ; Σ capital = montant décaissé, au centime près (testé).
- Première échéance une période après le décaissement ; échéances mensuelles sans débordement de fin de mois
  (31 janvier → 28 février → 31 mars).
- Comptes : un compte « prêt » par prêt (nature `pret`), produits « intérêts sur prêts » et
  « pénalités sur prêts » par devise, pénalités constatées dans un compte « pénalités à recevoir » par prêt
  (skill §4) ; intérêts comptabilisés au remboursement.
- Pénalités : commande quotidienne idempotente, clé `penalite:{echeance_id}:{date}`.
- PAR 30 / PAR 90 = encours des prêts ayant une échéance impayée depuis plus de 30 / 90 jours ÷ encours total.

## À confirmer

- L'ancienneté se compte depuis l'**inscription du membre dans l'application** (pas de date d'adhésion
  saisie à part).
