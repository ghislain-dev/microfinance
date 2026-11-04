package com.microfinance.collecteur.impression

import com.microfinance.collecteur.donnees.OperationEntite
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.ZoneId

class RecuEscPosTest {
    private val operation = OperationEntite(
        cle = "c", type = "cotisation", membreId = 1, membreNumero = "BUK-000045", membreNom = "Mukendi Bénédicte",
        compteEpargneId = null, pretId = null, cycleId = 3, devise = "CDF", montantCentimes = 1_000_000, fondsSocialCentimes = 50_000,
        parts = null, libelle = "Cotisation — Mamans du marché", saisiLe = "2026-11-04T08:30:00Z", numeroProvisoire = "P-AB12-00007",
    )

    @Test
    fun `tient sur 32 colonnes, sans accents, avec montants et numero provisoire`() {
        val lignes = RecuEscPos.lignes(operation, "Kabeya", ZoneId.of("Africa/Kinshasa"))

        assertTrue(lignes.all { it.length <= RecuEscPos.COLONNES })
        assertTrue(lignes.all { ligne -> ligne.all { it.code in 0x20..0x7E } })
        assertEquals("P-AB12-00007", lignes[1])
        assertTrue(lignes.contains(RecuEscPos.colonnes("Montant", "10 000,00 CDF")))
        assertTrue(lignes.contains(RecuEscPos.colonnes("Fonds social", "500,00 CDF")))
        assertTrue(lignes.contains(RecuEscPos.colonnes("Date", "04/11/2026 09:30")))
        assertTrue(lignes.contains("Mukendi Benedicte"))
    }

    @Test
    fun `commence par la remise a zero de l'imprimante et finit par l'avance du papier`() {
        val octets = RecuEscPos.octets(operation, "Kabeya")

        assertEquals(listOf<Byte>(0x1B, 0x40), octets.take(2))
        assertEquals(listOf<Byte>(0x1B, 0x64, 4), octets.takeLast(3))
    }
}
