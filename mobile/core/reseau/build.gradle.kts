plugins {
    alias(libs.plugins.microfinance.android.library)
    alias(libs.plugins.microfinance.android.hilt)
    alias(libs.plugins.kotlin.serialization)
}

android {
    namespace = "com.microfinance.reseau"
}

dependencies {
    api(projects.core.modele)
    api(libs.kotlinx.serialization.json)
    api(libs.kotlinx.coroutines.core)
    implementation(platform(libs.okhttp.bom))
    implementation(libs.okhttp)
    api(libs.retrofit.core)
    implementation(libs.retrofit.kotlinx.serialization)

    testImplementation(platform(libs.okhttp.bom))
    testImplementation(libs.okhttp.mockwebserver)
}
