package com.microfinance.collecteur.donnees

import android.content.Context
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.longPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.microfinance.modele.Montant
import com.microfinance.reseau.ConfigurationApi
import com.microfinance.reseau.ParametresCollecte
import com.microfinance.securite.Chiffrement
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.json.Json
import java.security.MessageDigest
import java.security.SecureRandom
import java.time.Instant
import java.util.UUID
import javax.crypto.SecretKeyFactory
import javax.crypto.spec.PBEKeySpec
import javax.inject.Inject
import javax.inject.Singleton

private val Context.etat by preferencesDataStore("etat_collecteur")

/** Ce que le téléphone retient entre deux synchronisations. */
data class InstantaneCollecte(
    val derniereSynchro: Instant?,
    val serveurLe: String?,
    val parametres: ParametresCollecte?,
    val especes: Map<String, Montant>,
)

@Singleton
class EtatLocal @Inject constructor(
    @ApplicationContext private val contexte: Context,
    private val chiffrement: Chiffrement,
    private val json: Json,
) : ConfigurationApi {
    override val urlDeBase: String = com.microfinance.collecteur.BuildConfig.API_URL

    @Volatile private var jetonEnMemoire: String? = null

    // Lecture synchrone pour l'intercepteur OkHttp (appelé hors du thread principal).
    override fun jeton(): String? = jetonEnMemoire ?: runBlocking { lire(JETON)?.let(chiffrement::dechiffrer) }?.also { jetonEnMemoire = it }

    val connecte: Flow<Boolean> = contexte.etat.data.map { it[JETON] != null }
    val pinDefini: Flow<Boolean> = contexte.etat.data.map { it[PIN] != null }

    suspend fun enregistrerJeton(jeton: String, email: String) {
        jetonEnMemoire = jeton
        contexte.etat.edit { it[JETON] = chiffrement.chiffrer(jeton); it[EMAIL] = email }
    }

    suspend fun email(): String? = lire(EMAIL)

    /** Identifiant du téléphone, créé une fois : c'est lui que le gérant autorise. */
    suspend fun identifiantAppareil(): String = lire(APPAREIL) ?: UUID.randomUUID().toString().also { id -> contexte.etat.edit { it[APPAREIL] = id } }

    suspend fun oublierJeton() {
        jetonEnMemoire = null
        contexte.etat.edit { it.remove(JETON); it.remove(PIN) }
    }

    suspend fun definirPin(pin: String) {
        val sel = ByteArray(16).also { SecureRandom().nextBytes(it) }
        contexte.etat.edit { it[PIN] = "${hex(sel)}:${hex(empreinte(pin, sel))}" }
    }

    suspend fun verifierPin(pin: String): Boolean {
        val (sel, attendu) = lire(PIN)?.split(':')?.takeIf { it.size == 2 } ?: return false
        return MessageDigest.isEqual(hex(empreinte(pin, octets(sel))).toByteArray(), attendu.toByteArray())
    }

    val instantane: Flow<InstantaneCollecte> = contexte.etat.data.map { p ->
        InstantaneCollecte(
            derniereSynchro = p[DERNIERE_SYNCHRO]?.let(Instant::ofEpochMilli),
            serveurLe = p[SERVEUR_LE],
            parametres = p[PARAMETRES]?.let { runCatching { json.decodeFromString<ParametresCollecte>(it) }.getOrNull() },
            especes = p[ESPECES]?.let { runCatching { json.decodeFromString<Map<String, Montant>>(it) }.getOrNull() } ?: emptyMap(),
        )
    }

    suspend fun instantaneActuel(): InstantaneCollecte = instantane.first()

    suspend fun enregistrerSynchro(serveurLe: String, parametres: ParametresCollecte?, especes: Map<String, Montant>, maintenant: Instant) {
        contexte.etat.edit {
            it[SERVEUR_LE] = serveurLe
            it[DERNIERE_SYNCHRO] = maintenant.toEpochMilli()
            if (parametres != null) it[PARAMETRES] = json.encodeToString(parametres) else it.remove(PARAMETRES)
            it[ESPECES] = json.encodeToString(especes)
        }
    }

    suspend fun prochainNumeroProvisoire(): String {
        var numero = 0L
        contexte.etat.edit { numero = (it[SEQUENCE] ?: 0L) + 1; it[SEQUENCE] = numero }
        return "P-${identifiantAppareil().take(4).uppercase()}-${numero.toString().padStart(5, '0')}"
    }

    suspend fun imprimante(): String? = lire(IMPRIMANTE)

    suspend fun choisirImprimante(adresse: String) = contexte.etat.edit { it[IMPRIMANTE] = adresse }

    private suspend fun lire(cle: androidx.datastore.preferences.core.Preferences.Key<String>): String? = contexte.etat.data.first()[cle]

    private fun empreinte(pin: String, sel: ByteArray): ByteArray =
        SecretKeyFactory.getInstance("PBKDF2WithHmacSHA256").generateSecret(PBEKeySpec(pin.toCharArray(), sel, 120_000, 256)).encoded

    private fun hex(octets: ByteArray) = octets.joinToString("") { "%02x".format(it) }

    private fun octets(hex: String) = ByteArray(hex.length / 2) { hex.substring(it * 2, it * 2 + 2).toInt(16).toByte() }

    private companion object {
        val JETON = stringPreferencesKey("jeton")
        val EMAIL = stringPreferencesKey("email")
        val APPAREIL = stringPreferencesKey("appareil")
        val PIN = stringPreferencesKey("pin")
        val SERVEUR_LE = stringPreferencesKey("serveur_le")
        val DERNIERE_SYNCHRO = longPreferencesKey("derniere_synchro")
        val PARAMETRES = stringPreferencesKey("parametres")
        val ESPECES = stringPreferencesKey("especes")
        val SEQUENCE = longPreferencesKey("sequence_recus")
        val IMPRIMANTE = stringPreferencesKey("imprimante")
    }
}
