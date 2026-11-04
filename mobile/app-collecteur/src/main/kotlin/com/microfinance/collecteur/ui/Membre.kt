package com.microfinance.collecteur.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Card
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.microfinance.collecteur.domaine.TypeOperation
import com.microfinance.collecteur.donnees.DepotCollecte
import com.microfinance.reseau.MembreTournee
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.stateIn
import javax.inject.Inject

@HiltViewModel
class MembreViewModel @Inject constructor(depot: DepotCollecte, etatNavigation: SavedStateHandle) : ViewModel() {
    val membre: StateFlow<MembreTournee?> = depot.membre(checkNotNull(etatNavigation.get<Long>("membre")))
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), null)
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranMembre(onSaisie: (Long, TypeOperation) -> Unit, viewModel: MembreViewModel = hiltViewModel()) {
    val membre by viewModel.membre.collectAsStateWithLifecycle()
    Scaffold(topBar = { TopAppBar(title = { Text(membre?.nom ?: "Membre") }) }) { marges ->
        val m = membre ?: return@Scaffold
        Formulaire(marges) {
            Text("${m.numero} · ${m.telephone.orEmpty()}", style = MaterialTheme.typography.bodyMedium)
            Text("Soldes connus à la dernière synchronisation", style = MaterialTheme.typography.titleSmall)
            m.comptes.forEach { c ->
                Card(Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(12.dp)) {
                        Text(c.numero, style = MaterialTheme.typography.titleSmall)
                        Text("Solde ${c.solde.formate()} · disponible ${c.disponible.formate()}${if (c.bloque) " · compte bloqué" else ""}")
                    }
                }
            }
            m.tontines.forEach { t ->
                Card(Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(12.dp)) {
                        Text(t.groupe, style = MaterialTheme.typography.titleSmall)
                        Text(t.prochaineSeance?.let { "Prochaine séance n° ${it.rang} le ${it.date}${if (it.dejaPaye) " · déjà payée" else ""}" } ?: "Aucune séance prévue")
                        Text("Dette : ${t.dette.formate()}")
                    }
                }
            }
            m.prets.forEach { p ->
                Card(Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(12.dp)) {
                        Text("Prêt ${p.numero}", style = MaterialTheme.typography.titleSmall)
                        Text("Dû à ce jour ${p.duACeJour.formate()} · reste ${p.resteDu.formate()}")
                    }
                }
            }
            Text("Nouvelle opération", style = MaterialTheme.typography.titleSmall)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                if (m.comptes.isNotEmpty()) {
                    OutlinedButton(onClick = { onSaisie(m.id, TypeOperation.Depot) }) { Text("Dépôt") }
                    OutlinedButton(onClick = { onSaisie(m.id, TypeOperation.Retrait) }) { Text("Retrait") }
                }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                if (m.tontines.any { it.prochaineSeance?.dejaPaye == false }) OutlinedButton(onClick = { onSaisie(m.id, TypeOperation.Cotisation) }) { Text("Cotisation") }
                if (m.prets.isNotEmpty()) OutlinedButton(onClick = { onSaisie(m.id, TypeOperation.Remboursement) }) { Text("Remboursement") }
            }
        }
    }
}
