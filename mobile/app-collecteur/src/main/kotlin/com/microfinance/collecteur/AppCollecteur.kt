package com.microfinance.collecteur

import android.app.Application
import androidx.hilt.work.HiltWorkerFactory
import androidx.work.Configuration
import com.microfinance.collecteur.donnees.TravailSynchro
import dagger.hilt.android.HiltAndroidApp
import javax.inject.Inject

@HiltAndroidApp
class AppCollecteur : Application(), Configuration.Provider {
    @Inject lateinit var fabriqueTravaux: HiltWorkerFactory

    override val workManagerConfiguration: Configuration
        get() = Configuration.Builder().setWorkerFactory(fabriqueTravaux).build()

    override fun onCreate() {
        super.onCreate()
        TravailSynchro.planifier(this)
    }
}
