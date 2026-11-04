package com.microfinance.membre.donnees

import android.content.Context
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.microfinance.membre.BuildConfig
import com.microfinance.reseau.ConfigurationApi
import com.microfinance.securite.Chiffrement
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.runBlocking
import javax.inject.Inject
import javax.inject.Singleton

private val Context.etat by preferencesDataStore("etat_membre")

@Singleton
class EtatMembre @Inject constructor(
    @ApplicationContext private val contexte: Context,
    private val chiffrement: Chiffrement,
) : ConfigurationApi {
    override val urlDeBase: String = BuildConfig.API_URL

    @Volatile private var jetonEnMemoire: String? = null

    override fun jeton(): String? = jetonEnMemoire ?: runBlocking { contexte.etat.data.first()[JETON]?.let(chiffrement::dechiffrer) }?.also { jetonEnMemoire = it }

    val connecte: Flow<Boolean> = contexte.etat.data.map { it[JETON] != null }

    suspend fun enregistrer(jeton: String, organisation: String, numero: String) {
        jetonEnMemoire = jeton
        contexte.etat.edit { it[JETON] = chiffrement.chiffrer(jeton); it[ORGANISATION] = organisation; it[NUMERO] = numero }
    }

    suspend fun identifiants(): Pair<String, String> = contexte.etat.data.first().let { (it[ORGANISATION] ?: "") to (it[NUMERO] ?: "") }

    suspend fun oublier() {
        jetonEnMemoire = null
        contexte.etat.edit { it.remove(JETON) }
    }

    private companion object {
        val JETON = stringPreferencesKey("jeton")
        val ORGANISATION = stringPreferencesKey("organisation")
        val NUMERO = stringPreferencesKey("numero")
    }
}
