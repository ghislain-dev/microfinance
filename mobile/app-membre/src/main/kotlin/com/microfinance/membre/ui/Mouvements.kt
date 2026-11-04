package com.microfinance.membre.ui

import android.content.Context
import android.content.Intent
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.ListItem
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.core.content.FileProvider
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.microfinance.membre.donnees.DepotMembre
import com.microfinance.reseau.Mouvement
import com.microfinance.reseau.Resultat
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.io.File
import java.time.LocalDate
import javax.inject.Inject

data class MouvementsUiState(
    val mouvements: List<Mouvement> = emptyList(),
    val chargement: Boolean = true,
    val erreur: String? = null,
    val releveEnCours: Boolean = false,
    val releve: File? = null,
)

@HiltViewModel
class MouvementsViewModel @Inject constructor(
    private val depot: DepotMembre,
    @ApplicationContext private val contexte: Context,
    etatNavigation: SavedStateHandle,
) : ViewModel() {
    private val compte = checkNotNull(etatNavigation.get<Long>("compte"))
    private val _uiState = MutableStateFlow(MouvementsUiState())
    val uiState: StateFlow<MouvementsUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            _uiState.value = when (val r = depot.mouvements(compte)) {
                is Resultat.Succes -> MouvementsUiState(r.valeur, chargement = false)
                is Resultat.Echec -> MouvementsUiState(chargement = false, erreur = r.erreur.message)
            }
        }
    }

    fun releve(mois: Long) {
        if (_uiState.value.releveEnCours) return
        _uiState.update { it.copy(releveEnCours = true, erreur = null) }
        viewModelScope.launch {
            val au = LocalDate.now()
            val dossier = File(contexte.cacheDir, "releves").apply { mkdirs() }
            when (val r = depot.releve(compte, au.minusMonths(mois).plusDays(1), au, dossier)) {
                is Resultat.Succes -> _uiState.update { it.copy(releveEnCours = false, releve = r.valeur) }
                is Resultat.Echec -> _uiState.update { it.copy(releveEnCours = false, erreur = r.erreur.message) }
            }
        }
    }

    fun releveOuvert() = _uiState.update { it.copy(releve = null) }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranMouvements(numero: String, viewModel: MouvementsViewModel = hiltViewModel()) {
    val etat by viewModel.uiState.collectAsStateWithLifecycle()
    val contexte = LocalContext.current
    LaunchedEffect(etat.releve) {
        etat.releve?.let { fichier ->
            val uri = FileProvider.getUriForFile(contexte, "${contexte.packageName}.fichiers", fichier)
            val ouverture = Intent(Intent.ACTION_VIEW).setDataAndType(uri, "application/pdf").addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            runCatching { contexte.startActivity(Intent.createChooser(ouverture, "Ouvrir le relevé")) }
            viewModel.releveOuvert()
        }
    }
    Scaffold(topBar = { TopAppBar(title = { Text(numero) }) }) { marges ->
        LazyColumn(Modifier.fillMaxSize().padding(marges)) {
            item {
                Row(Modifier.padding(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf(1L to "Relevé 1 mois", 3L to "3 mois", 12L to "12 mois").forEach { (mois, libelle) ->
                        OutlinedButton(onClick = { viewModel.releve(mois) }, enabled = !etat.releveEnCours) { Text(libelle) }
                    }
                }
                if (etat.releveEnCours) Text("Préparation du relevé…", Modifier.padding(horizontal = 12.dp))
            }
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
