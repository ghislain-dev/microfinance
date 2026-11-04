package com.microfinance.reseau

import com.microfinance.modele.Montant
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonObject

@Serializable
data class DemandeJetonPersonnel(
    val email: String,
    val password: String,
    val appareil: String,
    @SerialName("appareil_id") val appareilId: String? = null,
    val code: String? = null,
)

@Serializable
data class DemandeJetonMembre(val organisation: String, val numero: String, val password: String, val appareil: String)

@Serializable
data class ReponseJeton(
    val jeton: String? = null,
    @SerialName("expire_le") val expireLe: String? = null,
    val statut: String? = null,
    @SerialName("doit_changer_mot_de_passe") val doitChangerMotDePasse: Boolean = false,
)

@Serializable
data class ChangementMotDePasse(
    val actuel: String,
    val nouveau: String,
    @SerialName("nouveau_confirmation") val confirmation: String,
)

@Serializable
data class Liste<T>(val data: List<T>)

// ---- Espace membre ----

@Serializable
data class CompteMembre(
    val id: Long,
    val numero: String,
    val produit: String,
    val devise: String,
    val solde: Montant,
    val bloque: Montant,
    val disponible: Montant,
    @SerialName("bloque_jusqu_au") val bloqueJusquAu: String? = null,
)

@Serializable
data class Mouvement(val id: Long, val type: String, val libelle: String, val montant: Montant, val frais: Montant, val recu: String? = null, val le: String)

@Serializable
data class EcheanceMembre(
    val rang: Int,
    val date: String,
    val capital: Montant,
    val interet: Montant,
    val penalite: Montant,
    val paye: Montant,
    @SerialName("reste_du") val resteDu: Montant,
    val payee: Boolean,
)

@Serializable
data class PretMembre(
    val id: Long,
    val numero: String,
    val statut: String,
    @SerialName("statut_libelle") val statutLibelle: String,
    val montant: Montant,
    @SerialName("taux_mensuel") val tauxMensuel: String,
    @SerialName("date_decaissement") val dateDecaissement: String? = null,
    @SerialName("reste_du") val resteDu: Montant,
    val echeances: List<EcheanceMembre>,
)

@Serializable
data class SeanceRef(val rang: Int, val date: String? = null)

@Serializable
data class GroupeRef(val code: String, val nom: String, val type: String)

@Serializable
data class TontineMembre(
    @SerialName("cycle_id") val cycleId: Long,
    val groupe: GroupeRef,
    val numero: Int,
    val periodicite: String,
    val cotisation: Montant,
    @SerialName("fonds_social") val fondsSocial: Montant,
    @SerialName("prochaine_seance") val prochaineSeance: SeanceRef? = null,
    @SerialName("mon_tour") val monTour: SeanceRef? = null,
    val dette: Montant,
)

@Serializable
data class NotificationMembre(val id: Long, val titre: String, val corps: String, val le: String? = null)

@Serializable
data class PageNotifications(val data: List<NotificationMembre>, val suivant: String? = null)

@Serializable
data class JetonPushDto(val jeton: String, val plateforme: String = "android")

// ---- Collecte ----

@Serializable
data class Tournee(
    @SerialName("serveur_le") val serveurLe: String,
    val parametres: ParametresCollecte? = null,
    @SerialName("especes_detenues") val especesDetenues: Map<String, Montant> = emptyMap(),
    val membres: List<MembreTournee> = emptyList(),
    @SerialName("membres_retires") val membresRetires: List<Long> = emptyList(),
)

@Serializable
data class ParametresCollecte(
    @SerialName("delai_max_heures") val delaiMaxHeures: Int,
    val plafonds: Map<String, PlafondsDevise> = emptyMap(),
)

@Serializable
data class PlafondsDevise(
    @SerialName("plafond_retrait") val plafondRetrait: Montant,
    @SerialName("plafond_especes") val plafondEspeces: Montant,
    @SerialName("seuil_validation") val seuilValidation: Montant? = null,
)

@Serializable
data class MembreTournee(
    val id: Long,
    val numero: String,
    val nom: String,
    val telephone: String? = null,
    val statut: String,
    val comptes: List<CompteTournee> = emptyList(),
    val tontines: List<TontineTournee> = emptyList(),
    val prets: List<PretTournee> = emptyList(),
)

@Serializable
data class CompteTournee(val id: Long, val numero: String, val devise: String, val bloque: Boolean, val solde: Montant, val disponible: Montant)

@Serializable
data class SeanceTournee(val id: Long, val rang: Int, val date: String, @SerialName("deja_paye") val dejaPaye: Boolean)

@Serializable
data class TontineTournee(
    @SerialName("cycle_id") val cycleId: Long,
    val groupe: String,
    val type: String,
    val devise: String,
    @SerialName("prochaine_seance") val prochaineSeance: SeanceTournee? = null,
    val cotisation: Montant? = null,
    @SerialName("prix_part") val prixPart: Montant? = null,
    @SerialName("parts_min") val partsMin: Int? = null,
    @SerialName("parts_max") val partsMax: Int? = null,
    @SerialName("fonds_social") val fondsSocial: Montant,
    val dette: Montant,
)

@Serializable
data class PretTournee(
    val id: Long,
    val numero: String,
    val devise: String,
    @SerialName("reste_du") val resteDu: Montant,
    @SerialName("du_a_ce_jour") val duACeJour: Montant,
)

@Serializable
data class OperationCollecteDto(
    val cle: String,
    val type: String,
    @SerialName("membre_id") val membreId: Long,
    @SerialName("compte_epargne_id") val compteEpargneId: Long? = null,
    @SerialName("pret_id") val pretId: Long? = null,
    @SerialName("cycle_id") val cycleId: Long? = null,
    @SerialName("montant_centimes") val montantCentimes: Long? = null,
    @SerialName("fonds_social_centimes") val fondsSocialCentimes: Long? = null,
    val parts: Int? = null,
    @SerialName("saisi_le") val saisiLe: String,
)

@Serializable
data class LotSync(@SerialName("lot_uuid") val lotUuid: String, val operations: List<OperationCollecteDto>)

@Serializable
data class ResultatOperation(
    val cle: String,
    val statut: String,
    val raison: String? = null,
    @SerialName("operation_id") val operationId: Long? = null,
    val recu: String? = null,
)

@Serializable
data class ReponseLot(@SerialName("lot_uuid") val lotUuid: String, val resultats: List<ResultatOperation>)

@Serializable
data class ErreurJson(val message: String? = null, val code: String? = null, val errors: JsonObject? = null)
