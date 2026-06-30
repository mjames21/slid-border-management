package com.slid.borderreporting.dynamic.location

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Bundle
import android.os.Looper
import androidx.core.content.ContextCompat
import kotlin.coroutines.resume
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull

data class DeviceGeoPoint(
    val latitude: Double,
    val longitude: Double,
    val accuracyMeters: Float?,
    val capturedAt: Long
)

interface DeviceLocationProvider {
    suspend fun currentLocation(): DeviceGeoPoint?
}

class AndroidDeviceLocationProvider(
    private val context: Context
) : DeviceLocationProvider {
    override suspend fun currentLocation(): DeviceGeoPoint? {
        if (!hasLocationPermission()) return null

        val locationManager = context.getSystemService(LocationManager::class.java) ?: return null
        return latestKnownLocation(locationManager)?.toGeoPoint()
            ?: requestSingleLocation(locationManager)
    }

    private fun hasLocationPermission(): Boolean {
        val fine = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION)
        val coarse = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION)

        return fine == PackageManager.PERMISSION_GRANTED || coarse == PackageManager.PERMISSION_GRANTED
    }

    @SuppressLint("MissingPermission")
    private fun latestKnownLocation(locationManager: LocationManager): Location? {
        return enabledProviders(locationManager)
            .mapNotNull { provider -> runCatching { locationManager.getLastKnownLocation(provider) }.getOrNull() }
            .maxByOrNull { location -> location.time }
            ?.takeIf { location -> System.currentTimeMillis() - location.time <= MAX_LOCATION_AGE_MS }
    }

    @Suppress("DEPRECATION", "OVERRIDE_DEPRECATION")
    @SuppressLint("MissingPermission")
    private suspend fun requestSingleLocation(locationManager: LocationManager): DeviceGeoPoint? {
        val provider = enabledProviders(locationManager).firstOrNull() ?: return null

        return withTimeoutOrNull(LOCATION_TIMEOUT_MS) {
            suspendCancellableCoroutine { continuation ->
                val listener = object : LocationListener {
                    override fun onLocationChanged(location: Location) {
                        locationManager.removeUpdates(this)
                        if (continuation.isActive) {
                            continuation.resume(location.toGeoPoint())
                        }
                    }

                    override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) = Unit
                }

                continuation.invokeOnCancellation {
                    locationManager.removeUpdates(listener)
                }

                runCatching {
                    locationManager.requestSingleUpdate(provider, listener, Looper.getMainLooper())
                }.onFailure {
                    if (continuation.isActive) continuation.resume(null)
                }
            }
        }
    }

    private fun enabledProviders(locationManager: LocationManager): List<String> {
        return listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER, LocationManager.PASSIVE_PROVIDER)
            .filter { provider -> runCatching { locationManager.isProviderEnabled(provider) }.getOrDefault(false) }
    }

    private fun Location.toGeoPoint(): DeviceGeoPoint {
        return DeviceGeoPoint(
            latitude = latitude,
            longitude = longitude,
            accuracyMeters = if (hasAccuracy()) accuracy else null,
            capturedAt = if (time > 0) time else System.currentTimeMillis()
        )
    }

    private companion object {
        const val MAX_LOCATION_AGE_MS = 5 * 60 * 1000L
        const val LOCATION_TIMEOUT_MS = 8_000L
    }
}
