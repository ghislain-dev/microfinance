package com.microfinance.collecteur.domaine

import com.microfinance.modele.Montant
import com.microfinance.reseau.ParametresCollecte
import com.microfinance.reseau.PlafondsDevise
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.Instant

class LimitesCollecteTest {
    private fun usd(centimes: Long) = Montant(centimes, "USD")

    private val parametres = ParametresCollecte(
        delaiMaxHeures = 24,
        plafonds = mapOf("USD" to PlafondsDevise(plafondRetrait = usd(5_000), plafondEspeces = usd(100_000), seuilValidation = usd(20_000))),
    )
    private val maintenant = Instant.parse("2026-11-04T10:00:00Z")

    @Test
    fun `especes en poche comptent les entrees et les retraits en attente`() {
        val attente = listOf(
            OperationLocale(TypeOperation.Depot, 1, usd(3_000)),
            OperationLocale(TypeOperation.Cotisation, null, usd(1_000), fondsSocial = usd(50)),
            OperationLocale(TypeOperation.Retrait, 1, usd(2_000)),
        )

        assertEquals(usd(12_050), LimitesCollecte.especes("USD", mapOf("USD" to usd(10_000)), attente))
    }

    @Test
    fun `bloque toute saisie sans parametres, apres le delai ou au plafond d'especes`() {
        assertEquals(Refus.SansParametres, LimitesCollecte.peutSaisir(maintenant, maintenant, null, emptyMap(), emptyList(), "USD"))
        assertEquals(Refus.DelaiDepasse(24),
            LimitesCollecte.peutSaisir(maintenant, maintenant.minusSeconds(25 * 3600), parametres, emptyMap(), emptyList(), "USD"))
        assertTrue(LimitesCollecte.peutSaisir(maintenant, maintenant, parametres, mapOf("USD" to usd(100_000)), emptyList(), "USD") is Refus.PlafondEspeces)
        assertNull(LimitesCollecte.peutSaisir(maintenant, maintenant.minusSeconds(3600), parametres, mapOf("USD" to usd(99_999)), emptyList(), "USD"))
    }

    @Test
    fun `retrait limite au plafond, au disponible connu moins les retraits saisis, et aux especes en poche`() {
        val detenues = mapOf("USD" to usd(10_000))

        assertEquals(Refus.PlafondRetrait(usd(5_000)), LimitesCollecte.retrait(usd(5_001), 1, usd(100_000), parametres, detenues, emptyList()))
        val dejaSaisi = listOf(OperationLocale(TypeOperation.Retrait, 1, usd(4_000)))
        assertEquals(Refus.SoldeInsuffisant(usd(1_000)), LimitesCollecte.retrait(usd(1_500), 1, usd(5_000), parametres, detenues, dejaSaisi))
        assertTrue(LimitesCollecte.retrait(usd(4_000), 1, usd(10_000), parametres, mapOf("USD" to usd(3_000)), emptyList()) is Refus.EspecesInsuffisantes)
        assertNull(LimitesCollecte.retrait(usd(4_000), 1, usd(10_000), parametres, detenues, emptyList()))
    }

    @Test
    fun `le seuil de validation plafonne le retrait s'il est plus bas`() {
        val bas = parametres.copy(plafonds = mapOf("USD" to PlafondsDevise(usd(50_000), usd(100_000), usd(20_000))))

        assertEquals(Refus.PlafondRetrait(usd(20_000)), LimitesCollecte.retrait(usd(20_001), 1, usd(100_000), bas, mapOf("USD" to usd(90_000)), emptyList()))
    }
}
