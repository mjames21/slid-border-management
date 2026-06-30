package com.slid.borderreporting.dynamic.vm

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.slid.borderreporting.dynamic.model.CalculationKind
import com.slid.borderreporting.dynamic.model.MobileBranding
import com.slid.borderreporting.dynamic.model.RuntimeField
import com.slid.borderreporting.dynamic.model.RuntimeFieldType
import com.slid.borderreporting.dynamic.model.RuntimeFormDefinition
import com.slid.borderreporting.dynamic.model.RuleOperator
import com.slid.borderreporting.dynamic.model.StoredAuthSession
import com.slid.borderreporting.dynamic.model.StoredSubmission
import com.slid.borderreporting.dynamic.model.SubmissionSyncSummary
import com.slid.borderreporting.dynamic.mrz.MrzFormat
import com.slid.borderreporting.dynamic.mrz.ParsedMrz
import com.slid.borderreporting.dynamic.repo.DynamicFormRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import org.json.JSONObject

data class DynamicFormsUiState(
    val isLoading: Boolean = true,
    val activeForm: RuntimeFormDefinition? = null,
    val authSession: StoredAuthSession? = null,
    val branding: MobileBranding? = null,
    val serverUrl: String = "",
    val setupCode: String = "",
    val loginEmail: String = "",
    val loginPassword: String = "",
    val isLoginPasswordVisible: Boolean = false,
    val deviceName: String = "",
    val answers: Map<String, List<String>> = emptyMap(),
    val submissions: List<StoredSubmission> = emptyList(),
    val pendingSyncCount: Int = 0,
    val isSubmitting: Boolean = false,
    val message: String? = null,
    val validationErrors: Map<String, String> = emptyMap()
)

class DynamicFormsViewModel(
    private val repository: DynamicFormRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(DynamicFormsUiState())
    val uiState: StateFlow<DynamicFormsUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            runCatching { repository.refreshPublicBranding() }
        }

        viewModelScope.launch {
            repository.observeActiveForm().collect { form ->
                _uiState.update {
                    val initialAnswers = form?.let(::calculateDerivedAnswers).orEmpty()
                    it.copy(
                        isLoading = false,
                        activeForm = form,
                        answers = initialAnswers
                    )
                }
            }
        }

        viewModelScope.launch {
            repository.observeStoredSubmissions().collect { submissions ->
                _uiState.update { it.copy(submissions = submissions) }
            }
        }

        viewModelScope.launch {
            repository.observeAuthSession().collect { session ->
                _uiState.update { it.copy(authSession = session) }
            }
        }

        viewModelScope.launch {
            repository.observeCachedBranding().collect { branding ->
                _uiState.update { it.copy(branding = branding) }
            }
        }

        viewModelScope.launch {
            repository.observeServerUrl().collect { serverUrl ->
                _uiState.update { it.copy(serverUrl = serverUrl) }
            }
        }

        viewModelScope.launch {
            repository.observePendingCount().collect { count ->
                _uiState.update { it.copy(pendingSyncCount = count) }
            }
        }
    }

    fun updateSingleValue(fieldId: String, value: String) {
        val currentForm = _uiState.value.activeForm ?: return
        val next = _uiState.value.answers.toMutableMap()
        next[fieldId] = listOf(value)
        applyAnswers(currentForm, next)
    }

    fun toggleMultiValue(fieldId: String, value: String, selected: Boolean) {
        val currentForm = _uiState.value.activeForm ?: return
        val next = _uiState.value.answers.toMutableMap()
        val current = next[fieldId].orEmpty().toMutableList()

        if (selected) {
            if (value !in current) current.add(value)
        } else {
            current.remove(value)
        }

        next[fieldId] = current
        applyAnswers(currentForm, next)
    }

    fun refreshConfig() {
        viewModelScope.launch {
            try {
                repository.refreshConfig()
                _uiState.update { it.copy(message = "Configuration updated.") }
            } catch (e: Exception) {
                _uiState.update { it.copy(message = e.message ?: "Configuration update failed.") }
            }
        }
    }

    fun updateLoginEmail(value: String) {
        _uiState.update { it.copy(loginEmail = value) }
    }

    fun updateLoginPassword(value: String) {
        _uiState.update { it.copy(loginPassword = value) }
    }

    fun toggleLoginPasswordVisible() {
        _uiState.update { it.copy(isLoginPasswordVisible = !it.isLoginPasswordVisible) }
    }

    fun updateServerUrl(value: String) {
        _uiState.update { it.copy(serverUrl = value) }
    }

    fun saveCurrentServerUrl() {
        val rawServerUrl = _uiState.value.serverUrl
        viewModelScope.launch {
            try {
                val normalized = repository.saveServerUrl(rawServerUrl)
                _uiState.update {
                    it.copy(
                        serverUrl = normalized,
                        message = "Server URL saved. Sync pending submissions again."
                    )
                }
            } catch (e: Exception) {
                _uiState.update { it.copy(message = e.message ?: "Server URL could not be saved.") }
            }
        }
    }

    fun updateSetupCode(value: String) {
        _uiState.update { it.copy(setupCode = value) }
    }

    fun applyManualSetupCode() {
        if (_uiState.value.setupCode.isBlank()) {
            _uiState.update { it.copy(message = "Paste a setup code or scan the setup QR.") }
            return
        }

        applySetupCode(_uiState.value.setupCode)
    }

    fun applySetupCode(rawCode: String) {
        runCatching {
            val payload = JSONObject(rawCode.trim())
            require(payload.optString("type") == "slid_mobile_setup") {
                "This QR code is not a SLID mobile setup code."
            }

            _uiState.update {
                it.copy(
                    setupCode = "",
                    serverUrl = payload.optString("serverUrl", it.serverUrl),
                    loginEmail = payload.optString("email", it.loginEmail),
                    loginPassword = payload.optString("password", it.loginPassword),
                    deviceName = payload.optString("deviceName", it.deviceName).ifBlank { it.deviceName },
                    message = "Setup QR applied. Review details and sign in."
                )
            }
        }.onFailure { error ->
            _uiState.update { it.copy(message = error.message ?: "Invalid setup QR code.") }
        }
    }

    fun updateDeviceName(value: String) {
        _uiState.update { it.copy(deviceName = value) }
    }

    fun login() {
        val state = _uiState.value
        viewModelScope.launch {
            try {
                val serverUrl = repository.saveServerUrl(state.serverUrl)
                repository.login(
                    email = state.loginEmail,
                    password = state.loginPassword,
                    deviceName = state.deviceName
                )
                _uiState.update {
                    it.copy(
                        serverUrl = serverUrl,
                        loginPassword = "",
                        isLoginPasswordVisible = false,
                        message = "Signed in and downloaded active configuration."
                    )
                }
            } catch (e: Exception) {
                _uiState.update { it.copy(message = e.message ?: "Sign in failed.") }
            }
        }
    }

    fun logout() {
        viewModelScope.launch {
            repository.logout()
            _uiState.update { it.copy(message = "Signed out.", authSession = null) }
        }
    }

    fun saveDraft() {
        val form = _uiState.value.activeForm ?: return
        viewModelScope.launch {
            repository.saveDraft(form, _uiState.value.answers)
            _uiState.update {
                it.copy(
                    message = "Draft saved.",
                    validationErrors = emptyMap()
                )
            }
        }
    }

    fun finalizeSubmission(defaultDeviceId: String = "") {
        if (_uiState.value.isSubmitting) return

        val form = _uiState.value.activeForm ?: return
        val errors = validate(form, _uiState.value.answers)

        if (errors.isNotEmpty()) {
            _uiState.update {
                it.copy(
                    validationErrors = errors,
                    message = "Complete the required fields."
                )
            }
            return
        }

        viewModelScope.launch {
            _uiState.update {
                it.copy(
                    isSubmitting = true,
                    message = "Submitting report..."
                )
            }

            try {
                val localId = repository.finalizeSubmission(form, _uiState.value.answers)
                val syncResult = runCatching {
                    val deviceId = repository.storedDeviceId().ifBlank { defaultDeviceId }
                    repository.syncPending(deviceId = deviceId)
                }
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        answers = calculateDerivedAnswers(form),
                        validationErrors = emptyMap(),
                        message = syncResult.fold(
                            onSuccess = { summary ->
                                val rejection = summary.rejected.firstOrNull { rejection -> rejection.localId == localId }
                                when {
                                    localId in summary.acceptedIds -> {
                                        val extra = (summary.acceptedCount - 1).coerceAtLeast(0)
                                        if (extra > 0) {
                                            "Submission sent successfully. Also sent $extra pending report(s)."
                                        } else {
                                            "Submission sent successfully."
                                        }
                                    }

                                    rejection != null -> {
                                        "Submission was not sent. ${rejection.reason}"
                                    }

                                    summary.acceptedCount > 0 -> {
                                        "Submission saved but not sent yet. Sent ${summary.acceptedCount} older pending report(s). This report will retry when connectivity returns."
                                    }

                                    else -> {
                                        "Submission saved but not sent yet. It will retry when connectivity returns."
                                    }
                                }
                            },
                            onFailure = { error ->
                                "Submission saved but not sent. ${error.userFacingSyncMessage(_uiState.value.serverUrl)} It will retry when connectivity returns."
                            }
                        )
                    )
                }
            } catch (e: Exception) {
                _uiState.update {
                    it.copy(
                        isSubmitting = false,
                        message = "Submission could not be saved. ${e.userFacingSyncMessage()}"
                    )
                }
            }
        }
    }

    fun applyMrzScan(parsedMrz: ParsedMrz) {
        val form = _uiState.value.activeForm ?: return
        val next = _uiState.value.answers.toMutableMap()
        val updatedFields = mutableSetOf<String>()

        fun putText(fieldId: String, value: String?) {
            if (value.isNullOrBlank() || form.fields.none { it.id == fieldId }) return

            next[fieldId] = listOf(value)
            updatedFields += fieldId
        }

        fun putChoice(fieldId: String, candidates: List<String>) {
            val field = form.fields.firstOrNull { it.id == fieldId } ?: return
            val options = if (field.options.isNotEmpty()) {
                field.options
            } else {
                form.choiceLists[field.listName].orEmpty()
            }
            val selectedValue = candidates.firstNotNullOfOrNull { candidate ->
                options.firstOrNull { option ->
                    option.value.equals(candidate, ignoreCase = true) ||
                        option.label.equals(candidate, ignoreCase = true)
                }?.value
            } ?: candidates.firstOrNull()

            if (!selectedValue.isNullOrBlank()) {
                next[fieldId] = listOf(selectedValue)
                updatedFields += fieldId
            }
        }

        fun sexCandidates(): List<String> {
            return when (parsedMrz.sex) {
                "m" -> listOf("m", "male", "M", "Male")
                "f" -> listOf("f", "female", "F", "Female")
                "x" -> listOf("x", "other", "X")
                else -> listOf("unspecified", "unknown")
            }
        }

        putChoice("mrz_format", listOf(parsedMrz.format.formValue))
        parsedMrz.lines.forEachIndexed { index, line -> putText("mrz_line_${index + 1}", line) }
        putText("mrz_document_code", parsedMrz.documentCode)
        putText("mrz_issuing_state", parsedMrz.issuingState)
        putText("mrz_primary_identifier", parsedMrz.primaryIdentifier)
        putText("mrz_secondary_identifier", parsedMrz.secondaryIdentifier)
        putText("mrz_document_number", parsedMrz.documentNumber)
        putText("mrz_document_number_check_digit", parsedMrz.documentNumberCheckDigit)
        putText("mrz_nationality", parsedMrz.nationality)
        putText("mrz_date_of_birth", parsedMrz.dateOfBirth)
        putText("mrz_date_of_birth_check_digit", parsedMrz.dateOfBirthCheckDigit)
        putChoice("mrz_sex", sexCandidates())
        putText("mrz_expiry_date", parsedMrz.expiryDate)
        putText("mrz_expiry_date_check_digit", parsedMrz.expiryDateCheckDigit)
        putText("mrz_optional_data", parsedMrz.optionalData)
        putText("mrz_optional_data_check_digit", parsedMrz.optionalDataCheckDigit)
        putText("mrz_composite_check_digit", parsedMrz.compositeCheckDigit)
        putChoice("mrz_check_result", listOf(parsedMrz.checkResult.formValue))

        putText("document_type_code", parsedMrz.documentCode)
        putText("issuing_state_or_org", parsedMrz.issuingState)
        putText("document_number", parsedMrz.documentNumber)
        putText("id_number", parsedMrz.documentNumber)
        putText("passport_number", parsedMrz.documentNumber)
        putText("travel_document_number", parsedMrz.documentNumber)
        putText("document_number_check_digit", parsedMrz.documentNumberCheckDigit)
        putText("document_expiry_date", parsedMrz.expiryDateIso)
        putText("expiry_date", parsedMrz.expiryDateIso)
        putText("expiry_date_check_digit", parsedMrz.expiryDateCheckDigit)
        putText("surname_primary_identifier", parsedMrz.primaryIdentifier)
        putText("given_names_secondary_identifier", parsedMrz.secondaryIdentifier)
        putText("full_name_viz", parsedMrz.fullName)
        putText("full_name", parsedMrz.fullName)
        putText("traveller_full_name", parsedMrz.fullName)
        putText("nationality_code", parsedMrz.nationality)
        putChoice("nationality", listOf(parsedMrz.nationality, parsedMrz.nationality.lowercase()))
        putText("date_of_birth", parsedMrz.dateOfBirthIso)
        putText("dob", parsedMrz.dateOfBirthIso)
        putText("date_of_birth_check_digit", parsedMrz.dateOfBirthCheckDigit)
        putChoice("sex", sexCandidates())
        val documentKind = when (parsedMrz.format) {
            MrzFormat.MRV_A,
            MrzFormat.MRV_B -> "visa"
            else -> "passport"
        }
        putChoice("document_category", listOf(documentKind))
        putChoice("id_doc_type", listOf(documentKind, "passport"))
        putChoice(
            "mrtd_form_factor",
            when (parsedMrz.format) {
                MrzFormat.TD3 -> listOf("td3")
                MrzFormat.TD2 -> listOf("td2")
                MrzFormat.TD1 -> listOf("td1")
                MrzFormat.MRV_A -> listOf("mrv_a")
                MrzFormat.MRV_B -> listOf("mrv_b")
            }
        )

        applyAnswers(form, next)
        _uiState.update {
            it.copy(
                validationErrors = it.validationErrors - updatedFields,
                message = "MRZ scanned. Review the extracted passport data before finalizing."
            )
        }
    }

    fun syncPending(defaultDeviceId: String) {
        viewModelScope.launch {
            try {
                val deviceId = repository.storedDeviceId().ifBlank { defaultDeviceId }
                val summary = repository.syncPending(deviceId = deviceId)
                _uiState.update {
                    it.copy(message = summary.toUserMessage())
                }
            } catch (e: Exception) {
                _uiState.update {
                    it.copy(message = "Could not submit pending reports. ${e.userFacingSyncMessage(it.serverUrl)}")
                }
            }
        }
    }

    fun clearMessage() {
        _uiState.update { it.copy(message = null) }
    }

    fun visibleFields(): List<RuntimeField> {
        val form = _uiState.value.activeForm ?: return emptyList()
        val answers = _uiState.value.answers
        return form.fields.filter { field ->
            field.type != RuntimeFieldType.CALCULATE && isVisible(field, answers)
        }
    }

    fun optionsFor(field: RuntimeField): List<com.slid.borderreporting.dynamic.model.ChoiceOption> {
        val form = _uiState.value.activeForm ?: return emptyList()
        return if (field.options.isNotEmpty()) {
            field.options
        } else {
            form.choiceLists[field.listName].orEmpty()
        }
    }

    private fun applyAnswers(form: RuntimeFormDefinition, rawAnswers: MutableMap<String, List<String>>) {
        val derived = calculateDerivedAnswers(form, rawAnswers)
        _uiState.update {
            it.copy(
                answers = derived,
                validationErrors = it.validationErrors - rawAnswers.keys
            )
        }
    }

    private fun calculateDerivedAnswers(
        form: RuntimeFormDefinition,
        baseAnswers: Map<String, List<String>> = emptyMap()
    ): Map<String, List<String>> {
        val result = baseAnswers.toMutableMap()

        form.fields
            .filter { it.type == RuntimeFieldType.CALCULATE && it.calculation != null }
            .forEach { field ->
                val calculation = field.calculation ?: return@forEach
                val computed = when (calculation.kind) {
                    CalculationKind.CONSTANT -> calculation.value.orEmpty()
                    CalculationKind.COPY -> result[calculation.sourceFieldId].orEmpty().firstOrNull().orEmpty()
                    CalculationKind.TEMPLATE -> {
                        val template = calculation.template.orEmpty()
                        """\$\{([^}]+)}""".toRegex().replace(template) { match ->
                            result[match.groupValues[1]].orEmpty().firstOrNull().orEmpty()
                        }
                    }
                }
                result[field.id] = listOf(computed)
            }

        return result
    }

    private fun validate(
        form: RuntimeFormDefinition,
        answers: Map<String, List<String>>
    ): Map<String, String> {
        return form.fields
            .filter { it.type != RuntimeFieldType.CALCULATE }
            .filter { isVisible(it, answers) }
            .mapNotNull { field ->
                val values = answers[field.id].orEmpty().filter { it.isNotBlank() }
                if (field.required && values.isEmpty()) {
                    field.id to "${field.label} is required."
                } else {
                    null
                }
            }
            .toMap()
    }

    private fun isVisible(
        field: RuntimeField,
        answers: Map<String, List<String>>
    ): Boolean {
        val rule = field.relevant ?: return true
        val current = answers[rule.fieldId].orEmpty()

        return when (rule.operator) {
            RuleOperator.EQUALS -> current.firstOrNull() == rule.value
            RuleOperator.NOT_EQUALS -> current.firstOrNull() != rule.value
            RuleOperator.NOT_EMPTY -> current.any { it.isNotBlank() }
            RuleOperator.EMPTY -> current.none { it.isNotBlank() }
            RuleOperator.IN -> current.any { it in rule.values }
        }
    }

    private fun SubmissionSyncSummary.toUserMessage(): String {
        return when {
            attemptedCount == 0 -> "No pending submissions to send."
            acceptedCount > 0 && rejectedCount == 0 -> "Sent $acceptedCount submission(s)."
            acceptedCount > 0 && rejectedCount > 0 -> "Sent $acceptedCount submission(s). $rejectedCount report(s) were not sent. Open Stored Submissions for reasons."
            rejectedCount > 0 -> "$rejectedCount report(s) were not sent. Open Stored Submissions for reasons."
            else -> "No submissions were sent. They remain queued for retry."
        }
    }

    private fun Throwable.userFacingSyncMessage(serverUrl: String? = null): String {
        val rawMessage = message.orEmpty()
        val lower = rawMessage.lowercase()
        val serverHint = serverUrl
            ?.takeIf { it.isNotBlank() }
            ?.let { " at $it" }
            .orEmpty()

        return when {
            "failed to connect" in lower ||
                "timeout" in lower ||
                "timed out" in lower ||
                "unable to resolve host" in lower -> {
                "Could not reach the server$serverHint. Check Wi-Fi, the Server URL, and that Laravel is running."
            }

            rawMessage.isNotBlank() -> rawMessage
            else -> "Check the server URL or network connection."
        }
    }
}
