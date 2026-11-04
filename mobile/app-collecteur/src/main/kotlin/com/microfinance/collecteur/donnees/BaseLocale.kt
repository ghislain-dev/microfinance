package com.microfinance.collecteur.donnees

import androidx.room.Dao
import androidx.room.Database
import androidx.room.Entity
import androidx.room.Index
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.PrimaryKey
import androidx.room.Query
import androidx.room.RoomDatabase
import androidx.room.Transaction
import androidx.room.Upsert
import kotlinx.coroutines.flow.Flow

/** Membre de la tournée tel que reçu du serveur (`donnees` : MembreTournee en JSON). */
@Entity(tableName = "membres", indices = [Index("numero"), Index("nom")])
data class MembreEntite(
    @PrimaryKey val id: Long,
    val numero: String,
    val nom: String,
    val donnees: String,
)

/**
 * Opération saisie hors-ligne. `cle` (UUID) est générée à la saisie et rejouée telle quelle : le serveur
 * ne l'enregistre qu'une fois. Une ligne n'est jamais supprimée avant d'avoir une réponse du serveur.
 */
@Entity(tableName = "operations", indices = [Index("statut"), Index("saisiLe")])
data class OperationEntite(
    @PrimaryKey val cle: String,
    val type: String,
    val membreId: Long,
    val membreNumero: String,
    val membreNom: String,
    val compteEpargneId: Long?,
    val pretId: Long?,
    val cycleId: Long?,
    val devise: String,
    val montantCentimes: Long,
    val fondsSocialCentimes: Long?,
    val parts: Int?,
    val libelle: String,
    val saisiLe: String,
    val numeroProvisoire: String,
    val statut: String = EN_ATTENTE,
    val raison: String? = null,
    val recuOfficiel: String? = null,
) {
    companion object {
        const val EN_ATTENTE = "en_attente"
        const val ACCEPTEE = "acceptee"
        const val REFUSEE = "refusee"
    }
}

@Dao
interface MembresDao {
    @Query("SELECT * FROM membres ORDER BY numero")
    fun tous(): Flow<List<MembreEntite>>

    @Query("SELECT * FROM membres WHERE id = :id")
    fun parId(id: Long): Flow<MembreEntite?>

    @Upsert
    suspend fun enregistrer(membres: List<MembreEntite>)

    @Query("DELETE FROM membres WHERE id IN (:ids)")
    suspend fun retirer(ids: List<Long>)

    @Query("DELETE FROM membres")
    suspend fun vider()

    @Transaction
    suspend fun remplacer(membres: List<MembreEntite>) {
        vider()
        enregistrer(membres)
    }
}

@Dao
interface OperationsDao {
    @Insert(onConflict = OnConflictStrategy.ABORT)
    suspend fun ajouter(operation: OperationEntite)

    @Query("SELECT * FROM operations WHERE statut = 'en_attente' ORDER BY saisiLe")
    suspend fun enAttente(): List<OperationEntite>

    @Query("SELECT * FROM operations WHERE statut = 'en_attente' ORDER BY saisiLe")
    fun enAttenteFlux(): Flow<List<OperationEntite>>

    @Query("SELECT * FROM operations ORDER BY saisiLe DESC LIMIT 200")
    fun recentes(): Flow<List<OperationEntite>>

    @Query("SELECT * FROM operations WHERE cle = :cle")
    suspend fun parCle(cle: String): OperationEntite?

    @Query("UPDATE operations SET statut = :statut, raison = :raison, recuOfficiel = :recu WHERE cle = :cle")
    suspend fun repondre(cle: String, statut: String, raison: String?, recu: String?)

    @Query("SELECT COUNT(*) FROM operations")
    suspend fun nombre(): Int
}

@Database(entities = [MembreEntite::class, OperationEntite::class], version = 1, exportSchema = true)
abstract class BaseLocale : RoomDatabase() {
    abstract fun membres(): MembresDao

    abstract fun operations(): OperationsDao
}
