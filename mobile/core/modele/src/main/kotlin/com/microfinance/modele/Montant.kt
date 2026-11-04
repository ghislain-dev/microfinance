package com.microfinance.modele

import kotlinx.serialization.Serializable

/**
 * Montant en centimes entiers et sa devise, tel que l'API l'envoie : jamais de nombre à virgule
 * (CLAUDE.md §4.1). Le téléphone n'additionne que pour ses limites hors-ligne ; le solde officiel
 * vient toujours du serveur.
 */
@Serializable
data class Montant(val centimes: Long, val devise: String) {
    operator fun plus(autre: Montant): Montant = memeDevise(autre).let { Montant(centimes + autre.centimes, devise) }

    operator fun minus(autre: Montant): Montant = memeDevise(autre).let { Montant(centimes - autre.centimes, devise) }

    operator fun compareTo(autre: Montant): Int = memeDevise(autre).let { centimes.compareTo(autre.centimes) }

    val estPositif: Boolean get() = centimes > 0

    /** « 1 250,50 USD », comme le serveur (espace insécable fine absente : simple espace). */
    fun formate(): String {
        val signe = if (centimes < 0) "-" else ""
        val absolu = kotlin.math.abs(centimes)
        val unites = (absolu / 100).toString().reversed().chunked(3).joinToString(" ").reversed()
        val reste = (absolu % 100).toString().padStart(2, '0')
        return "$signe$unites,$reste $devise"
    }

    private fun memeDevise(autre: Montant) = require(autre.devise == devise) { "Devises différentes : $devise / ${autre.devise}" }

    companion object {
        fun zero(devise: String) = Montant(0, devise)

        private val FORMAT = Regex("""^\d{1,13}([.,]\d{1,2})?$""")

        /** « 12 », « 12,5 », « 12.50 » → centimes ; null si la saisie n'est pas un montant positif ou nul. */
        fun depuisSaisie(saisie: String, devise: String): Montant? {
            val texte = saisie.replace(" ", "").replace(" ", "").trim()
            if (!FORMAT.matches(texte)) return null
            val (unites, decimales) = texte.replace(',', '.').split('.').let { it[0] to (it.getOrNull(1) ?: "") }
            return Montant(unites.toLong() * 100 + decimales.padEnd(2, '0').toLong(), devise)
        }
    }
}
