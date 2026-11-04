package com.microfinance.membre.ui

import android.Manifest
import android.content.Context
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.google.firebase.FirebaseApp
import com.google.firebase.messaging.FirebaseMessaging
import com.microfinance.membre.donnees.DepotMembre
import com.microfinance.reseau.CompteMembre
import com.microfinance.reseau.ErreurApi
import com.microfinance.reseau.NotificationMembre
import com.microfinance.reseau.PretMembre
import com.microfinance.reseau.Resultat
import com.microfinance.reseau.TontineMembre
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.async
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class AccueilUiState(
    val chargement: Boolean = true,
    val comptes: List<CompteMembre> = emptyList(),
    val tontines: List<TontineMembre> = emptyList(),
    val prets: List<PretMembre> = emptyList(),
    val avis: List<NotificationMembre> = emptyList(),
    val erreur: String? = null,
    val reconnexion: Boolean = false,
)

@HiltViewModel
class AccueilViewModel @Inject constructor(
    private val depot: DepotMembre,
    @ApplicationContext private val contexte: Context,
) : ViewModel() {
    private val _uiState = MutableStateFlow(AccueilUiState())
    val uiState: StateFlow<AccueilUiState> = _uiState.asStateFlow()

    init {
        actualiser()
        enregistrerJetonPush()
    }

    fun actualiser() {
        _uiState.update { it.copy(chargement = true, erreur = null) }
        viewModelScope.launch {
            val comptes = async { depot.comptes() }
            val tontines = async { depot.tontines() }
            val prets = async { depot.prets() }
            val avis = async { depot.notifications() }
            val resultats = listOf(comptes.await(), tontines.await(), prets.await(), avis.await())
            val erreur = resultats.filterIsInstance<Resultat.Echec>().firstOrNull()?.erreur
            _uiState.update {
                it.copy(
                    chargement = false,
                    comptes = (comptes.await() as? Resultat.Succes)?.valeur ?: it.comptes,
                    tontines = (tontines.await() as? Resultat.Succes)?.valeur ?: it.tontines,
                    prets = (prets.await() as? Resultat.Succes)?.valeur ?: it.prets,
                    avis = (avis.await() as? Resultat.Succes)?.valeur ?: it.avis,
                    erreur = erreur?.message,
                    reconnexion = erreur is ErreurApi.NonAutorise || (erreur is ErreurApi.Interdit && erreur.code == "mot_de_passe_a_changer"),
                )
            }
        }
    }

    fun deconnecter() {
        viewModelScope.launch { depot.deconnecter(); _uiState.update { it.copy(reconnexion = true) } }
    }

    private fun enregistrerJetonPush() {
        if (FirebaseApp.getApps(contexte).isEmpty()) return // Firebase non configuré : pas de notifications
        FirebaseMessaging.getInstance().token.addOnSuccessListener { jeton -> viewModelScope.launch { depot.enregistrerJetonPush(jeton) } }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranAccueil(onCompte: (Long, String) -> Unit, onReconnexion: () -> Unit, viewModel: AccueilViewModel = hiltViewModel()) {
    val etat by viewModel.uiState.collectAsStateWithLifecycle()
    var onglet by rememberSaveable { mutableIntStateOf(0) }
    LaunchedEffect(etat.reconnexion) { if (etat.reconnexion) onReconnexion() }
    val autorisation = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {}
    LaunchedEffect(Unit) { if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) autorisation.launch(Manifest.permission.POST_NOTIFICATIONS) }

    Scaffold(
        topBar = {
            TopAppBar(title = { Text("Mon épargne") }, actions = {
                TextButton(onClick = viewModel::actualiser) { Text("Actualiser") }
                TextButton(onClick = viewModel::deconnecter) { Text("Quitter") }
            })
        },
        bottomBar = {
            NavigationBar {
                listOf("Comptes", "Tontines", "Prêts", "Avis").forEachIndexed { i, titre ->
                    NavigationBarItem(selected = onglet == i, onClick = { onglet = i }, icon = {}, label = { Text(titre) })
                }
            }
        },
    ) { marges ->
        Box(Modifier.fillMaxSize().padding(marges)) {
            when {
                etat.chargement && etat.comptes.isEmpty() -> CircularProgressIndicator(Modifier.align(Alignment.Center))
                else -> Liste(etat.erreur) {
                    when (onglet) {
                        0 -> items(etat.comptes, key = { it.id }) { c ->
                            Fiche(Modifier.clickable { onCompte(c.id, c.numero) }) {
                                Text(c.produit, style = MaterialTheme.typography.titleSmall)
                                Text(c.numero, style = MaterialTheme.typography.bodySmall)
                                Text("Solde : ${c.solde.formate()}", style = MaterialTheme.typography.titleMedium)
                                if (c.bloque.estPositif) Text("Dont ${c.bloque.formate()} en garantie · disponible ${c.disponible.formate()}")
                            }
                        }
                        1 -> items(etat.tontines, key = { it.cycleId }) { t ->
                            Fiche {
                                Text("${t.groupe.nom} · cycle ${t.numero}", style = MaterialTheme.typography.titleSmall)
                                Text("${t.periodicite} · cotisation ${t.cotisation.formate()} · fonds social ${t.fondsSocial.formate()}")
                                t.prochaineSeance?.let { Text("Prochaine séance : n° ${it.rang} le ${it.date.orEmpty()}") }
                                t.monTour?.let { Text("Mon tour : rang ${it.rang}${it.date?.let { d -> " le $d" }.orEmpty()}", style = MaterialTheme.typography.titleMedium) }
                                if (t.dette.estPositif) Text("Dette : ${t.dette.formate()}", color = MaterialTheme.colorScheme.error)
                            }
                        }
                        2 -> items(etat.prets, key = { it.id }) { p ->
                            Fiche {
                                Text("Prêt ${p.numero} · ${p.statutLibelle}", style = MaterialTheme.typography.titleSmall)
                                Text("${p.montant.formate()} à ${p.tauxMensuel} par mois · reste dû ${p.resteDu.formate()}")
                                p.echeances.filter { !it.payee }.take(3).forEach { e ->
                                    Text("Échéance ${e.rang} du ${e.date} : ${e.resteDu.formate()}")
                                }
                            }
                        }
                        else -> items(etat.avis, key = { it.id }) { a ->
                            Fiche {
                                Text(a.titre, style = MaterialTheme.typography.titleSmall)
                                Text(a.corps)
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun Liste(erreur: String?, contenu: LazyListScope.() -> Unit) {
    LazyColumn(contentPadding = PaddingValues(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        if (erreur != null) item { MessageErreur(erreur) }
        contenu()
    }
}

@Composable
private fun Fiche(modifier: Modifier = Modifier, contenu: @Composable () -> Unit) {
    Card(modifier.fillMaxWidth()) { Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) { contenu() } }
}
