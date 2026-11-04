package com.microfinance.collecteur.ui

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val Vert = Color(0xFF1B6B4A)

@Composable
fun ThemeCollecte(contenu: @Composable () -> Unit) {
    val couleurs = if (isSystemInDarkTheme()) darkColorScheme(primary = Color(0xFF7FD8A8)) else lightColorScheme(primary = Vert)
    MaterialTheme(colorScheme = couleurs, content = contenu)
}
