package com.slid.borderreporting.dynamic.local

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "dynamic_form_definitions")
data class DynamicFormDefinitionEntity(
    @PrimaryKey val id: String,
    val formId: String,
    val version: Int,
    val title: String,
    val schemaJson: String,
    val isActive: Boolean,
    val downloadedAt: Long
)

@Entity(tableName = "dynamic_submissions")
data class DynamicSubmissionEntity(
    @PrimaryKey val localId: String,
    val formId: String,
    val formVersion: Int,
    val answersJson: String,
    val status: String,
    val createdAt: Long,
    val updatedAt: Long,
    val deviceLatitude: Double? = null,
    val deviceLongitude: Double? = null,
    val deviceLocationAccuracyMeters: Float? = null,
    val deviceLocationCapturedAt: Long? = null,
    val syncError: String? = null,
    val syncAttemptCount: Int = 0,
    val lastSyncAttemptAt: Long? = null,
    val serverId: String? = null,
    val serverReceivedAt: String? = null
)

@Entity(tableName = "dynamic_config")
data class DynamicConfigEntity(
    @PrimaryKey val key: String,
    val value: String,
    val updatedAt: Long
)

@Entity(tableName = "auth_session")
data class AuthSessionEntity(
    @PrimaryKey val id: Int = 1,
    val token: String,
    val tokenType: String,
    val userId: Long,
    val userName: String,
    val userEmail: String,
    val deviceId: String,
    val role: String? = null,
    val countryCode: String? = null,
    val appTitle: String? = null,
    val appSubtitle: String? = null,
    val logoMimeType: String? = null,
    val logoBase64: String? = null,
    val borderPostCode: String? = null,
    val borderPostDigitalAddress: String? = null,
    val borderPostName: String? = null,
    val borderPostRegion: String? = null,
    val borderPostLatitude: Double? = null,
    val borderPostLongitude: Double? = null,
    val allowedRadiusMeters: Int? = null,
    val updatedAt: Long
)
