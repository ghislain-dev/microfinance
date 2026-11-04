package com.microfinance.collecteur.impression

import com.microfinance.collecteur.donnees.OperationEntite
import com.microfinance.modele.Montant
import java.text.Normalizer
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter

/**
 * Reçu provisoire pour imprimante thermique 58 mm (32 colonnes, commandes ESC/POS). Les accents sont retirés :
 * la plupart de ces imprimantes n'ont pas de jeu de caractères français fiable.
 */
object RecuEscPos {
    const val COLONNES = 32
    private val INIT = byteArrayOf(0x1B, 0x40)
    private val GRAS = byteArrayOf(0x1B, 0x45, 1)
    private val NORMAL = byteArrayOf(0x1B, 0x45, 0)
    private val CENTRE = byteArrayOf(0x1B, 0x61, 1)
    private val GAUCHE = byteArrayOf(0x1B, 0x61, 0)
    private val AVANCE = byteArrayOf(0x1B, 0x64, 4)

    fun lignes(operation: OperationEntite, collecteur: String, fuseau: ZoneId = ZoneId.systemDefault()): List<String> {
        val date = DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm").withZone(fuseau).format(Instant.parse(operation.saisiLe))
        val montant = Montant(operation.montantCentimes, operation.devise)
        return buildList {
            add("RECU PROVISOIRE")
            add(operation.numeroProvisoire)
            add("-".repeat(COLONNES))
            add(colonnes("Date", date))
            add(colonnes("Membre", operation.membreNumero))
            add(operation.membreNom.take(COLONNES))
            add(operation.libelle.take(COLONNES))
            add(colonnes("Montant", montant.formate()))
            operation.fondsSocialCentimes?.takeIf { it > 0 }?.let { add(colonnes("Fonds social", Montant(it, operation.devise).formate())) }
            operation.parts?.let { add(colonnes("Parts", it.toString())) }
            add(colonnes("Collecteur", collecteur.take(18)))
            add("-".repeat(COLONNES))
            add("Recu officiel dans l'application")
            add("du membre apres synchronisation.")
        }.map(::sansAccents)
    }

    fun octets(operation: OperationEntite, collecteur: String, fuseau: ZoneId = ZoneId.systemDefault()): ByteArray {
        val lignes = lignes(operation, collecteur, fuseau)
        val sortie = java.io.ByteArrayOutputStream()
        sortie.write(INIT)
        sortie.write(CENTRE + GRAS)
        lignes.take(2).forEach { sortie.write((it + "\n").toByteArray(Charsets.US_ASCII)) }
        sortie.write(NORMAL + GAUCHE)
        lignes.drop(2).forEach { sortie.write((it + "\n").toByteArray(Charsets.US_ASCII)) }
        sortie.write(AVANCE)
        return sortie.toByteArray()
    }

    fun colonnes(gauche: String, droite: String): String {
        val espace = (COLONNES - gauche.length - droite.length).coerceAtLeast(1)
        return (gauche + " ".repeat(espace) + droite).take(COLONNES)
    }

    fun sansAccents(texte: String): String =
        Normalizer.normalize(texte.replace(Regex("[\u2013\u2014]"), "-").replace('\u2019', '\''), Normalizer.Form.NFD)
            .replace(Regex("\\p{M}"), "").replace(Regex("[^\\x20-\\x7E]"), "?")
}
