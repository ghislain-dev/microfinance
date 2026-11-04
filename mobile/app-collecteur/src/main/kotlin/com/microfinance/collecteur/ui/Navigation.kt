package com.microfinance.collecteur.ui

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
import com.microfinance.collecteur.domaine.TypeOperation
import com.microfinance.collecteur.donnees.EtatLocal
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.combine
import javax.inject.Inject

@HiltViewModel
class DemarrageViewModel @Inject constructor(etat: EtatLocal) : ViewModel() {
    /** null tant que l'état n'est pas lu ; sinon la première destination. */
    val depart: Flow<String> = combine(etat.connecte, etat.pinDefini) { connecte, pin ->
        when {
            !connecte -> "connexion"
            !pin -> "pin/creation"
            else -> "pin/verification"
        }
    }
}

@Composable
fun NavigationCollecte(demarrage: DemarrageViewModel = hiltViewModel()) {
    val depart by demarrage.depart.collectAsState(initial = null)
    val debut = depart ?: return
    val nav = rememberNavController()
    val versConnexion = { nav.navigate("connexion") { popUpTo(0) } }

    NavHost(nav, startDestination = debut) {
        composable("connexion") { EcranConnexion(onConnecte = { nav.navigate("pin/creation") { popUpTo(0) } }) }
        composable("pin/{mode}") { entree ->
            EcranPin(
                creation = entree.arguments?.getString("mode") == "creation",
                onDeverrouille = { nav.navigate("tournee") { popUpTo(0) } },
                onReconnexion = versConnexion,
            )
        }
        composable("tournee") {
            EcranTournee(onMembre = { nav.navigate("membre/$it") }, onOperations = { nav.navigate("operations") }, onReconnexion = versConnexion)
        }
        composable("membre/{membre}", listOf(navArgument("membre") { type = NavType.LongType })) {
            EcranMembre(onSaisie = { membre, type -> nav.navigate("saisie/$membre/${type.name}") })
        }
        composable("saisie/{membre}/{type}", listOf(navArgument("membre") { type = NavType.LongType }, navArgument("type") { type = NavType.StringType })) {
            EcranSaisie(onEnregistree = { cle -> nav.navigate("recu/$cle") { popUpTo("tournee") } })
        }
        composable("recu/{cle}") { EcranRecu(onTerminer = { nav.popBackStack("tournee", inclusive = false) }) }
        composable("operations") { EcranOperations(onRecu = { nav.navigate("recu/$it") }) }
    }
}
