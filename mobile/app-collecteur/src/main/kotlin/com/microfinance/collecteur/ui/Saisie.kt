package com.microfinance.collecteur.ui

import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewModelScope
import com.microfinance.collecteur.domaine.TypeOperation
import com.microfinance.collecteur.donnees.DepotCollecte
import com.microfinance.collecteur.donnees.ResultatSaisie
import com.microfinance.collecteur.donnees.Saisie
import com.microfinance.modele.Montant
import com.microfinance.reseau.MembreTournee
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.filterNotNull
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** Un choix possible pour l'opération : compte, cycle de tontine ou prêt. */
data class Cible(val id: Long, val libelle: String, val devise: String)

data class SaisieUiState(
    val type: TypeOperation = TypeOperation.Depot,
    val membre: MembreTournee? = null,
    val cibles: List<Cible> = emptyList(),
    val cible: Long? = null,
    val montant: String = "",
    val fondsSocial: String = "",
    val parts: String = "",
    val enCours: Boolean = false,
    val erreur: String? = null,
    val enregistree: String? = null, // clé de l'opération, pour le reçu
) {
    private val tontine get() = membre?.tontines?.firstOrNull { it.cycleId == cible }
    val avec: Boolean get() = type == TypeOperation.Cotisation && tontine?.type == "avec"
    val devise: String? get() = cibles.firstOrNull { it.id == cible }?.devise
    val peutValider: Boolean get() = !enCours && cible != null && (if (avec) parts.toIntOrNull() != null else montant.isNotBlank())
}

@HiltViewModel
class SaisieViewModel @Inject constructor(private val depot: DepotCollecte, etatNavigation: SavedStateHandle) : ViewModel() {
    private val _uiState = MutableStateFlow(SaisieUiState(type = TypeOperation.valueOf(checkNotNull(etatNavigation.get<String>("type")))))
    val uiState: StateFlow<SaisieUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            val membre = depot.membre(checkNotNull(etatNavigation.get<Long>("membre"))).filterNotNull().first()
            val type = _uiState.value.type
            val cibles = when (type) {
                TypeOperation.Depot, TypeOperation.Retrait -> membre.comptes.filter { type == TypeOperation.Depot || !it.bloque }
                    .map { Cible(it.id, "${it.numero} (disponible ${it.disponible.formate()})", it.devise) }
                TypeOperation.Cotisation -> membre.tontines.filter { it.prochaineSeance?.dejaPaye == false }
                    .map { Cible(it.cycleId, "${it.groupe} · séance ${it.prochaineSeance?.rang}", it.devise) }
                TypeOperation.Remboursement -> membre.prets.map { Cible(it.id, "${it.numero} (dû ${it.duACeJour.formate()})", it.devise) }
            }
            _uiState.update { it.copy(membre = membre, cibles = cibles) }
            cibles.singleOrNull()?.let { choisir(it.id) }
        }
    }

    fun choisir(id: Long) = _uiState.update { etat ->
        val membre = etat.membre
        val tontine = membre?.tontines?.firstOrNull { it.cycleId == id }
        val pret = membre?.prets?.firstOrNull { it.id == id }
        etat.copy(
            cible = id, erreur = null,
            montant = when (etat.type) {
                TypeOperation.Cotisation -> tontine?.cotisation?.let(::saisie).orEmpty()
                TypeOperation.Remboursement -> pret?.duACeJour?.takeIf { it.estPositif }?.let(::saisie).orEmpty()
                else -> ""
            },
            fondsSocial = tontine?.fondsSocial?.let(::saisie).orEmpty(),
            parts = tontine?.partsMin?.toString().orEmpty(),
        )
    }

    fun montant(v: String) = _uiState.update { it.copy(montant = v, erreur = null) }
    fun fondsSocial(v: String) = _uiState.update { it.copy(fondsSocial = v, erreur = null) }
    fun parts(v: String) = _uiState.update { it.copy(parts = v.filter(Char::isDigit).take(2), erreur = null) }

    fun valider() {
        val e = _uiState.value
        val membre = e.membre ?: return
        val devise = e.devise ?: return
        val saisie = construire(e, membre, devise) ?: return
        _uiState.update { it.copy(enCours = true) }
        viewModelScope.launch {
            when (val r = depot.saisir(saisie)) {
                is ResultatSaisie.Refusee -> _uiState.update { it.copy(enCours = false, erreur = r.refus.message) }
                is ResultatSaisie.Enregistree -> _uiState.update { it.copy(enCours = false, enregistree = r.operation.cle) }
            }
        }
    }

    private fun construire(e: SaisieUiState, membre: MembreTournee, devise: String): Saisie? {
        val erreur = { message: String -> _uiState.update { it.copy(erreur = message) }; null }
        val cible = e.cibles.first { it.id == e.cible }
        return when (e.type) {
            TypeOperation.Depot, TypeOperation.Retrait -> {
                val montant = Montant.depuisSaisie(e.montant, devise)?.takeIf { it.estPositif } ?: return erreur("Montant invalide (ex. 1250,50).")
                Saisie(e.type, membre, montant, compteEpargneId = cible.id, libelle = "${e.type.libelle} — ${cible.libelle.substringBefore(" (")}")
            }
            TypeOperation.Remboursement -> {
                val pret = membre.prets.first { it.id == cible.id }
                val montant = Montant.depuisSaisie(e.montant, devise)?.takeIf { it.estPositif } ?: return erreur("Montant invalide (ex. 1250,50).")
                if (montant > pret.resteDu) return erreur("Le prêt ne doit plus que ${pret.resteDu.formate()}.")
                Saisie(e.type, membre, montant, pretId = pret.id, libelle = "Remboursement — prêt ${pret.numero}")
            }
            TypeOperation.Cotisation -> {
                val tontine = membre.tontines.first { it.cycleId == cible.id }
                val fs = Montant.depuisSaisie(e.fondsSocial.ifBlank { "0" }, devise) ?: return erreur("Fonds social invalide.")
                if (fs > tontine.fondsSocial) return erreur("Fonds social : au plus ${tontine.fondsSocial.formate()}.")
                if (e.avec) {
                    val parts = e.parts.toIntOrNull()?.takeIf { it in 1..(tontine.partsMax ?: 1) } ?: return erreur("De 1 à ${tontine.partsMax} parts.")
                    val prix = tontine.prixPart ?: return erreur("Prix de la part inconnu : synchronisez.")
                    Saisie(e.type, membre, Montant(prix.centimes * parts, devise), cycleId = tontine.cycleId, fondsSocial = fs, parts = parts,
                        libelle = "Parts AVEC — ${tontine.groupe}")
                } else {
                    val montant = Montant.depuisSaisie(e.montant.ifBlank { "0" }, devise) ?: return erreur("Montant invalide.")
                    val attendu = tontine.cotisation ?: return erreur("Cotisation inconnue : synchronisez.")
                    if (montant > attendu) return erreur("Cotisation : au plus ${attendu.formate()}.")
                    if (!montant.estPositif && !fs.estPositif) return erreur("Rien à encaisser.")
                    Saisie(e.type, membre, montant, cycleId = tontine.cycleId, fondsSocial = fs, libelle = "Cotisation — ${tontine.groupe}")
                }
            }
        }
    }

    private fun saisie(m: Montant) = m.formate().substringBeforeLast(' ').replace(" ", "")
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EcranSaisie(onEnregistree: (String) -> Unit, viewModel: SaisieViewModel = hiltViewModel()) {
    val etat by viewModel.uiState.collectAsStateWithLifecycle()
    LaunchedEffect(etat.enregistree) { etat.enregistree?.let(onEnregistree) }

    Scaffold(topBar = { TopAppBar(title = { Text("${etat.type.libelle} · ${etat.membre?.nom.orEmpty()}") }) }) { marges ->
        Formulaire(marges) {
            if (etat.cibles.isEmpty()) Text("Rien à saisir pour ce membre.", style = MaterialTheme.typography.bodyMedium)
            etat.cibles.forEach { cible ->
                Row(Modifier.fillMaxWidth().selectable(selected = etat.cible == cible.id, onClick = { viewModel.choisir(cible.id) }),
                    verticalAlignment = Alignment.CenterVertically) {
                    RadioButton(selected = etat.cible == cible.id, onClick = null)
                    Text(cible.libelle)
                }
            }
            val clavier = KeyboardOptions(keyboardType = KeyboardType.Decimal)
            if (etat.avec) {
                OutlinedTextField(etat.parts, viewModel::parts, label = { Text("Nombre de parts") }, singleLine = true,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), modifier = Modifier.fillMaxWidth())
            } else {
                OutlinedTextField(etat.montant, viewModel::montant, label = { Text("Montant (${etat.devise.orEmpty()})") }, singleLine = true,
                    keyboardOptions = clavier, modifier = Modifier.fillMaxWidth())
            }
            if (etat.type == TypeOperation.Cotisation) {
                OutlinedTextField(etat.fondsSocial, viewModel::fondsSocial, label = { Text("Fonds social") }, singleLine = true,
                    keyboardOptions = clavier, modifier = Modifier.fillMaxWidth())
            }
            MessageErreur(etat.erreur)
            Button(onClick = viewModel::valider, enabled = etat.peutValider, modifier = Modifier.fillMaxWidth()) { Text("Enregistrer et imprimer le reçu") }
        }
    }
}
