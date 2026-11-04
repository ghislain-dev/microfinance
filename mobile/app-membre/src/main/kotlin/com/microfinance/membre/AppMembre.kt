package com.microfinance.membre

import android.app.Application
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class AppMembre : Application() {
    override fun onCreate() {
        super.onCreate()
        // Sans identifiants Firebase (non fournis au build), l'application fonctionne sans notifications.
        if (BuildConfig.FIREBASE_APPLICATION.isNotBlank() && FirebaseApp.getApps(this).isEmpty()) {
            FirebaseApp.initializeApp(this, FirebaseOptions.Builder()
                .setProjectId(BuildConfig.FIREBASE_PROJET)
                .setApplicationId(BuildConfig.FIREBASE_APPLICATION)
                .setApiKey(BuildConfig.FIREBASE_CLE)
                .setGcmSenderId(BuildConfig.FIREBASE_EXPEDITEUR)
                .build())
        }
    }
}
