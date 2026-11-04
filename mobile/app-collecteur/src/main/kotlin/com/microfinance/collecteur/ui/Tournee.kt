package com.microfinance.collecteur.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Card
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.ListItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.microfinance.collecteur.domaine.LimitesCollecte
import com.microfinance.collecteur.donnees.DepotCollecte
import com.microfinance.collecteur.donnees.DepotSession
import com.microfinance.collecteur.donnees.EtatLocal
import com.microfinance.collecteur.donnees.InstantaneCollecte
import com.microfinance.collecteur.domaine.OperationLocale
import com.microfinance.collecteur.domaine.TypeOperation
import com.microfinance.modele.Montant
import com.microfinance.reseau.MembreTournee
import com.microfinance.reseau.Resultat
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import javax.inject.Inject

data class TourneeUiState(
    val membres: List<MembreTournee> = emptyList(),
    val recherche: String = "",
    val derniereSynchro: String? = null,
    val especes: List<Montant> = emptyList(),
    val enAttente: Int = 0,
    val synchroEnCours: Boolean = false,
    val message: String? = null,
    val reconnexion: Boolean = false,
)

private data class Transitoire(val recherche: String = "", val enCours: Boolean = false, val message: String? = null, val reconnexion: Boolean = false)

@HiltViewModel
class TourneeViewModel @Inject constructor(
    private val depot: DepotCollecte,
    private val session: DepotSession,
    etat: EtatLocal,
) : ViewModel() {
    private val transitoire = MutableStateFlow(Transitoire())
    private val format = DateTimeFormatter.ofPattern("dd/MM HH:mm").withZone(ZoneId.systemDefault())

    val uiState: StateFlow<TourneeUiState> = combine(depot.membres, etat.instantane, depot.enAttente, transitoire) { membres, instantane, attente, t ->
        val q = t.recherche.trim().lowercase()
        TourneeUiState(
            membres = if (q.isEmpty()) membres else membres.filter { q in it.nom.lowercase() || q in it.numero.lowercase() },
            recherche = t.recherche,
            derniereSynchro = instantane.derniereSynchro?.let(format::format),
            especes = especes(instantane, attente.map { OperationLocale(TypeOperation.depuisApi(it.type) ?: TypeOperation.Depot, it.compteEpargneId,
                Montant(it.montantCentimes, it.devise), it.fondsSocialCentimes?.let { fs -> Montant(fs, it.devise) }) }),
            enAttente = attente.size,
            synchroEnCours = t.enCours,
            message = t.message,
            reconnexion = t.reconnexion,
        )
    }.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), TourneeUiState())

    fun rechercher(texte: String) = transitoire.update { it.copy(recherche = texte) }

    fun synchroniser() {
        if (transitoire.value.enCours) return
        transitoire.update { it.copy(enCours = true, message = null) }
        viewModelScope.launch {
            val r = depot.synchroniser()
            transitoire.update {
                when (r) {
                    is Resultat.Succes -> it.copy(enCours = false, message = "Synchronisé : ${r.valeur.acceptees} acceptée(s), ${r.valeur.refusees} refusée(s).")
                    is Resultat.Echec -> it.copy(enCours = false, message = session.message(r.erreur), reconnexion = depot.exigeReconnexion(r.erreur))
                }
            }
        }
    }

    private fun especes(instantane: InstantaneCollecte, attente: List<OperationLocale>): List<Montant> =
        (instantane.especes.keys + attente.map { it.montant.devise }).toSortedSet().map { LimitesCollecte.especes(it, instantane.especes, attente) }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranTournee(onMembre: (Long) -> Unit, onOperations: () -> Unit, onReconnexion: () -> Unit, viewModel: TourneeViewModel = hiltViewModel()) {
    val etat by viewModel.uiState.collectAsStateWithLifecycle()
    LaunchedEffect(Unit) { viewModel.synchroniser() }
    LaunchedEffect(etat.reconnexion) { if (etat.reconnexion) onReconnexion() }

    Scaffold(topBar = {
        TopAppBar(title = { Text("Ma tournée") }, actions = {
            TextButton(onClick = onOperations) { Text("Saisies (${etat.enAttente})") }
            TextButton(onClick = viewModel::synchroniser, enabled = !etat.synchroEnCours) { Text(if (etat.synchroEnCours) "…" else "Synchroniser") }
        })
    }) { marges ->
        Column(Modifier.fillMaxSize().padding(marges)) {
            Card(Modifier.fillMaxWidth().padding(12.dp)) {
                Column(Modifier.padding(12.dp)) {
                    Text("Dernière synchronisation : ${etat.derniereSynchro ?: "jamais"}", style = MaterialTheme.typography.bodyMedium)
                    Text("Espèces en main : ${etat.especes.joinToString(" · ") { it.formate() }.ifEmpty { "—" }}", style = MaterialTheme.typography.bodyMedium)
                    Text("En attente d'envoi : ${etat.enAttente}", style = MaterialTheme.typography.bodyMedium)
                    etat.message?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                }
            }
            OutlinedTextField(etat.recherche, viewModel::rechercher, label = { Text("Nom ou numéro") }, singleLine = true,
                modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp))
            LazyColumn(Modifier.fillMaxSize()) {
                items(etat.membres, key = { it.id }) { membre ->
                    ListItem(
                        headlineContent = { Text(membre.nom) },
                        supportingContent = { Text("${membre.numero} · ${membre.comptes.joinToString { it.solde.formate() }}") },
                        modifier = Modifier.clickable { onMembre(membre.id) },
                    )
                    HorizontalDivider()
                }
            }
        }
    }
}
