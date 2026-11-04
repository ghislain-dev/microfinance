package com.microfinance.membre.donnees

import com.microfinance.reseau.ConfigurationApi
import com.microfinance.securite.Chiffrement
import dagger.Binds
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
abstract class ModuleMembre {
    @Binds
    abstract fun configuration(etat: EtatMembre): ConfigurationApi

    companion object {
        @Provides
        @Singleton
        fun chiffrement(): Chiffrement = Chiffrement("microfinance-membre")
    }
}
