package com.microfinance.collecteur.donnees

import android.content.Context
import androidx.hilt.work.HiltWorker
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import com.microfinance.reseau.ErreurApi
import com.microfinance.reseau.Resultat
import dagger.assisted.Assisted
import dagger.assisted.AssistedInject
import java.util.concurrent.TimeUnit

/** Synchronisation automatique dès que le réseau est là (toutes les 15 minutes au plus souvent). */
@HiltWorker
class TravailSynchro @AssistedInject constructor(
    @Assisted contexte: Context,
    @Assisted parametres: WorkerParameters,
    private val depot: DepotCollecte,
    private val etat: EtatLocal,
) : CoroutineWorker(contexte, parametres) {
    override suspend fun doWork(): Result {
        if (etat.jeton() == null) return Result.success()
        return when (val r = depot.synchroniser()) {
            is Resultat.Succes -> Result.success()
            is Resultat.Echec -> when {
                depot.exigeReconnexion(r.erreur) -> Result.success() // l'écran demandera de se reconnecter
                r.erreur is ErreurApi.Reseau || r.erreur is ErreurApi.Serveur -> Result.retry()
                else -> Result.failure()
            }
        }
    }

    companion object {
        fun planifier(contexte: Context) {
            val requete = PeriodicWorkRequestBuilder<TravailSynchro>(15, TimeUnit.MINUTES)
                .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 1, TimeUnit.MINUTES)
                .build()
            WorkManager.getInstance(contexte).enqueueUniquePeriodicWork("synchro", ExistingPeriodicWorkPolicy.KEEP, requete)
        }
    }
}
