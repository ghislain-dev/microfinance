package com.microfinance.collecteur

import android.os.Bundle
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import com.microfinance.collecteur.ui.NavigationCollecte
import com.microfinance.collecteur.ui.ThemeCollecte
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class ActivitePrincipale : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        // Soldes et reçus à l'écran : pas de capture ni d'aperçu dans les applications récentes.
        window.setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE)
        enableEdgeToEdge()
        setContent { ThemeCollecte { NavigationCollecte() } }
    }
}
