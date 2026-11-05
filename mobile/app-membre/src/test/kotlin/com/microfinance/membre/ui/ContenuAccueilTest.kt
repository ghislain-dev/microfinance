package com.microfinance.membre.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import com.microfinance.modele.Montant
import com.microfinance.reseau.CompteMembre
import com.microfinance.reseau.EcheanceMembre
import com.microfinance.reseau.GroupeRef
import com.microfinance.reseau.NotificationMembre
import com.microfinance.reseau.PretMembre
import com.microfinance.reseau.SeanceRef
import com.microfinance.reseau.TontineMembre
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [35])
class ContenuAccueilTest {
    @get:Rule val regle = createComposeRule()

    private fun usd(c: Long) = Montant(c, "USD")
    private fun cdf(c: Long) = Montant(c, "CDF")

    private val etat = AccueilUiState(
        chargement = false,
        comptes = listOf(CompteMembre(7, "SIEGE-000001-EPV-1", "Épargne à vue", "USD", usd(147_106), usd(3_000), usd(144_106))),
        tontines = listOf(TontineMembre(3, GroupeRef("MAMANS", "Mamans du marché", "rotative"), 1, "Chaque semaine", cdf(1_000_000), cdf(50_000),
            SeanceRef(2, "2026-11-12"), SeanceRef(4, "2026-11-26"), cdf(250_000))),
        prets = listOf(PretMembre(5, "SIEGE-000001-CR1", "en_cours", "En cours", usd(15_000), "3,00 %", "2026-11-02", usd(13_845),
            listOf(EcheanceMembre(2, "2027-01-02", usd(2_389), usd(380), usd(0), usd(0), usd(2_769), false)))),
        avis = listOf(NotificationMembre(1, "Dépôt", "Dépôt de 12,50 USD sur SIEGE-000001-EPV-1. Solde : 1 471,06 USD.")),
    )

    @Test
    fun `affiche les comptes avec le solde, la garantie et le disponible du serveur`() {
        var ouvert: Pair<Long, String>? = null
        regle.setContent { ContenuAccueil(etat, onCompte = { id, n -> ouvert = id to n }, onActualiser = {}, onQuitter = {}) }

        regle.onNodeWithText("Solde : 1 471,06 USD").assertIsDisplayed()
        regle.onNodeWithText("Dont 30,00 USD en garantie · disponible 1 441,06 USD").assertIsDisplayed()
        regle.onNodeWithText("SIEGE-000001-EPV-1").performClick()
        assertEquals(7L to "SIEGE-000001-EPV-1", ouvert)
    }

    @Test
    fun `montre la tontine, le tour, la dette, les prets et les avis selon l'onglet`() {
        regle.setContent { ContenuAccueil(etat, onCompte = { _, _ -> }, onActualiser = {}, onQuitter = {}) }

        regle.onNodeWithText("Tontines").performClick()
        regle.onNodeWithText("Mon tour : rang 4 le 2026-11-26").assertIsDisplayed()
        regle.onNodeWithText("Dette : 2 500,00 CDF").assertIsDisplayed()

        regle.onNodeWithText("Prêts").performClick()
        regle.onNodeWithText("150,00 USD à 3,00 % par mois · reste dû 138,45 USD").assertIsDisplayed()
        regle.onNodeWithText("Échéance 2 du 2027-01-02 : 27,69 USD").assertIsDisplayed()

        regle.onNodeWithText("Avis").performClick()
        regle.onNodeWithText("Dépôt de 12,50 USD sur SIEGE-000001-EPV-1. Solde : 1 471,06 USD.").assertIsDisplayed()
    }

    @Test
    fun `affiche l'erreur du serveur au-dessus des donnees deja connues`() {
        regle.setContent { ContenuAccueil(etat.copy(erreur = "Pas de connexion au serveur."), onCompte = { _, _ -> }, onActualiser = {}, onQuitter = {}) }

        regle.onNodeWithText("Pas de connexion au serveur.").assertIsDisplayed()
        regle.onNodeWithText("Solde : 1 471,06 USD").assertIsDisplayed()
    }
}
