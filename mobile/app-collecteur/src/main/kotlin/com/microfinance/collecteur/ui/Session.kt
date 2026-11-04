package com.microfinance.collecteur.ui

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.microfinance.collecteur.donnees.DepotSession
import com.microfinance.collecteur.donnees.EtapeConnexion
import com.microfinance.collecteur.donnees.EtatLocal
import com.microfinance.reseau.Resultat
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class ConnexionUiState(
    val email: String = "",
    val motDePasse: String = "",
    val code: String = "",
    val codeDemande: Boolean = false,
    val enCours: Boolean = false,
    val erreur: String? = null,
    val connecte: Boolean = false,
) {
    val peutValider: Boolean get() = !enCours && email.isNotBlank() && motDePasse.isNotBlank() && (!codeDemande || code.length == 6)
}

@HiltViewModel
class ConnexionViewModel @Inject constructor(private val session: DepotSession, etat: EtatLocal) : ViewModel() {
    private val _uiState = MutableStateFlow(ConnexionUiState())
    val uiState: StateFlow<ConnexionUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch { etat.email()?.let { email -> _uiState.update { it.copy(email = email) } } }
    }

    fun email(v: String) = _uiState.update { it.copy(email = v, erreur = null) }
    fun motDePasse(v: String) = _uiState.update { it.copy(motDePasse = v, erreur = null) }
    fun code(v: String) = _uiState.update { it.copy(code = v.filter(Char::isDigit).take(6), erreur = null) }

    fun valider() {
        val etat = _uiState.value
        if (!etat.peutValider) return
        _uiState.update { it.copy(enCours = true, erreur = null) }
        viewModelScope.launch {
            when (val r = session.connecter(etat.email, etat.motDePasse, etat.code.takeIf { etat.codeDemande })) {
                is Resultat.Echec -> _uiState.update { it.copy(enCours = false, erreur = session.message(r.erreur)) }
                is Resultat.Succes -> _uiState.update {
                    when (r.valeur) {
                        EtapeConnexion.CodeEnvoye -> it.copy(enCours = false, codeDemande = true, code = "")
                        EtapeConnexion.Connecte -> it.copy(enCours = false, connecte = true, motDePasse = "", code = "")
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranConnexion(onConnecte: () -> Unit, viewModel: ConnexionViewModel = hiltViewModel()) {
    val etat by viewModel.uiState.collectAsStateWithLifecycle()
    LaunchedEffect(etat.connecte) { if (etat.connecte) onConnecte() }

    Scaffold(topBar = { TopAppBar(title = { Text("Connexion du collecteur") }) }) { marges ->
        Formulaire(marges) {
            OutlinedTextField(etat.email, viewModel::email, label = { Text("Adresse e-mail") }, singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email), modifier = Modifier.fillMaxWidth())
            OutlinedTextField(etat.motDePasse, viewModel::motDePasse, label = { Text("Mot de passe") }, singleLine = true,
                visualTransformation = PasswordVisualTransformation(), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                modifier = Modifier.fillMaxWidth())
            if (etat.codeDemande) {
                Text("Un code à 6 chiffres vous a été envoyé.", style = MaterialTheme.typography.bodyMedium)
                OutlinedTextField(etat.code, viewModel::code, label = { Text("Code reçu") }, singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword), modifier = Modifier.fillMaxWidth())
            }
            MessageErreur(etat.erreur)
            Button(onClick = viewModel::valider, enabled = etat.peutValider, modifier = Modifier.fillMaxWidth()) {
                Text(if (etat.enCours) "Connexion…" else if (etat.codeDemande) "Valider le code" else "Se connecter")
            }
        }
    }
}

data class PinUiState(
    val creation: Boolean = false,
    val pin: String = "",
    val confirmation: String = "",
    val essais: Int = 0,
    val erreur: String? = null,
    val deverrouille: Boolean = false,
    val reconnexion: Boolean = false,
) {
    val peutValider: Boolean get() = pin.length in 4..6 && (!creation || confirmation.length == pin.length)
}

@HiltViewModel
class PinViewModel @Inject constructor(private val etat: EtatLocal) : ViewModel() {
    private val _uiState = MutableStateFlow(PinUiState())
    val uiState: StateFlow<PinUiState> = _uiState.asStateFlow()

    fun preparer(creation: Boolean) = _uiState.update { it.copy(creation = creation) }
    fun pin(v: String) = _uiState.update { it.copy(pin = v.filter(Char::isDigit).take(6), erreur = null) }
    fun confirmation(v: String) = _uiState.update { it.copy(confirmation = v.filter(Char::isDigit).take(6), erreur = null) }

    fun valider() {
        val e = _uiState.value
        if (!e.peutValider) return
        viewModelScope.launch {
            when {
                e.creation && e.pin != e.confirmation -> _uiState.update { it.copy(erreur = "Les deux codes ne correspondent pas.", confirmation = "") }
                e.creation -> { etat.definirPin(e.pin); _uiState.update { it.copy(deverrouille = true) } }
                etat.verifierPin(e.pin) -> _uiState.update { it.copy(deverrouille = true) }
                e.essais + 1 >= ESSAIS_MAX -> { etat.oublierJeton(); _uiState.update { it.copy(reconnexion = true) } }
                else -> _uiState.update { it.copy(pin = "", essais = it.essais + 1, erreur = "Code incorrect (${ESSAIS_MAX - it.essais - 1} essai(s) restant(s)).") }
            }
        }
    }

    private companion object {
        const val ESSAIS_MAX = 5
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranPin(creation: Boolean, onDeverrouille: () -> Unit, onReconnexion: () -> Unit, viewModel: PinViewModel = hiltViewModel()) {
    val etat by viewModel.uiState.collectAsStateWithLifecycle()
    LaunchedEffect(creation) { viewModel.preparer(creation) }
    LaunchedEffect(etat.deverrouille, etat.reconnexion) {
        if (etat.deverrouille) onDeverrouille()
        if (etat.reconnexion) onReconnexion()
    }

    Scaffold(topBar = { TopAppBar(title = { Text(if (creation) "Choisissez votre code PIN" else "Code PIN") }) }) { marges ->
        Formulaire(marges) {
            val clavier = KeyboardOptions(keyboardType = KeyboardType.NumberPassword)
            OutlinedTextField(etat.pin, viewModel::pin, label = { Text("Code PIN (4 à 6 chiffres)") }, singleLine = true,
                visualTransformation = PasswordVisualTransformation(), keyboardOptions = clavier, modifier = Modifier.fillMaxWidth())
            if (creation) {
                OutlinedTextField(etat.confirmation, viewModel::confirmation, label = { Text("Confirmez le code") }, singleLine = true,
                    visualTransformation = PasswordVisualTransformation(), keyboardOptions = clavier, modifier = Modifier.fillMaxWidth())
            }
            MessageErreur(etat.erreur)
            Button(onClick = viewModel::valider, enabled = etat.peutValider, modifier = Modifier.fillMaxWidth()) { Text("Valider") }
        }
    }
}
