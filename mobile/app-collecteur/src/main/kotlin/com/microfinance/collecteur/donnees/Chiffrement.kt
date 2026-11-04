package com.microfinance.collecteur.donnees

import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec
import javax.inject.Inject
import javax.inject.Singleton

/** Chiffre le jeton d'accès avec une clé AES du Keystore Android (jamais exportable). */
@Singleton
class Chiffrement @Inject constructor() {
    private val alias = "microfinance-collecteur"

    fun chiffrer(texte: String): String {
        val cipher = Cipher.getInstance(TRANSFORMATION).apply { init(Cipher.ENCRYPT_MODE, cle()) }
        val donnees = cipher.iv + cipher.doFinal(texte.toByteArray(Charsets.UTF_8))
        return Base64.encodeToString(donnees, Base64.NO_WRAP)
    }

    fun dechiffrer(texte: String): String? = runCatching {
        val donnees = Base64.decode(texte, Base64.NO_WRAP)
        val cipher = Cipher.getInstance(TRANSFORMATION).apply {
            init(Cipher.DECRYPT_MODE, cle(), GCMParameterSpec(128, donnees.copyOfRange(0, 12)))
        }
        String(cipher.doFinal(donnees.copyOfRange(12, donnees.size)), Charsets.UTF_8)
    }.getOrNull()

    private fun cle(): SecretKey {
        val keystore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        (keystore.getEntry(alias, null) as? KeyStore.SecretKeyEntry)?.let { return it.secretKey }
        return KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore").apply {
            init(KeyGenParameterSpec.Builder(alias, KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .build())
        }.generateKey()
    }

    private companion object {
        const val TRANSFORMATION = "AES/GCM/NoPadding"
    }
}
