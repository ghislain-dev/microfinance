package com.microfinance.reseau

import kotlinx.coroutines.CancellationException
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonPrimitive
import retrofit2.Response
import java.io.IOException
import javax.inject.Inject
import javax.inject.Singleton

/** Exécute un appel et traduit la réponse en [Resultat] (statut HTTP, corps d'erreur JSON du serveur). */
@Singleton
class AppelApi @Inject constructor(private val json: Json) {
    suspend fun <T> executer(appel: suspend () -> Response<T>): Resultat<Response<T>> = try {
        val reponse = appel()
        if (reponse.isSuccessful) Resultat.Succes(reponse) else Resultat.Echec(erreur(reponse))
    } catch (e: CancellationException) {
        throw e // jamais avalée : l'annulation des coroutines doit remonter
    } catch (_: IOException) {
        Resultat.Echec(ErreurApi.Reseau)
    }

    suspend fun <T : Any> corps(appel: suspend () -> Response<T>): Resultat<T> = when (val r = executer(appel)) {
        is Resultat.Echec -> r
        is Resultat.Succes -> r.valeur.body()?.let { Resultat.Succes(it) } ?: Resultat.Echec(ErreurApi.Serveur(r.valeur.code()))
    }

    private fun erreur(reponse: Response<*>): ErreurApi {
        val corps = runCatching { reponse.errorBody()?.string()?.let { json.decodeFromString<ErreurJson>(it) } }.getOrNull()
        return when (reponse.code()) {
            401 -> ErreurApi.NonAutorise
            403 -> ErreurApi.Interdit(corps?.code, corps?.message ?: "Accès refusé.")
            422 -> {
                val champs = corps?.errors.orEmpty().mapValues { (_, v) ->
                    ((v as? JsonArray)?.firstOrNull() as? JsonPrimitive)?.content ?: ""
                }
                ErreurApi.Validation(champs, champs.values.firstOrNull() ?: corps?.message ?: "Données refusées.")
            }
            429 -> ErreurApi.TropDeTentatives
            else -> ErreurApi.Serveur(reponse.code())
        }
    }
}
