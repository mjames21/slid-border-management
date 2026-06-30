package com.slid.borderreporting.dynamic.di

import android.content.Context
import com.jakewharton.retrofit2.converter.kotlinx.serialization.asConverterFactory
import com.slid.borderreporting.dynamic.config.DynamicAppConfig
import com.slid.borderreporting.dynamic.local.DynamicDatabase
import com.slid.borderreporting.dynamic.location.AndroidDeviceLocationProvider
import com.slid.borderreporting.dynamic.remote.FormServiceApi
import com.slid.borderreporting.dynamic.repo.DynamicFormRepository
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import retrofit2.Retrofit

object DynamicRepositoryFactory {
    fun create(context: Context): DynamicFormRepository {
        val db = DynamicDatabase.get(context)

        val json = Json {
            ignoreUnknownKeys = true
        }

        return DynamicFormRepository(
            formDao = db.formDao(),
            submissionDao = db.submissionDao(),
            configDao = db.configDao(),
            authSessionDao = db.authSessionDao(),
            defaultServerUrl = DynamicAppConfig.DEFAULT_API_BASE_URL,
            apiFactory = { serverUrl ->
                Retrofit.Builder()
                    .baseUrl(serverUrl)
                    .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
                    .build()
                    .create(FormServiceApi::class.java)
            },
            locationProvider = AndroidDeviceLocationProvider(context.applicationContext)
        )
    }
}
