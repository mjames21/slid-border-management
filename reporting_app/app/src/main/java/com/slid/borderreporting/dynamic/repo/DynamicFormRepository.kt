package com.slid.borderreporting.dynamic.repo

import com.slid.borderreporting.dynamic.local.AuthSessionDao
import com.slid.borderreporting.dynamic.local.AuthSessionEntity
import com.slid.borderreporting.dynamic.local.DynamicConfigDao
import com.slid.borderreporting.dynamic.local.DynamicConfigEntity
import com.slid.borderreporting.dynamic.local.DynamicFormDao
import com.slid.borderreporting.dynamic.local.DynamicFormDefinitionEntity
import com.slid.borderreporting.dynamic.local.DynamicSubmissionDao
import com.slid.borderreporting.dynamic.local.DynamicSubmissionEntity
import com.slid.borderreporting.dynamic.location.DeviceGeoPoint
import com.slid.borderreporting.dynamic.location.DeviceLocationProvider
import com.slid.borderreporting.dynamic.model.AnswerCodec
import com.slid.borderreporting.dynamic.model.MobileBranding
import com.slid.borderreporting.dynamic.model.MobileConfigResponse
import com.slid.borderreporting.dynamic.model.MobileLoginRequest
import com.slid.borderreporting.dynamic.model.RuntimeFormDefinition
import com.slid.borderreporting.dynamic.model.StoredAuthSession
import com.slid.borderreporting.dynamic.model.StoredSubmission
import com.slid.borderreporting.dynamic.model.SubmissionBatchRequest
import com.slid.borderreporting.dynamic.model.SubmissionPayload
import com.slid.borderreporting.dynamic.model.SubmissionStatus
import com.slid.borderreporting.dynamic.model.SubmissionSyncSummary
import com.slid.borderreporting.dynamic.remote.FormServiceApi
import java.net.URI
import java.util.UUID
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.contentOrNull
import retrofit2.HttpException

class DynamicFormRepository(
    private val formDao: DynamicFormDao,
    private val submissionDao: DynamicSubmissionDao,
    private val configDao: DynamicConfigDao,
    private val authSessionDao: AuthSessionDao,
    private val defaultServerUrl: String,
    private val apiFactory: (String) -> FormServiceApi,
    private val locationProvider: DeviceLocationProvider? = null
) {
    constructor(
        formDao: DynamicFormDao,
        submissionDao: DynamicSubmissionDao,
        configDao: DynamicConfigDao,
        authSessionDao: AuthSessionDao,
        api: FormServiceApi,
        locationProvider: DeviceLocationProvider? = null
    ) : this(
        formDao = formDao,
        submissionDao = submissionDao,
        configDao = configDao,
        authSessionDao = authSessionDao,
        defaultServerUrl = "http://localhost/",
        apiFactory = { api },
        locationProvider = locationProvider
    )

    private companion object {
        const val BRANDING_CONFIG_KEY = "mobile_branding"
        const val SERVER_URL_CONFIG_KEY = "server_url"
    }

    private val json = Json {
        ignoreUnknownKeys = true
    }

    fun observeActiveForm(): Flow<RuntimeFormDefinition?> {
        return formDao.observeActiveForm().map { entity ->
            entity?.let { json.decodeFromString<RuntimeFormDefinition>(it.schemaJson) }
        }
    }

    fun observeStoredSubmissions(): Flow<List<StoredSubmission>> {
        return submissionDao.observeAll().map { entities ->
            entities.map { entity ->
                StoredSubmission(
                    localId = entity.localId,
                    formId = entity.formId,
                    formVersion = entity.formVersion,
                    status = entity.status,
                    createdAt = entity.createdAt,
                    updatedAt = entity.updatedAt,
                    syncError = entity.syncError,
                    syncAttemptCount = entity.syncAttemptCount,
                    lastSyncAttemptAt = entity.lastSyncAttemptAt,
                    serverId = entity.serverId,
                    serverReceivedAt = entity.serverReceivedAt,
                    answers = AnswerCodec.decode(entity.answersJson)
                )
            }
        }
    }

    fun observePendingCount(): Flow<Int> = submissionDao.observePendingCount()

    fun observeServerUrl(): Flow<String> {
        return configDao.observe(SERVER_URL_CONFIG_KEY).map { entity ->
            entity?.value ?: normalizedServerUrl(defaultServerUrl)
        }
    }

    fun observeCachedBranding(): Flow<MobileBranding?> {
        return configDao.observe(BRANDING_CONFIG_KEY).map { entity ->
            entity?.let {
                runCatching { json.decodeFromString<MobileBranding>(it.value) }.getOrNull()
            }
        }
    }

    fun observeAuthSession(): Flow<StoredAuthSession?> {
        return authSessionDao.observeSession().map { entity ->
            entity?.let {
                StoredAuthSession(
                    token = it.token,
                    userName = it.userName,
                    userEmail = it.userEmail,
                    deviceId = it.deviceId,
                    role = it.role,
                    countryCode = it.countryCode,
                    appTitle = it.appTitle,
                    appSubtitle = it.appSubtitle,
                    logoMimeType = it.logoMimeType,
                    logoBase64 = it.logoBase64,
                    borderPostCode = it.borderPostCode,
                    borderPostDigitalAddress = it.borderPostDigitalAddress,
                    borderPostName = it.borderPostName,
                    borderPostRegion = it.borderPostRegion,
                    borderPostLatitude = it.borderPostLatitude,
                    borderPostLongitude = it.borderPostLongitude,
                    allowedRadiusMeters = it.allowedRadiusMeters,
                    updatedAt = it.updatedAt
                )
            }
        }
    }

    suspend fun login(email: String, password: String, deviceName: String) {
        val response = api().login(
            MobileLoginRequest(
                email = email.trim(),
                password = password,
                device_name = deviceName.trim()
            )
        )

        authSessionDao.upsert(
            AuthSessionEntity(
                token = response.token,
                tokenType = response.token_type,
                userId = response.user.id,
                userName = response.user.name,
                userEmail = response.user.email,
                deviceId = deviceName.trim(),
                role = response.assignment?.role,
                countryCode = response.branding?.countryCode ?: response.assignment?.borderPost?.countryCode,
                appTitle = response.branding?.appTitle,
                appSubtitle = response.branding?.appSubtitle,
                logoMimeType = response.branding?.logoMimeType,
                logoBase64 = response.branding?.logoBase64,
                borderPostCode = response.assignment?.borderPost?.code,
                borderPostDigitalAddress = response.assignment?.borderPost?.digitalAddress,
                borderPostName = response.assignment?.borderPost?.name,
                borderPostRegion = response.assignment?.borderPost?.region,
                borderPostLatitude = response.assignment?.borderPost?.latitude,
                borderPostLongitude = response.assignment?.borderPost?.longitude,
                allowedRadiusMeters = response.assignment?.borderPost?.allowedRadiusMeters,
                updatedAt = System.currentTimeMillis()
            )
        )

        persistBranding(response.branding)
        refreshConfig()
    }

    suspend fun refreshPublicBranding() {
        persistBranding(api().getMobileBranding())
    }

    suspend fun logout() {
        val session = authSessionDao.getSession()
        if (session != null) {
            runCatching { api().logout(session.authorizationHeader()) }
        }
        authSessionDao.clear()
    }

    suspend fun refreshConfig() {
        val session = requireSession()
        val config = api().getMobileConfig(session.authorizationHeader())
        upsertForms(config)
        persistBranding(config.branding)
        configDao.upsert(
            DynamicConfigEntity(
                key = "last_config_sync_at",
                value = System.currentTimeMillis().toString(),
                updatedAt = System.currentTimeMillis()
            )
        )
    }

    suspend fun saveServerUrl(rawUrl: String): String {
        val normalized = normalizedServerUrl(rawUrl)
        configDao.upsert(
            DynamicConfigEntity(
                key = SERVER_URL_CONFIG_KEY,
                value = normalized,
                updatedAt = System.currentTimeMillis()
            )
        )

        return normalized
    }

    suspend fun saveDraft(
        form: RuntimeFormDefinition,
        answers: Map<String, List<String>>
    ) {
        saveLocalSubmission(form, answers, SubmissionStatus.DRAFT)
    }

    suspend fun finalizeSubmission(
        form: RuntimeFormDefinition,
        answers: Map<String, List<String>>
    ): String {
        return saveLocalSubmission(form, answers, SubmissionStatus.PENDING_SYNC)
    }

    suspend fun syncPending(deviceId: String): SubmissionSyncSummary {
        val session = requireSession()
        val pending = submissionDao.getPendingSync()
        if (pending.isEmpty()) {
            return SubmissionSyncSummary(
                attemptedIds = emptyList(),
                acceptedIds = emptyList(),
                rejected = emptyList()
            )
        }

        val syncLocation = locationProvider?.currentLocation()
        val attemptedIds = pending.map { it.localId }
        val attemptStartedAt = System.currentTimeMillis()
        submissionDao.recordSyncAttempt(attemptedIds, attemptStartedAt)

        val request = SubmissionBatchRequest(
            deviceId = deviceId.ifBlank { session.deviceId },
            submissions = pending.map {
                val submissionLocation = it.deviceGeoPoint() ?: syncLocation
                SubmissionPayload(
                    localId = it.localId,
                    formId = it.formId,
                    formVersion = it.formVersion,
                    answersJson = it.answersJson,
                    createdAt = it.createdAt,
                    updatedAt = it.updatedAt,
                    clientSyncAttemptedAt = attemptStartedAt,
                    deviceLatitude = submissionLocation?.latitude,
                    deviceLongitude = submissionLocation?.longitude,
                    deviceLocationAccuracyMeters = submissionLocation?.accuracyMeters,
                    deviceLocationCapturedAt = submissionLocation?.capturedAt
                )
            }
        )

        val response = try {
            api().syncSubmissions(session.authorizationHeader(), request)
        } catch (error: Exception) {
            val syncError = error.apiMessage()
            submissionDao.recordSyncError(
                localIds = attemptedIds,
                updatedAt = System.currentTimeMillis(),
                syncError = syncError
            )
            throw SubmissionSyncException(syncError, error)
        }
        val now = System.currentTimeMillis()
        val acceptedReceipts = response.accepted.associateBy { it.localId }
        val acceptedIds = (response.acceptedIds + response.accepted.map { it.localId }).distinct()

        acceptedIds.forEach { localId ->
            val receipt = acceptedReceipts[localId]
            submissionDao.markSynced(
                localId = localId,
                updatedAt = now,
                serverId = receipt?.serverId,
                serverReceivedAt = receipt?.receivedAt
            )
        }

        response.rejected.forEach { rejection ->
            submissionDao.markRejected(
                localId = rejection.localId,
                updatedAt = now,
                syncError = rejection.reason
            )
        }

        return SubmissionSyncSummary(
            attemptedIds = attemptedIds,
            acceptedIds = acceptedIds,
            accepted = response.accepted,
            rejected = response.rejected
        )
    }

    suspend fun storedDeviceId(): String {
        return authSessionDao.getSession()?.deviceId.orEmpty()
    }

    private suspend fun requireSession(): AuthSessionEntity {
        return authSessionDao.getSession() ?: error("Sign in online before using server sync.")
    }

    private suspend fun api(): FormServiceApi = apiFactory(currentServerUrl())

    private suspend fun currentServerUrl(): String {
        return configDao.get(SERVER_URL_CONFIG_KEY)?.value ?: normalizedServerUrl(defaultServerUrl)
    }

    private fun normalizedServerUrl(rawUrl: String): String {
        val withScheme = rawUrl.trim()
            .ifBlank { defaultServerUrl }
            .let { if (it.startsWith("http://") || it.startsWith("https://")) it else "http://$it" }
            .let { if (it.endsWith("/")) it else "$it/" }

        val uri = URI(withScheme)
        require(uri.scheme == "http" || uri.scheme == "https") {
            "Server URL must start with http:// or https://"
        }
        require(!uri.host.isNullOrBlank()) {
            "Enter a valid server URL, for example http://192.168.1.20:8000/"
        }

        return withScheme
    }

    private suspend fun persistBranding(branding: MobileBranding?) {
        if (branding == null) return

        val now = System.currentTimeMillis()
        configDao.upsert(
            DynamicConfigEntity(
                key = BRANDING_CONFIG_KEY,
                value = json.encodeToString(MobileBranding.serializer(), branding),
                updatedAt = now
            )
        )

        val session = authSessionDao.getSession() ?: return
        authSessionDao.upsert(
            session.copy(
                countryCode = branding.countryCode ?: session.countryCode,
                appTitle = branding.appTitle ?: session.appTitle,
                appSubtitle = branding.appSubtitle ?: session.appSubtitle,
                logoMimeType = branding.logoMimeType ?: session.logoMimeType,
                logoBase64 = branding.logoBase64 ?: session.logoBase64,
                updatedAt = now
            )
        )
    }

    private fun AuthSessionEntity.authorizationHeader(): String = "$tokenType $token"

    private suspend fun saveLocalSubmission(
        form: RuntimeFormDefinition,
        answers: Map<String, List<String>>,
        status: SubmissionStatus
    ): String {
        val now = System.currentTimeMillis()
        val deviceLocation = locationProvider?.currentLocation()
        val localId = UUID.randomUUID().toString()
        submissionDao.upsert(
            DynamicSubmissionEntity(
                localId = localId,
                formId = form.formId,
                formVersion = form.version,
                answersJson = AnswerCodec.encode(answers),
                status = status.value,
                createdAt = now,
                updatedAt = now,
                deviceLatitude = deviceLocation?.latitude,
                deviceLongitude = deviceLocation?.longitude,
                deviceLocationAccuracyMeters = deviceLocation?.accuracyMeters,
                deviceLocationCapturedAt = deviceLocation?.capturedAt
            )
        )
        return localId
    }

    private fun DynamicSubmissionEntity.deviceGeoPoint(): DeviceGeoPoint? {
        val latitude = deviceLatitude ?: return null
        val longitude = deviceLongitude ?: return null

        return DeviceGeoPoint(
            latitude = latitude,
            longitude = longitude,
            accuracyMeters = deviceLocationAccuracyMeters,
            capturedAt = deviceLocationCapturedAt ?: updatedAt
        )
    }

    private fun Throwable.apiMessage(): String {
        if (this is HttpException) {
            val body = response()?.errorBody()?.string().orEmpty()
            val parsed = runCatching {
                val obj = json.decodeFromString(JsonObject.serializer(), body)
                (obj["message"] as? JsonPrimitive)?.contentOrNull
            }.getOrNull()

            return parsed
                ?.takeIf { it.isNotBlank() }
                ?: "Server rejected the submission batch with HTTP ${code()}."
        }

        return message?.takeIf { it.isNotBlank() } ?: "Unable to reach the server."
    }

    class SubmissionSyncException(message: String, cause: Throwable) : Exception(message, cause)

    private suspend fun upsertForms(config: MobileConfigResponse) {
        if (config.activeForms.isEmpty()) return

        formDao.deactivateAll()

        config.activeForms.forEach { form ->
            val encoded = json.encodeToString(RuntimeFormDefinition.serializer(), form)
            formDao.upsert(
                DynamicFormDefinitionEntity(
                    id = "${form.formId}:${form.version}",
                    formId = form.formId,
                    version = form.version,
                    title = form.title,
                    schemaJson = encoded,
                    isActive = true,
                    downloadedAt = System.currentTimeMillis()
                )
            )
        }
    }

}
