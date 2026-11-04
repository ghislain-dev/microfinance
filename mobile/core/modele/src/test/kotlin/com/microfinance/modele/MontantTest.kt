package com.microfinance.modele

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class MontantTest {
    @Test
    fun `lit une saisie en centimes sans nombre a virgule`() {
        assertEquals(1_250_50L, Montant.depuisSaisie("1250,50", "USD")?.centimes)
        assertEquals(1_250L, Montant.depuisSaisie("12,5", "USD")?.centimes)
        assertEquals(1_200L, Montant.depuisSaisie("12", "USD")?.centimes)
        assertEquals(1_000_000L, Montant.depuisSaisie("10 000", "CDF")?.centimes)
        assertNull(Montant.depuisSaisie("12,345", "USD"))
        assertNull(Montant.depuisSaisie("-5", "USD"))
        assertNull(Montant.depuisSaisie("abc", "USD"))
    }

    @Test
    fun `formate comme le serveur`() {
        assertEquals("1 250,50 USD", Montant(125_050, "USD").formate())
        assertEquals("0,05 CDF", Montant(5, "CDF").formate())
        assertEquals("-5,00 USD", Montant(-500, "USD").formate())
        assertEquals("1 000 000,00 CDF", Montant(100_000_000, "CDF").formate())
    }

    @Test(expected = IllegalArgumentException::class)
    fun `refuse d'additionner deux devises`() {
        Montant(100, "USD") + Montant(100, "CDF")
    }
}
