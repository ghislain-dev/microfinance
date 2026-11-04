package com.microfinance.collecteur.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.ListItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.microfinance.collecteur.donnees.DepotCollecte
import com.microfinance.collecteur.donnees.OperationEntite
import com.microfinance.modele.Montant
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.stateIn
import javax.inject.Inject

@HiltViewModel
class OperationsViewModel @Inject constructor(depot: DepotCollecte) : ViewModel() {
    val operations: StateFlow<List<OperationEntite>> = depot.recentes.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), emptyList())
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranOperations(onRecu: (String) -> Unit, viewModel: OperationsViewModel = hiltViewModel()) {
    val operations by viewModel.operations.collectAsStateWithLifecycle()
    Scaffold(topBar = { TopAppBar(title = { Text("Mes saisies") }) }) { marges ->
        LazyColumn(Modifier.fillMaxSize().padding(marges)) {
            items(operations, key = { it.cle }) { op ->
                val etat = when (op.statut) {
                    OperationEntite.EN_ATTENTE -> "En attente d'envoi"
                    OperationEntite.ACCEPTEE -> "Acceptée${op.recuOfficiel?.let { " · reçu $it" }.orEmpty()}"
                    else -> "Refusée : ${op.raison.orEmpty()}"
                }
                ListItem(
                    headlineContent = { Text("${op.libelle} · ${Montant(op.montantCentimes, op.devise).formate()}") },
                    supportingContent = {
                        Text("${op.membreNumero} · ${op.numeroProvisoire} · $etat",
                            color = if (op.statut == OperationEntite.REFUSEE) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant)
                    },
                    modifier = Modifier.clickable { onRecu(op.cle) },
                )
                HorizontalDivider()
            }
        }
    }
}
