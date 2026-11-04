package com.microfinance.collecteur.domaine

import com.microfinance.modele.Montant
import com.microfinance.reseau.ParametresCollecte
import java.time.Duration
import java.time.Instant

/** Opération saisie au téléphone et pas encore confirmée par le serveur. */
data class OperationLocale(
    val type: TypeOperation,
    val compteEpargneId: Long?,
    val montant: Montant,
    val fondsSocial: Montant? = null,
)

enum class TypeOperation(val api: String, val libelle: String, val entreeEspeces: Boolean) {
    Depot("depot", "Dépôt", true),
    Retrait("retrait", "Retrait", false),
    Cotisation("cotisation", "Cotisation de tontine", true),
    Remboursement("remboursement", "Remboursement de prêt", true),
    ;

    companion object {
        fun depuisApi(code: String): TypeOperation? = entries.firstOrNull { it.api == code }
    }
}

sealed interface Refus {
    val message: String

    data object SansParametres : Refus {
        override val message = "Le gérant n'a pas encore fixé les paramètres de collecte : synchronisez plus tard."
    }

    data class DelaiDepasse(val heures: Int) : Refus {
        override val message = "Plus de $heures h sans synchronisation : synchronisez avant toute nouvelle saisie."
    }

    data class PlafondEspeces(val plafond: Montant) : Refus {
        override val message = "Vous détenez déjà ${plafond.formate()} ou plus : versez vos espèces au guichet."
    }

    data class PlafondRetrait(val plafond: Montant) : Refus {
        override val message = "Retrait hors-ligne limité à ${plafond.formate()} : au-delà, le membre passe au guichet."
    }

    data class SoldeInsuffisant(val disponible: Montant) : Refus {
        override val message = "Disponible connu : ${disponible.formate()} (retraits déjà saisis déduits)."
    }

    data class EspecesInsuffisantes(val especes: Montant) : Refus {
        override val message = "Vous n'avez que ${especes.formate()} en espèces pour payer ce retrait."
    }
}

/**
 * Règles hors-ligne du collecteur (ADR 022). Le serveur revérifie tout à la synchronisation ; ces limites
 * évitent de remettre de l'argent que le serveur refuserait ensuite.
 */
object LimitesCollecte {
    /** Espèces en poche = détenues au dernier contact + entrées en attente − retraits en attente. */
    fun especes(devise: String, detenues: Map<String, Montant>, enAttente: List<OperationLocale>): Montant =
        enAttente.filter { it.montant.devise == devise }.fold(detenues[devise] ?: Montant.zero(devise)) { total, op ->
            val somme = op.montant + (op.fondsSocial ?: Montant.zero(devise))
            if (op.type.entreeEspeces) total + somme else total - somme
        }

    fun peutSaisir(
        maintenant: Instant,
        derniereSynchro: Instant?,
        parametres: ParametresCollecte?,
        detenues: Map<String, Montant>,
        enAttente: List<OperationLocale>,
        devise: String,
    ): Refus? {
        if (parametres == null || derniereSynchro == null) return Refus.SansParametres
        if (Duration.between(derniereSynchro, maintenant) > Duration.ofHours(parametres.delaiMaxHeures.toLong())) {
            return Refus.DelaiDepasse(parametres.delaiMaxHeures)
        }
        val plafond = parametres.plafonds[devise]?.plafondEspeces ?: return Refus.SansParametres
        return if (especes(devise, detenues, enAttente) >= plafond) Refus.PlafondEspeces(plafond) else null
    }

    /**
     * @param disponibleConnu disponible du compte à la dernière synchronisation (solde − minimum − garanties)
     */
    fun retrait(
        montant: Montant,
        compteEpargneId: Long,
        disponibleConnu: Montant,
        parametres: ParametresCollecte,
        detenues: Map<String, Montant>,
        enAttente: List<OperationLocale>,
    ): Refus? {
        val plafonds = parametres.plafonds[montant.devise] ?: return Refus.SansParametres
        val limite = plafonds.seuilValidation?.let { if (it < plafonds.plafondRetrait) it else plafonds.plafondRetrait } ?: plafonds.plafondRetrait
        if (montant > limite) return Refus.PlafondRetrait(limite)

        val dejaRetire = enAttente.filter { it.type == TypeOperation.Retrait && it.compteEpargneId == compteEpargneId }
            .fold(Montant.zero(montant.devise)) { t, op -> t + op.montant }
        val disponible = disponibleConnu - dejaRetire
        if (montant > disponible) return Refus.SoldeInsuffisant(disponible)

        val enPoche = especes(montant.devise, detenues, enAttente)
        return if (montant > enPoche) Refus.EspecesInsuffisantes(enPoche) else null
    }
}
