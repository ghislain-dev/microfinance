package com.microfinance.membre.ui

import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.microfinance.membre.donnees.EtatMembre
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import javax.inject.Inject

@HiltViewModel
class DemarrageViewModel @Inject constructor(etat: EtatMembre) : ViewModel() {
    val depart: Flow<String> = etat.connecte.map { if (it) "accueil" else "connexion" }
}

@Composable
fun NavigationMembre(demarrage: DemarrageViewModel = hiltViewModel()) {
    val depart by demarrage.depart.collectAsState(initial = null)
    val debut = depart ?: return
    val nav = rememberNavController()

    NavHost(nav, startDestination = debut) {
        composable("connexion") {
            EcranConnexion(onSuite = { suite ->
                nav.navigate(if (suite == Suite.ChangerMotDePasse) "mot-de-passe" else "accueil") { popUpTo(0) }
            })
        }
        composable("mot-de-passe") { EcranMotDePasse(onTermine = { nav.navigate("accueil") { popUpTo(0) } }) }
        composable("accueil") {
            EcranAccueil(onCompte = { id, numero -> nav.navigate("compte/$id/$numero") }, onReconnexion = { nav.navigate("connexion") { popUpTo(0) } })
        }
        composable("compte/{compte}/{numero}", listOf(navArgument("compte") { type = NavType.LongType })) { entree ->
            EcranMouvements(numero = entree.arguments?.getString("numero").orEmpty())
        }
    }
}
