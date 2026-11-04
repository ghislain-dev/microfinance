package com.microfinance.membre.donnees

import android.os.Build
import com.microfinance.reseau.ApiMicrofinance
import com.microfinance.reseau.AppelApi
import com.microfinance.reseau.ChangementMotDePasse
import com.microfinance.reseau.CompteMembre
import com.microfinance.reseau.DemandeJetonMembre
import com.microfinance.reseau.ErreurApi
import com.microfinance.reseau.JetonPushDto
import com.microfinance.reseau.Mouvement
import com.microfinance.reseau.NotificationMembre
import com.microfinance.reseau.PeriodeReleve
import com.microfinance.reseau.PretMembre
import com.microfinance.reseau.Resultat
import com.microfinance.reseau.TontineMembre
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.withContext
import java.io.File
import java.time.LocalDate
import javax.inject.Inject
import javax.inject.Singleton

/** Données du membre connecté ; le serveur est la seule source des soldes. */
@Singleton
class DepotMembre @Inject constructor(
    private val api: ApiMicrofinance,
    private val appel: AppelApi,
    private val etat: EtatMembre,
) {
    /** @return vrai si le mot de passe provisoire doit être changé */
    suspend fun connecter(organisation: String, numero: String, motDePasse: String): Resultat<Boolean> {
        val demande = DemandeJetonMembre(organisation.trim().uppercase(), numero.trim().uppercase(), motDePasse, "${Build.MANUFACTURER} ${Build.MODEL}".take(100))
        return when (val r = appel.corps { api.jetonMembre(demande) }) {
            is Resultat.Echec -> r
            is Resultat.Succes -> {
                val jeton = r.valeur.jeton ?: return Resultat.Echec(ErreurApi.Serveur(500))
                etat.enregistrer(jeton, demande.organisation, demande.numero)
                Resultat.Succes(r.valeur.doitChangerMotDePasse)
            }
        }
    }

    suspend fun changerMotDePasse(actuel: String, nouveau: String, confirmation: String): Resultat<Unit> =
        when (val r = appel.executer { api.changerMotDePasse(ChangementMotDePasse(actuel, nouveau, confirmation)) }) {
            is Resultat.Echec -> r
            is Resultat.Succes -> Resultat.Succes(Unit)
        }

    suspend fun comptes(): Resultat<List<CompteMembre>> = liste { api.comptes() }

    suspend fun mouvements(compte: Long): Resultat<List<Mouvement>> = liste { api.mouvements(compte) }

    suspend fun prets(): Resultat<List<PretMembre>> = liste { api.prets() }

    suspend fun tontines(): Resultat<List<TontineMembre>> = liste { api.tontines() }

    suspend fun notifications(): Resultat<List<NotificationMembre>> = when (val r = appel.corps { api.notifications() }) {
        is Resultat.Echec -> r
        is Resultat.Succes -> Resultat.Succes(r.valeur.data)
    }

    /**
     * Demande le relevé, attend sa préparation par le serveur (file d'attente), puis l'enregistre dans le cache.
     */
    suspend fun releve(compte: Long, du: LocalDate, au: LocalDate, dossier: File): Resultat<File> {
        val demande = when (val r = appel.corps { api.demanderReleve(compte, PeriodeReleve(du.toString(), au.toString())) }) {
            is Resultat.Echec -> return r
            is Resultat.Succes -> r.valeur
        }
        repeat(30) {
            when (val r = appel.executer { api.releve(demande.id) }) {
                is Resultat.Echec -> return r
                is Resultat.Succes -> if (r.valeur.code() == 200) {
                    val fichier = File(dossier, "releve-${demande.id}.pdf")
                    withContext(Dispatchers.IO) { r.valeur.body()?.byteStream()?.use { e -> fichier.outputStream().use { e.copyTo(it) } } }
                    return Resultat.Succes(fichier)
                }
            }
            delay(2_000)
        }
        return Resultat.Echec(ErreurApi.Serveur(504))
    }

    suspend fun enregistrerJetonPush(jeton: String) {
        if (etat.jeton() != null) appel.executer { api.enregistrerJetonPush(JetonPushDto(jeton)) }
    }

    suspend fun deconnecter() {
        appel.executer { api.deconnexion() }
        etat.oublier()
    }

    private suspend fun <T> liste(appelListe: suspend () -> retrofit2.Response<com.microfinance.reseau.Liste<T>>): Resultat<List<T>> =
        when (val r = appel.corps(appelListe)) {
            is Resultat.Echec -> r
            is Resultat.Succes -> Resultat.Succes(r.valeur.data)
        }
}
