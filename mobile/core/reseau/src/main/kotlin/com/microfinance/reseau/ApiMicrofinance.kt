package com.microfinance.reseau

import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query
import retrofit2.http.Streaming

/** API `/api/v1` (docs/api/openapi.json du serveur). */
interface ApiMicrofinance {
    @POST("auth/jetons")
    suspend fun jetonPersonnel(@Body demande: DemandeJetonPersonnel): Response<ReponseJeton>

    @POST("auth/membre")
    suspend fun jetonMembre(@Body demande: DemandeJetonMembre): Response<ReponseJeton>

    @POST("auth/deconnexion")
    suspend fun deconnexion(): Response<Unit>

    @POST("moi/mot-de-passe")
    suspend fun changerMotDePasse(@Body changement: ChangementMotDePasse): Response<Unit>

    @GET("membre/comptes")
    suspend fun comptes(): Response<Liste<CompteMembre>>

    @GET("membre/comptes/{compte}/mouvements")
    suspend fun mouvements(@Path("compte") compte: Long, @Query("depuis") depuis: String? = null): Response<Liste<Mouvement>>

    @GET("membre/prets")
    suspend fun prets(): Response<Liste<PretMembre>>

    @GET("membre/tontines")
    suspend fun tontines(): Response<Liste<TontineMembre>>

    @GET("membre/notifications")
    suspend fun notifications(@Query("depuis") depuis: String? = null): Response<PageNotifications>

    @POST("membre/notifications/jeton")
    suspend fun enregistrerJetonPush(@Body jeton: JetonPushDto): Response<Unit>

    @POST("membre/comptes/{compte}/releves")
    suspend fun demanderReleve(@Path("compte") compte: Long, @Body periode: PeriodeReleve): Response<ReponseReleve>

    @Streaming
    @GET("membre/releves/{releve}")
    suspend fun releve(@Path("releve") releve: Long): Response<ResponseBody>

    @GET("collecte/tournee")
    suspend fun tournee(@Query("depuis") depuis: String? = null): Response<Tournee>

    @POST("sync/lots")
    suspend fun synchroniser(@Body lot: LotSync): Response<ReponseLot>
}
