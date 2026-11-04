plugins {
    alias(libs.plugins.microfinance.android.application)
    alias(libs.plugins.microfinance.android.application.compose)
    alias(libs.plugins.microfinance.android.hilt)
    alias(libs.plugins.microfinance.android.room)
    alias(libs.plugins.kotlin.serialization)
}

android {
    namespace = "com.microfinance.collecteur"

    defaultConfig {
        applicationId = "com.microfinance.collecteur"
        versionCode = providers.gradleProperty("microfinance.versionCode").get().toInt()
        versionName = providers.gradleProperty("microfinance.versionName").get()
        buildConfigField("String", "API_URL", "\"${providers.gradleProperty("microfinance.apiUrl").get()}\"")
    }

    buildFeatures.buildConfig = true

    buildTypes {
        release {
            isMinifyEnabled = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }
}

dependencies {
    implementation(projects.core.reseau)
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.compose.material3)
    implementation(libs.androidx.compose.ui)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.navigation.compose)
    implementation(libs.androidx.hilt.lifecycle.viewmodel.compose)
    implementation(libs.androidx.hilt.work)
    ksp(libs.androidx.hilt.compiler)
    implementation(libs.androidx.work.runtime.ktx)
    implementation(libs.androidx.dataStore.preferences)
    implementation(libs.kotlinx.coroutines.android)
}
