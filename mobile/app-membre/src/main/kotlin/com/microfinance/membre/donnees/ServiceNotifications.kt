package com.microfinance.membre.donnees

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.microfinance.membre.ActivitePrincipale
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch
import javax.inject.Inject

/** Avis du serveur (opérations, échéances, tour de tontine, prêt) et renouvellement du jeton Firebase. */
@AndroidEntryPoint
class ServiceNotifications : FirebaseMessagingService() {
    @Inject lateinit var depot: DepotMembre

    private val portee = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    override fun onNewToken(token: String) {
        portee.launch { depot.enregistrerJetonPush(token) }
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val avis = message.notification ?: return
        val gestionnaire = getSystemService(NotificationManager::class.java)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            gestionnaire.createNotificationChannel(NotificationChannel(CANAL, "Mon épargne", NotificationManager.IMPORTANCE_DEFAULT))
        }
        val ouverture = PendingIntent.getActivity(this, 0, Intent(this, ActivitePrincipale::class.java), PendingIntent.FLAG_IMMUTABLE)
        val notification = NotificationCompat.Builder(this, CANAL)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(avis.title)
            .setContentText(avis.body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(avis.body))
            .setContentIntent(ouverture)
            .setAutoCancel(true)
            .build()
        if (NotificationManagerCompat.from(this).areNotificationsEnabled()) {
            @Suppress("MissingPermission") // areNotificationsEnabled() vérifie l'autorisation
            NotificationManagerCompat.from(this).notify(message.messageId.hashCode(), notification)
        }
    }

    override fun onDestroy() {
        portee.cancel()
        super.onDestroy()
    }

    private companion object {
        const val CANAL = "avis"
    }
}
