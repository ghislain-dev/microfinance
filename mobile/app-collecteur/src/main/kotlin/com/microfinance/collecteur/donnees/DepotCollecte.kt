package com.microfinance.collecteur.donnees

import com.microfinance.collecteur.domaine.LimitesCollecte
import com.microfinance.collecteur.domaine.OperationLocale
import com.microfinance.collecteur.domaine.Refus
import com.microfinance.collecteur.domaine.TypeOperation
import com.microfinance.modele.Montant
import com.microfinance.reseau.ApiMicrofinance
import com.microfinance.reseau.AppelApi
import com.microfinance.reseau.ErreurApi
import com.microfinance.reseau.LotSync
import com.microfinance.reseau.MembreTournee
import com.microfinance.reseau.OperationCollecteDto
import com.microfinance.reseau.Resultat
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.serialization.json.Json
import java.time.Clock
import java.time.Instant
import java.util.UUID
import javax.inject.Inject
import javax.inject.Singleton

data class Saisie(
    val type: TypeOperation,
    val membre: MembreTournee,
    val montant: Montant,
    val compteEpargneId: Long? = null,
    val pretId: Long? = null,
    val cycleId: Long? = null,
    val fondsSocial: Montant? = null,
    val parts: Int? = null,
    val libelle: String,
)

data class BilanSynchro(val acceptees: Int, val refusees: Int, val membres: Int)

sealed interface ResultatSaisie {
    data class Enregistree(val operation: OperationEntite) : ResultatSaisie

    data class Refusee(val refus: Refus) : ResultatSaisie
}

/**
 * Tournée gardée hors-ligne et opérations saisies ; la synchronisation envoie d'abord les opérations
 * (dans l'ordre de saisie), puis met la tournée à jour depuis le dernier passage.
 */
@Singleton
class DepotCollecte @Inject constructor(
    private val base: BaseLocale,
    private val etat: EtatLocal,
    private val api: ApiMicrofinance,
    private val appel: AppelApi,
    private val json: Json,
    private val horloge: Clock,
) {
    private val synchro = Mutex()

    val membres: Flow<List<MembreTournee>> = base.membres().tous().map { liste -> liste.map(::decoder) }

    fun membre(id: Long): Flow<MembreTournee?> = base.membres().parId(id).map { it?.let(::decoder) }

    val enAttente: Flow<List<OperationEntite>> = base.operations().enAttenteFlux()

    val recentes: Flow<List<OperationEntite>> = base.operations().recentes()

    suspend fun operation(cle: String): OperationEntite? = base.operations().parCle(cle)

    suspend fun saisir(saisie: Saisie): ResultatSaisie {
        val instantane = etat.instantaneActuel()
        val attente = base.operations().enAttente().map(::locale)
        LimitesCollecte.peutSaisir(Instant.now(horloge), instantane.derniereSynchro, instantane.parametres, instantane.especes, attente, saisie.montant.devise)
            ?.let { return ResultatSaisie.Refusee(it) }
        if (saisie.type == TypeOperation.Retrait) {
            val compte = saisie.membre.comptes.first { it.id == saisie.compteEpargneId }
            LimitesCollecte.retrait(saisie.montant, compte.id, compte.disponible, instantane.parametres!!, instantane.especes, attente)
                ?.let { return ResultatSaisie.Refusee(it) }
        }

        val operation = OperationEntite(
            cle = UUID.randomUUID().toString(),
            type = saisie.type.api,
            membreId = saisie.membre.id,
            membreNumero = saisie.membre.numero,
            membreNom = saisie.membre.nom,
            compteEpargneId = saisie.compteEpargneId,
            pretId = saisie.pretId,
            cycleId = saisie.cycleId,
            devise = saisie.montant.devise,
            montantCentimes = saisie.montant.centimes,
            fondsSocialCentimes = saisie.fondsSocial?.centimes,
            parts = saisie.parts,
            libelle = saisie.libelle,
            saisiLe = Instant.now(horloge).toString(),
            numeroProvisoire = etat.prochainNumeroProvisoire(),
        )
        base.operations().ajouter(operation)
        return ResultatSaisie.Enregistree(operation)
    }

    suspend fun synchroniser(): Resultat<BilanSynchro> = synchro.withLock {
        var acceptees = 0
        var refusees = 0
        for (paquet in base.operations().enAttente().chunked(200)) {
            val lot = LotSync(UUID.randomUUID().toString(), paquet.map(::dto))
            when (val r = appel.corps { api.synchroniser(lot) }) {
                is Resultat.Echec -> return r
                is Resultat.Succes -> r.valeur.resultats.forEach { res ->
                    val accepte = res.statut == "cree" || res.statut == "deja_traite"
                    if (accepte) acceptees++ else refusees++
                    base.operations().repondre(res.cle, if (accepte) OperationEntite.ACCEPTEE else OperationEntite.REFUSEE, res.raison, res.recu)
                }
            }
        }

        val precedent = etat.instantaneActuel().serveurLe
        val tournee = when (val r = appel.corps { api.tournee(precedent) }) {
            is Resultat.Echec -> return r
            is Resultat.Succes -> r.valeur
        }
        val entites = tournee.membres.map { MembreEntite(it.id, it.numero, it.nom, json.encodeToString(it)) }
        if (precedent == null) base.membres().remplacer(entites) else {
            base.membres().enregistrer(entites)
            if (tournee.membresRetires.isNotEmpty()) base.membres().retirer(tournee.membresRetires)
        }
        etat.enregistrerSynchro(tournee.serveurLe, tournee.parametres, tournee.especesDetenues, Instant.now(horloge))

        Resultat.Succes(BilanSynchro(acceptees, refusees, tournee.membres.size))
    }

    /** Erreur qui impose une nouvelle connexion (jeton révoqué, téléphone retiré par le gérant). */
    fun exigeReconnexion(erreur: ErreurApi): Boolean =
        erreur is ErreurApi.NonAutorise || (erreur is ErreurApi.Interdit && erreur.code in setOf("appareil_non_autorise", "appareil_revoque"))

    private fun decoder(entite: MembreEntite): MembreTournee = json.decodeFromString(entite.donnees)

    private fun locale(op: OperationEntite) = OperationLocale(
        type = TypeOperation.depuisApi(op.type) ?: TypeOperation.Depot,
        compteEpargneId = op.compteEpargneId,
        montant = Montant(op.montantCentimes, op.devise),
        fondsSocial = op.fondsSocialCentimes?.let { Montant(it, op.devise) },
    )

    private fun dto(op: OperationEntite) = OperationCollecteDto(
        cle = op.cle, type = op.type, membreId = op.membreId, compteEpargneId = op.compteEpargneId, pretId = op.pretId,
        cycleId = op.cycleId, montantCentimes = op.montantCentimes, fondsSocialCentimes = op.fondsSocialCentimes,
        parts = op.parts, saisiLe = op.saisiLe,
    )
}
