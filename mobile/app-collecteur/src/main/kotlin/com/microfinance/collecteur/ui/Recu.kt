package com.microfinance.collecteur.ui

import android.Manifest
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.selection.selectable
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontFamily
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.microfinance.collecteur.donnees.DepotCollecte
import com.microfinance.collecteur.donnees.EtatLocal
import com.microfinance.collecteur.donnees.OperationEntite
import com.microfinance.collecteur.impression.AppareilAppaire
import com.microfinance.collecteur.impression.ImprimanteBluetooth
import com.microfinance.collecteur.impression.RecuEscPos
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class RecuUiState(
    val operation: OperationEntite? = null,
    val lignes: List<String> = emptyList(),
    val imprimantes: List<AppareilAppaire> = emptyList(),
    val imprimante: String? = null,
    val permission: Boolean = true,
    val impressionEnCours: Boolean = false,
    val message: String? = null,
)

@HiltViewModel
class RecuViewModel @Inject constructor(
    private val depot: DepotCollecte,
    private val etat: EtatLocal,
    private val bluetooth: ImprimanteBluetooth,
    etatNavigation: SavedStateHandle,
) : ViewModel() {
    private val cle = checkNotNull(etatNavigation.get<String>("cle"))
    private val _uiState = MutableStateFlow(RecuUiState())
    val uiState: StateFlow<RecuUiState> = _uiState.asStateFlow()

    init {
        actualiser()
    }

    fun actualiser() {
        viewModelScope.launch {
            val operation = depot.operation(cle)
            val collecteur = etat.email().orEmpty().substringBefore('@')
            _uiState.update {
                it.copy(
                    operation = operation,
                    lignes = operation?.let { op -> RecuEscPos.lignes(op, collecteur) }.orEmpty(),
                    imprimantes = bluetooth.appaires(),
                    imprimante = etat.imprimante(),
                    permission = bluetooth.permissionAccordee(),
                )
            }
        }
    }

    fun choisir(adresse: String) {
        viewModelScope.launch { etat.choisirImprimante(adresse) }
        _uiState.update { it.copy(imprimante = adresse) }
    }

    fun imprimer() {
        val e = _uiState.value
        val operation = e.operation ?: return
        val adresse = e.imprimante ?: return
        _uiState.update { it.copy(impressionEnCours = true, message = null) }
        viewModelScope.launch {
            val resultat = bluetooth.imprimer(adresse, RecuEscPos.octets(operation, etat.email().orEmpty().substringBefore('@')))
            _uiState.update {
                it.copy(impressionEnCours = false, message = resultat.fold({ "Reçu imprimé." }, { e -> "Impression impossible : ${e.message}" }))
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranRecu(onTerminer: () -> Unit, viewModel: RecuViewModel = hiltViewModel()) {
    val etat by viewModel.uiState.collectAsStateWithLifecycle()
    val demande = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { viewModel.actualiser() }

    Scaffold(topBar = { TopAppBar(title = { Text("Reçu provisoire") }) }) { marges ->
        Formulaire(marges) {
            Card(Modifier.fillMaxWidth()) {
                etat.lignes.forEach { Text(it, fontFamily = FontFamily.Monospace, style = MaterialTheme.typography.bodySmall) }
            }
            if (!etat.permission && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                OutlinedButton(onClick = { demande.launch(Manifest.permission.BLUETOOTH_CONNECT) }) { Text("Autoriser le Bluetooth") }
            }
            if (etat.permission && etat.imprimantes.isEmpty()) {
                Text("Aucune imprimante appairée : appairez-la dans les réglages Bluetooth du téléphone.", style = MaterialTheme.typography.bodyMedium)
            }
            etat.imprimantes.forEach { imprimante ->
                Row(Modifier.fillMaxWidth().selectable(selected = etat.imprimante == imprimante.adresse, onClick = { viewModel.choisir(imprimante.adresse) }),
                    verticalAlignment = Alignment.CenterVertically) {
                    RadioButton(selected = etat.imprimante == imprimante.adresse, onClick = null)
                    Text(imprimante.nom)
                }
            }
            etat.message?.let { Text(it, style = MaterialTheme.typography.bodyMedium) }
            Button(onClick = viewModel::imprimer, enabled = etat.imprimante != null && !etat.impressionEnCours, modifier = Modifier.fillMaxWidth()) {
                Text(if (etat.impressionEnCours) "Impression…" else "Imprimer")
            }
            OutlinedButton(onClick = onTerminer, modifier = Modifier.fillMaxWidth()) { Text("Terminer") }
        }
    }
}
