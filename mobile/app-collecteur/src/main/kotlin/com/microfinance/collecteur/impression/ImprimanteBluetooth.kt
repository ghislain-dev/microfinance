package com.microfinance.collecteur.impression

import android.Manifest
import android.annotation.SuppressLint
import android.bluetooth.BluetoothManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.content.ContextCompat
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.IOException
import java.util.UUID
import javax.inject.Inject
import javax.inject.Singleton

data class AppareilAppaire(val nom: String, val adresse: String)

/** Imprimante thermique appairée dans les réglages Bluetooth du téléphone (profil série SPP). */
@Singleton
class ImprimanteBluetooth @Inject constructor(@ApplicationContext private val contexte: Context) {
    private val adaptateur get() = contexte.getSystemService(BluetoothManager::class.java)?.adapter

    fun permissionAccordee(): Boolean = Build.VERSION.SDK_INT < Build.VERSION_CODES.S ||
        ContextCompat.checkSelfPermission(contexte, Manifest.permission.BLUETOOTH_CONNECT) == PackageManager.PERMISSION_GRANTED

    @SuppressLint("MissingPermission") // vérifiée par permissionAccordee()
    fun appaires(): List<AppareilAppaire> {
        if (!permissionAccordee()) return emptyList()
        return adaptateur?.bondedDevices.orEmpty().map { AppareilAppaire(it.name ?: it.address, it.address) }.sortedBy { it.nom }
    }

    @SuppressLint("MissingPermission")
    suspend fun imprimer(adresse: String, octets: ByteArray): Result<Unit> = withContext(Dispatchers.IO) {
        if (!permissionAccordee()) return@withContext Result.failure(SecurityException("Autorisez le Bluetooth pour imprimer."))
        val appareil = adaptateur?.getRemoteDevice(adresse) ?: return@withContext Result.failure(IOException("Bluetooth indisponible."))
        runCatching {
            appareil.createRfcommSocketToServiceRecord(SPP).use { prise ->
                adaptateur?.cancelDiscovery()
                prise.connect()
                prise.outputStream.write(octets)
                prise.outputStream.flush()
            }
        }
    }

    private companion object {
        val SPP: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
    }
}
