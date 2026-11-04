package com.microfinance.reseau

import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.util.concurrent.TimeUnit
import javax.inject.Singleton

/** Fourni par chaque application : adresse du serveur et lecture du jeton enregistré. */
interface ConfigurationApi {
    val urlDeBase: String

    fun jeton(): String?
}

@Module
@InstallIn(SingletonComponent::class)
object ModuleReseau {
    @Provides
    @Singleton
    fun json(): Json = Json {
        ignoreUnknownKeys = true // le serveur peut ajouter des champs sans casser les anciennes versions
        explicitNulls = false
    }

    @Provides
    @Singleton
    fun client(configuration: ConfigurationApi): OkHttpClient = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(60, TimeUnit.SECONDS)
        .addInterceptor { chaine ->
            val requete = chaine.request().newBuilder().header("Accept", "application/json")
            configuration.jeton()?.let { requete.header("Authorization", "Bearer $it") }
            chaine.proceed(requete.build())
        }
        .build()

    @Provides
    @Singleton
    fun api(configuration: ConfigurationApi, client: OkHttpClient, json: Json): ApiMicrofinance = Retrofit.Builder()
        .baseUrl(configuration.urlDeBase.trimEnd('/') + "/")
        .client(client)
        .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
        .build()
        .create(ApiMicrofinance::class.java)
}
