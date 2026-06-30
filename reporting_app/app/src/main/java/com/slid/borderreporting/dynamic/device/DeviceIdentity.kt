package com.slid.borderreporting.dynamic.device

import android.content.Context
import androidx.core.content.edit
import java.util.UUID

object DeviceIdentity {
    private const val PREFS_NAME = "slid_device_identity"
    private const val DEVICE_ID_KEY = "device_id"

    fun getOrCreate(context: Context): String {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val existing = prefs.getString(DEVICE_ID_KEY, null)
        if (!existing.isNullOrBlank()) {
            return existing
        }

        val generated = "android-${UUID.randomUUID()}"
        prefs.edit { putString(DEVICE_ID_KEY, generated) }
        return generated
    }
}
