package com.microfinance.membre.ui

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
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.microfinance.membre.donnees.DepotMembre
import com.microfinance.membre.donnees.EtatMembre
import com.microfinance.reseau.ErreurApi
import com.microfinance.reseau.Resultat
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

enum class Suite { Accueil, ChangerMotDePasse }

data class ConnexionUiState(
    val organisation: String = "",
    val numero: String = "",
    val motDePasse: String = "",
    val enCours: Boolean = false,
    val erreur: String? = null,
    val suite: Suite? = null,
) {
    val peutValider: Boolean get() = !enCours && organisation.isNotBlank() && numero.isNotBlank() && motDePasse.isNotBlank()
}

@HiltViewModel
class ConnexionViewModel @Inject constructor(private val depot: DepotMembre, etat: EtatMembre) : ViewModel() {
    private val _uiState = MutableStateFlow(ConnexionUiState())
    val uiState: StateFlow<ConnexionUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch { etat.identifiants().let { (o, n) -> _uiState.update { it.copy(organisation = o, numero = n) } } }
    }

    fun organisation(v: String) = _uiState.update { it.copy(organisation = v, erreur = null) }
    fun numero(v: String) = _uiState.update { it.copy(numero = v, erreur = null) }
    fun motDePasse(v: String) = _uiState.update { it.copy(motDePasse = v, erreur = null) }

    fun valider() {
        val e = _uiState.value
        if (!e.peutValider) return
        _uiState.update { it.copy(enCours = true) }
        viewModelScope.launch {
            when (val r = depot.connecter(e.organisation, e.numero, e.motDePasse)) {
                is Resultat.Echec -> _uiState.update { it.copy(enCours = false, erreur = r.erreur.message) }
                is Resultat.Succes -> _uiState.update { it.copy(enCours = false, motDePasse = "", suite = if (r.valeur) Suite.ChangerMotDePasse else Suite.Accueil) }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranConnexion(onSuite: (Suite) -> Unit, viewModel: ConnexionViewModel = hiltViewModel()) {
    val etat by viewModel.uiState.collectAsStateWithLifecycle()
    LaunchedEffect(etat.suite) { etat.suite?.let(onSuite) }
    val majuscules = KeyboardOptions(capitalization = KeyboardCapitalization.Characters)

    Scaffold(topBar = { TopAppBar(title = { Text("Mon épargne") }) }) { marges ->
        Formulaire(marges) {
            Text("Identifiants remis au guichet de votre coopérative.", style = MaterialTheme.typography.bodyMedium)
            OutlinedTextField(etat.organisation, viewModel::organisation, label = { Text("Code de l'organisation") }, singleLine = true,
                keyboardOptions = majuscules, modifier = Modifier.fillMaxWidth())
            OutlinedTextField(etat.numero, viewModel::numero, label = { Text("Numéro de membre") }, singleLine = true,
                keyboardOptions = majuscules, modifier = Modifier.fillMaxWidth())
            OutlinedTextField(etat.motDePasse, viewModel::motDePasse, label = { Text("Mot de passe") }, singleLine = true,
                visualTransformation = PasswordVisualTransformation(), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                modifier = Modifier.fillMaxWidth())
            MessageErreur(etat.erreur)
            Button(onClick = viewModel::valider, enabled = etat.peutValider, modifier = Modifier.fillMaxWidth()) {
                Text(if (etat.enCours) "Connexion…" else "Se connecter")
            }
        }
    }
}

data class MotDePasseUiState(
    val actuel: String = "",
    val nouveau: String = "",
    val confirmation: String = "",
    val enCours: Boolean = false,
    val erreur: String? = null,
    val termine: Boolean = false,
) {
    val peutValider: Boolean get() = !enCours && actuel.isNotBlank() && nouveau.length >= 8 && nouveau == confirmation
}

@HiltViewModel
class MotDePasseViewModel @Inject constructor(private val depot: DepotMembre) : ViewModel() {
    private val _uiState = MutableStateFlow(MotDePasseUiState())
    val uiState: StateFlow<MotDePasseUiState> = _uiState.asStateFlow()

    fun actuel(v: String) = _uiState.update { it.copy(actuel = v, erreur = null) }
    fun nouveau(v: String) = _uiState.update { it.copy(nouveau = v, erreur = null) }
    fun confirmation(v: String) = _uiState.update { it.copy(confirmation = v, erreur = null) }

    fun valider() {
        val e = _uiState.value
        if (!e.peutValider) return
        _uiState.update { it.copy(enCours = true) }
        viewModelScope.launch {
            when (val r = depot.changerMotDePasse(e.actuel, e.nouveau, e.confirmation)) {
                is Resultat.Echec -> _uiState.update {
                    val message = (r.erreur as? ErreurApi.Validation)?.let { v -> v.champs["actuel"] ?: v.champs["nouveau"] } ?: r.erreur.message
                    it.copy(enCours = false, erreur = message)
                }
                is Resultat.Succes -> _uiState.update { it.copy(enCours = false, termine = true) }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranMotDePasse(onTermine: () -> Unit, viewModel: MotDePasseViewModel = hiltViewModel()) {
    val etat by viewModel.uiState.collectAsStateWithLifecycle()
    LaunchedEffect(etat.termine) { if (etat.termine) onTermine() }
    val masque = PasswordVisualTransformation()

    Scaffold(topBar = { TopAppBar(title = { Text("Choisissez votre mot de passe") }) }) { marges ->
        Formulaire(marges) {
            Text("Remplacez le mot de passe provisoire : 8 caractères au moins, avec des lettres et des chiffres.", style = MaterialTheme.typography.bodyMedium)
            OutlinedTextField(etat.actuel, viewModel::actuel, label = { Text("Mot de passe provisoire") }, singleLine = true, visualTransformation = masque, modifier = Modifier.fillMaxWidth())
            OutlinedTextField(etat.nouveau, viewModel::nouveau, label = { Text("Nouveau mot de passe") }, singleLine = true, visualTransformation = masque, modifier = Modifier.fillMaxWidth())
            OutlinedTextField(etat.confirmation, viewModel::confirmation, label = { Text("Confirmez") }, singleLine = true, visualTransformation = masque, modifier = Modifier.fillMaxWidth())
            MessageErreur(etat.erreur)
            Button(onClick = viewModel::valider, enabled = etat.peutValider, modifier = Modifier.fillMaxWidth()) { Text("Enregistrer") }
        }
    }
}
