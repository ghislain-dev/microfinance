package com.microfinance.membre.ui

import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.ListItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.microfinance.membre.donnees.DepotMembre
import com.microfinance.reseau.Mouvement
import com.microfinance.reseau.Resultat
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class MouvementsUiState(val mouvements: List<Mouvement> = emptyList(), val chargement: Boolean = true, val erreur: String? = null)

@HiltViewModel
class MouvementsViewModel @Inject constructor(depot: DepotMembre, etatNavigation: SavedStateHandle) : ViewModel() {
    private val _uiState = MutableStateFlow(MouvementsUiState())
    val uiState: StateFlow<MouvementsUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            _uiState.value = when (val r = depot.mouvements(checkNotNull(etatNavigation.get<Long>("compte")))) {
                is Resultat.Succes -> MouvementsUiState(r.valeur, chargement = false)
                is Resultat.Echec -> MouvementsUiState(chargement = false, erreur = r.erreur.message)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranMouvements(numero: String, viewModel: MouvementsViewModel = hiltViewModel()) {
    val etat by viewModel.uiState.collectAsStateWithLifecycle()
    Scaffold(topBar = { TopAppBar(title = { Text(numero) }) }) { marges ->
        LazyColumn(Modifier.fillMaxSize().padding(marges)) {
            etat.erreur?.let { item { MessageErreur(it) } }
            items(etat.mouvements, key = { it.id }) { m ->
                ListItem(
                    headlineContent = { Text("${m.libelle} · ${m.montant.formate()}") },
                    supportingContent = { Text(m.le.take(16).replace('T', ' ') + (m.recu?.let { " · reçu $it" }.orEmpty())) },
                )
                HorizontalDivider()
            }
        }
    }
}
