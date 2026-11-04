package com.microfinance.reseau

import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import okhttp3.OkHttpClient
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

class AppelApiTest {
    private val serveur = MockWebServer()
    private lateinit var api: ApiMicrofinance
    private val json = ModuleReseau.json()

    @Before
    fun demarrer() {
        serveur.start()
        val configuration = object : ConfigurationApi {
            override val urlDeBase = serveur.url("/api/v1").toString()
            override fun jeton() = "jeton-test"
        }
        api = ModuleReseau.api(configuration, ModuleReseau.client(configuration), json)
    }

    @After
    fun arreter() = serveur.close()

    @Test
    fun `lit les montants en centimes et envoie le jeton`() = runTest {
        serveur.enqueue(MockResponse.Builder().body(
            """{"data":[{"id":1,"numero":"BUK-1-EPV-1","produit":"Épargne","devise":"USD","solde":{"centimes":50000,"devise":"USD","texte":"500,00 USD"},
            "bloque":{"centimes":0,"devise":"USD","texte":"0,00 USD"},"disponible":{"centimes":50000,"devise":"USD","texte":"500,00 USD"},"inconnu":true}]}""",
        ).build())

        val resultat = AppelApi(json).corps { api.comptes() }

        assertTrue(resultat is Resultat.Succes)
        assertEquals(50_000L, (resultat as Resultat.Succes).valeur.data.single().solde.centimes)
        assertEquals("Bearer jeton-test", serveur.takeRequest().headers["Authorization"])
    }

    @Test
    fun `traduit un refus 403 avec son code metier`() = runTest {
        serveur.enqueue(MockResponse.Builder().code(403).body("""{"message":"Téléphone en attente","code":"appareil_en_attente"}""").build())

        val resultat = AppelApi(json).corps { api.tournee() }

        assertEquals(ErreurApi.Interdit("appareil_en_attente", "Téléphone en attente"), (resultat as Resultat.Echec).erreur)
    }

    @Test
    fun `traduit une erreur de validation 422 champ par champ`() = runTest {
        serveur.enqueue(MockResponse.Builder().code(422).body("""{"message":"x","errors":{"numero":["Identifiants incorrects."]}}""").build())

        val erreur = (AppelApi(json).corps { api.comptes() } as Resultat.Echec).erreur as ErreurApi.Validation

        assertEquals("Identifiants incorrects.", erreur.champs["numero"])
    }

    @Test
    fun `serveur injoignable donne une erreur reseau`() = runTest {
        serveur.close()

        assertEquals(ErreurApi.Reseau, (AppelApi(json).corps { api.comptes() } as Resultat.Echec).erreur)
    }
}
