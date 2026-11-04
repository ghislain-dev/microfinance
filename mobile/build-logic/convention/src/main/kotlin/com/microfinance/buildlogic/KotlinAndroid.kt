package com.microfinance.buildlogic

import com.android.build.api.dsl.CommonExtension
import org.gradle.api.JavaVersion
import org.gradle.api.Project
import org.gradle.api.plugins.JavaPluginExtension
import org.gradle.kotlin.dsl.configure
import org.gradle.kotlin.dsl.dependencies
import org.jetbrains.kotlin.gradle.dsl.KotlinAndroidProjectExtension
import org.jetbrains.kotlin.gradle.dsl.KotlinJvmProjectExtension

/** Shared Android + Kotlin configuration for application and library modules. */
internal fun Project.configureKotlinAndroid(commonExtension: CommonExtension) {
    val javaVersion = JavaVersion.toVersion(libs.intVersion("jvmToolchain"))

    commonExtension.apply {
        compileSdk = libs.intVersion("compileSdk")
        defaultConfig.apply {
            minSdk = libs.intVersion("minSdk")
            testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
        }
        compileOptions.apply {
            sourceCompatibility = javaVersion
            targetCompatibility = javaVersion
            // java.time on minSdk 24 (Android 7): APIs are backported by desugar_jdk_libs.
            isCoreLibraryDesugaringEnabled = true
        }
        lint.apply {
            abortOnError = true
            checkDependencies = false
            warningsAsErrors = false
        }
    }

    extensions.configure<KotlinAndroidProjectExtension> {
        jvmToolchain(libs.intVersion("jvmToolchain"))
    }

    dependencies {
        add("coreLibraryDesugaring", libs.findLibrary("android-desugarJdkLibs").get())
    }

    addCommonTestDependencies()
}

/** Configuration for pure Kotlin/JVM modules. */
internal fun Project.configureKotlinJvm() {
    val toolchain = libs.intVersion("jvmToolchain")
    extensions.configure<JavaPluginExtension> {
        sourceCompatibility = JavaVersion.toVersion(toolchain)
        targetCompatibility = JavaVersion.toVersion(toolchain)
    }
    extensions.configure<KotlinJvmProjectExtension> {
        jvmToolchain(toolchain)
    }
    addCommonTestDependencies()
}

private fun Project.addCommonTestDependencies() {
    dependencies {
        add("testImplementation", libs.findLibrary("junit4").get())
        add("testImplementation", libs.findLibrary("kotlinx-coroutines-test").get())
        add("testImplementation", libs.findLibrary("turbine").get())
        add("testImplementation", libs.findLibrary("mockk").get())
    }
}
