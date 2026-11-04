package com.microfinance.collecteur.donnees

import android.content.Context
import androidx.room.Room
import com.microfinance.reseau.ConfigurationApi
import com.microfinance.securite.Chiffrement
import dagger.Binds
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import java.time.Clock
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
abstract class ModuleCollecteur {
    @Binds
    abstract fun configuration(etat: EtatLocal): ConfigurationApi

    companion object {
        // Pas de migration destructive : les opérations en attente sont de l'argent déjà reçu.
        @Provides
        @Singleton
        fun base(@ApplicationContext contexte: Context): BaseLocale =
            Room.databaseBuilder(contexte, BaseLocale::class.java, "collecte.db").build()

        @Provides
        fun horloge(): Clock = Clock.systemUTC()

        @Provides
        @Singleton
        fun chiffrement(): Chiffrement = Chiffrement("microfinance-collecteur")
    }
}
