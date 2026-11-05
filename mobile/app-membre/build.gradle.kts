plugins {
    alias(libs.plugins.microfinance.android.application)
    alias(libs.plugins.microfinance.android.application.compose)
    alias(libs.plugins.microfinance.android.hilt)
    alias(libs.plugins.kotlin.serialization)
}

// Firebase (notifications push) : identifiants du projet dans ~/.gradle/gradle.properties, jamais dans le dépôt.
// Vides : l'application fonctionne sans notifications.
fun propriete(nom: String): String = providers.gradleProperty(nom).getOrElse("")

android {
    namespace = "com.microfinance.membre"

    defaultConfig {
        applicationId = "com.microfinance.membre"
        versionCode = propriete("microfinance.versionCode").toInt()
        versionName = propriete("microfinance.versionName")
        buildConfigField("String", "API_URL", "\"${propriete("microfinance.apiUrl")}\"")
        buildConfigField("String", "FIREBASE_PROJET", "\"${propriete("microfinance.firebase.projet")}\"")
        buildConfigField("String", "FIREBASE_APPLICATION", "\"${propriete("microfinance.firebase.application")}\"")
        buildConfigField("String", "FIREBASE_CLE", "\"${propriete("microfinance.firebase.cle")}\"")
        buildConfigField("String", "FIREBASE_EXPEDITEUR", "\"${propriete("microfinance.firebase.expediteur")}\"")
    }

    buildFeatures.buildConfig = true
    testOptions.unitTests.isIncludeAndroidResources = true

    buildTypes {
        release {
            isMinifyEnabled = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }
}

dependencies {
    implementation(projects.core.reseau)
    implementation(projects.core.securite)
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.compose.material3)
    implementation(libs.androidx.compose.ui)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.navigation.compose)
    implementation(libs.androidx.hilt.lifecycle.viewmodel.compose)
    implementation(libs.androidx.dataStore.preferences)
    implementation(libs.kotlinx.coroutines.android)
    implementation(platform(libs.firebase.bom))
    implementation(libs.firebase.messaging)

    testImplementation(libs.robolectric)
    testImplementation(platform(libs.androidx.compose.bom))
    testImplementation(libs.androidx.compose.ui.test.junit4)
}
