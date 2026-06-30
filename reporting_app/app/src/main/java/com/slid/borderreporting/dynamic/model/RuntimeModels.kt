package com.slid.borderreporting.dynamic.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
enum class RuntimeFieldType {
    @SerialName("text") TEXT,
    @SerialName("integer") INTEGER,
    @SerialName("decimal") DECIMAL,
    @SerialName("date") DATE,
    @SerialName("datetime") DATETIME,
    @SerialName("select_one") SELECT_ONE,
    @SerialName("select_multiple") SELECT_MULTIPLE,
    @SerialName("note") NOTE,
    @SerialName("calculate") CALCULATE
}

@Serializable
enum class RuleOperator {
    @SerialName("equals") EQUALS,
    @SerialName("not_equals") NOT_EQUALS,
    @SerialName("not_empty") NOT_EMPTY,
    @SerialName("empty") EMPTY,
    @SerialName("in") IN
}

@Serializable
enum class CalculationKind {
    @SerialName("constant") CONSTANT,
    @SerialName("copy") COPY,
    @SerialName("template") TEMPLATE
}

@Serializable
data class ChoiceOption(
    val value: String,
    val label: String
)

@Serializable
data class VisibilityRule(
    val fieldId: String,
    val operator: RuleOperator,
    val value: String? = null,
    val values: List<String> = emptyList()
)

@Serializable
data class CalculationDefinition(
    val kind: CalculationKind,
    val value: String? = null,
    val sourceFieldId: String? = null,
    val template: String? = null
)

@Serializable
data class RuntimeField(
    val id: String,
    val type: RuntimeFieldType,
    val label: String,
    val hint: String? = null,
    val required: Boolean = false,
    val listName: String? = null,
    val options: List<ChoiceOption> = emptyList(),
    val relevant: VisibilityRule? = null,
    val calculation: CalculationDefinition? = null
)

@Serializable
data class RuntimeFormDefinition(
    val formId: String,
    val version: Int,
    val title: String,
    val reportingModule: String = "immigration",
    val standardReference: String? = null,
    val defaultLanguage: String = "English",
    val fields: List<RuntimeField>,
    val choiceLists: Map<String, List<ChoiceOption>> = emptyMap()
)

@Serializable
data class MobileBranding(
    val countryCode: String? = null,
    val countryName: String? = null,
    val agencyName: String? = null,
    val appTitle: String? = null,
    val appSubtitle: String? = null,
    val logoMimeType: String? = null,
    val logoBase64: String? = null
)

@Serializable
data class MobileConfigResponse(
    val branding: MobileBranding? = null,
    val activeForms: List<RuntimeFormDefinition> = emptyList()
)

@Serializable
data class MobileLoginRequest(
    val email: String,
    val password: String,
    val device_name: String
)

@Serializable
data class MobileUserProfile(
    val id: Long,
    val name: String,
    val email: String
)

@Serializable
data class MobileBorderPostAssignment(
    val id: Long,
    val countryCode: String? = null,
    val code: String,
    val digitalAddress: String? = null,
    val name: String,
    val region: String? = null,
    val latitude: Double? = null,
    val longitude: Double? = null,
    val allowedRadiusMeters: Int? = null
)

@Serializable
data class MobileAssignment(
    val role: String,
    val borderPost: MobileBorderPostAssignment
)

@Serializable
data class MobileLoginResponse(
    val token: String,
    val token_type: String,
    val offline_login_allowed: Boolean = true,
    val user: MobileUserProfile,
    val assignment: MobileAssignment? = null,
    val branding: MobileBranding? = null
)

@Serializable
data class MobileMeResponse(
    val user: MobileUserProfile,
    val assignment: MobileAssignment? = null,
    val branding: MobileBranding? = null
)

@Serializable
data class SubmissionPayload(
    val localId: String,
    val formId: String,
    val formVersion: Int,
    val answersJson: String,
    val createdAt: Long,
    val updatedAt: Long,
    val clientSyncAttemptedAt: Long? = null,
    val deviceLatitude: Double? = null,
    val deviceLongitude: Double? = null,
    val deviceLocationAccuracyMeters: Float? = null,
    val deviceLocationCapturedAt: Long? = null
)

@Serializable
data class SubmissionBatchRequest(
    val deviceId: String,
    val submissions: List<SubmissionPayload>
)

@Serializable
data class SubmissionRejection(
    val localId: String,
    val reason: String
)

@Serializable
data class SubmissionAcceptance(
    val localId: String,
    val serverId: String? = null,
    val receivedAt: String? = null
)

@Serializable
data class SubmissionBatchResponse(
    val acceptedIds: List<String> = emptyList(),
    val accepted: List<SubmissionAcceptance> = emptyList(),
    val rejected: List<SubmissionRejection> = emptyList()
)

data class SubmissionSyncSummary(
    val attemptedIds: List<String>,
    val acceptedIds: List<String>,
    val accepted: List<SubmissionAcceptance> = emptyList(),
    val rejected: List<SubmissionRejection>
) {
    val attemptedCount: Int = attemptedIds.size
    val acceptedCount: Int = acceptedIds.size
    val rejectedCount: Int = rejected.size
}

enum class SubmissionStatus(val value: String) {
    DRAFT("draft"),
    PENDING_SYNC("pending_sync"),
    SYNCED("synced"),
    FAILED("failed")
}

data class StoredSubmission(
    val localId: String,
    val formId: String,
    val formVersion: Int,
    val status: String,
    val createdAt: Long,
    val updatedAt: Long,
    val syncError: String?,
    val syncAttemptCount: Int,
    val lastSyncAttemptAt: Long?,
    val serverId: String?,
    val serverReceivedAt: String?,
    val answers: Map<String, List<String>>
)

data class StoredAuthSession(
    val token: String,
    val userName: String,
    val userEmail: String,
    val deviceId: String,
    val role: String?,
    val countryCode: String?,
    val appTitle: String?,
    val appSubtitle: String?,
    val logoMimeType: String?,
    val logoBase64: String?,
    val borderPostCode: String?,
    val borderPostDigitalAddress: String?,
    val borderPostName: String?,
    val borderPostRegion: String?,
    val borderPostLatitude: Double?,
    val borderPostLongitude: Double?,
    val allowedRadiusMeters: Int?,
    val updatedAt: Long
)
