package com.microfinance.collecteur.donnees

import android.os.Build
import com.microfinance.reseau.ApiMicrofinance
import com.microfinance.reseau.AppelApi
import com.microfinance.reseau.DemandeJetonPersonnel
import com.microfinance.reseau.ErreurApi
import com.microfinance.reseau.Resultat
import javax.inject.Inject
import javax.inject.Singleton

sealed interface EtapeConnexion {
    data object CodeEnvoye : EtapeConnexion

    data object Connecte : EtapeConnexion
}

/** Connexion du collecteur : mot de passe, puis code OTP, depuis un téléphone autorisé par le gérant. */
@Singleton
class DepotSession @Inject constructor(
    private val api: ApiMicrofinance,
    private val appel: AppelApi,
    private val etat: EtatLocal,
) {
    suspend fun connecter(email: String, motDePasse: String, code: String?): Resultat<EtapeConnexion> {
        val demande = DemandeJetonPersonnel(email.trim(), motDePasse, "${Build.MANUFACTURER} ${Build.MODEL}".take(100), etat.identifiantAppareil(), code?.trim())
        return when (val r = appel.executer { api.jetonPersonnel(demande) }) {
            is Resultat.Echec -> r
            is Resultat.Succes -> {
                val jeton = r.valeur.body()?.jeton
                if (r.valeur.code() == 202 || jeton == null) Resultat.Succes(EtapeConnexion.CodeEnvoye) else {
                    etat.enregistrerJeton(jeton, email.trim())
                    Resultat.Succes(EtapeConnexion.Connecte)
                }
            }
        }
    }

    suspend fun deconnecter() {
        appel.executer { api.deconnexion() }
        etat.oublierJeton()
    }

    fun message(erreur: ErreurApi): String = when {
        erreur is ErreurApi.Interdit && erreur.code == "appareil_en_attente" ->
            "Ce téléphone attend l'autorisation du gérant. Demandez-lui de l'autoriser dans l'écran Collecte, puis réessayez."
        erreur is ErreurApi.Interdit && erreur.code == "appareil_revoque" -> "Ce téléphone a été révoqué par le gérant."
        else -> erreur.message
    }
}
