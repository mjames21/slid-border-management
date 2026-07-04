package com.slid.borderreporting.dynamic.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.Checkbox
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TimeInput
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.material3.rememberTimePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import com.slid.borderreporting.dynamic.model.ChoiceOption
import com.slid.borderreporting.dynamic.model.RuntimeField
import com.slid.borderreporting.dynamic.model.RuntimeFieldType
import com.slid.borderreporting.dynamic.vm.DynamicFormsUiState
import java.time.Instant
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter

@Composable
fun DynamicFormScreen(
    uiState: DynamicFormsUiState,
    visibleFields: List<RuntimeField>,
    optionsFor: (RuntimeField) -> List<ChoiceOption>,
    onSingleValueChange: (String, String) -> Unit,
    onMultiValueToggle: (String, String, Boolean) -> Unit,
    onSaveDraft: () -> Unit,
    onFinalize: () -> Unit,
    onScanMrz: () -> Unit,
    onBack: () -> Unit,
    onClearMessage: () -> Unit
) {
    val form = uiState.activeForm
    val steps = remember(form?.formId, form?.version, visibleFields) {
        buildFormSteps(visibleFields)
    }
    var currentStepIndex by remember(form?.formId, form?.version) { mutableIntStateOf(0) }
    var stepValidationErrors by remember(form?.formId, form?.version, currentStepIndex) {
        mutableStateOf(emptyMap<String, String>())
    }

    LaunchedEffect(steps.size) {
        if (currentStepIndex > steps.lastIndex) {
            currentStepIndex = steps.lastIndex.coerceAtLeast(0)
        }
    }

    val safeStepIndex = currentStepIndex.coerceIn(0, steps.lastIndex)
    val currentStep = steps[safeStepIndex]
    val isFirstStep = safeStepIndex == 0
    val isLastStep = safeStepIndex == steps.lastIndex
    val canScanMrz = form != null && currentStep.fields.any { it.supportsMrzScan() }
    val progress = (safeStepIndex + 1).toFloat() / steps.size.toFloat()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .navigationBarsPadding()
            .imePadding()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        BrandHeader(
            title = uiState.authSession?.appTitle ?: uiState.branding?.appTitle ?: "BorderReach",
            subtitle = form?.title ?: "No active form",
            logoBase64 = uiState.authSession?.logoBase64 ?: uiState.branding?.logoBase64
        )

        uiState.message?.let { message ->
            Card(modifier = Modifier.fillMaxWidth()) {
                Text(
                    text = message,
                    modifier = Modifier.padding(16.dp)
                )
            }
        }

        Card(modifier = Modifier.fillMaxWidth()) {
            Column(
                modifier = Modifier.padding(12.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Text(
                    text = "Question ${safeStepIndex + 1} of ${steps.size}",
                    style = MaterialTheme.typography.labelMedium
                )
                LinearProgressIndicator(
                    progress = { progress },
                    modifier = Modifier.fillMaxWidth()
                )
                Text(
                    text = currentStep.title,
                    style = MaterialTheme.typography.titleMedium
                )
                currentStep.description?.takeIf { it.isNotBlank() }?.let { description ->
                    Text(description, style = MaterialTheme.typography.bodySmall)
                }
                if (canScanMrz) {
                    OutlinedButton(
                        onClick = {
                            onClearMessage()
                            onScanMrz()
                        },
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Text("Scan Passport MRZ")
                    }
                }
            }
        }

        LazyColumn(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(10.dp),
            contentPadding = PaddingValues(bottom = 4.dp)
        ) {
            items(currentStep.fields, key = { it.id }) { field ->
                DynamicFieldCard(
                    field = field,
                    values = uiState.answers[field.id].orEmpty(),
                    error = stepValidationErrors[field.id] ?: uiState.validationErrors[field.id],
                    optionsFor = optionsFor,
                    onSingleValueChange = { fieldId, value ->
                        stepValidationErrors = stepValidationErrors - fieldId
                        onSingleValueChange(fieldId, value)
                    },
                    onMultiValueToggle = { fieldId, value, checked ->
                        stepValidationErrors = stepValidationErrors - fieldId
                        onMultiValueToggle(fieldId, value, checked)
                    }
                )
            }
        }

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            OutlinedButton(
                onClick = {
                    stepValidationErrors = emptyMap()
                    currentStepIndex = (safeStepIndex - 1).coerceAtLeast(0)
                },
                modifier = Modifier.weight(1f),
                enabled = !isFirstStep && !uiState.isSubmitting
            ) {
                Text("Previous")
            }

            Button(
                onClick = {
                    onClearMessage()
                    val errors = validateStep(currentStep, uiState.answers)
                    if (errors.isNotEmpty()) {
                        stepValidationErrors = errors
                        return@Button
                    }

                    stepValidationErrors = emptyMap()
                    if (isLastStep) {
                        onFinalize()
                    } else {
                        currentStepIndex = (safeStepIndex + 1).coerceAtMost(steps.lastIndex)
                    }
                },
                modifier = Modifier.weight(1f),
                enabled = !uiState.isSubmitting
            ) {
                Text(
                    when {
                        uiState.isSubmitting -> "Submitting..."
                        isLastStep -> "Finalize & Send"
                        else -> "Next"
                    }
                )
            }
        }

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            OutlinedButton(
                onClick = {
                    onClearMessage()
                    onSaveDraft()
                },
                modifier = Modifier.weight(1f),
                enabled = !uiState.isSubmitting
            ) {
                Text("Save Draft")
            }

            OutlinedButton(
                onClick = onBack,
                modifier = Modifier.weight(1f),
                enabled = !uiState.isSubmitting
            ) {
                Text("Close")
            }
        }
    }
}

@Composable
private fun DynamicFieldCard(
    field: RuntimeField,
    values: List<String>,
    error: String?,
    optionsFor: (RuntimeField) -> List<ChoiceOption>,
    onSingleValueChange: (String, String) -> Unit,
    onMultiValueToggle: (String, String, Boolean) -> Unit
) {
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier.padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Text(
                text = buildString {
                    append(field.label)
                    if (field.required) append(" *")
                },
                style = MaterialTheme.typography.titleMedium
            )

            field.hint?.takeIf { it.isNotBlank() }?.let { hint ->
                Text(hint, style = MaterialTheme.typography.bodySmall)
            }

            when (field.type) {
                RuntimeFieldType.TEXT,
                RuntimeFieldType.INTEGER,
                RuntimeFieldType.DECIMAL -> {
                    val multiline = field.prefersMultilineInput()
                    OutlinedTextField(
                        value = values.firstOrNull().orEmpty(),
                        onValueChange = { onSingleValueChange(field.id, it) },
                        modifier = Modifier.fillMaxWidth(),
                        keyboardOptions = field.keyboardOptions(),
                        singleLine = !multiline,
                        minLines = if (multiline) 3 else 1,
                        maxLines = if (multiline) 6 else 1
                    )
                }

                RuntimeFieldType.DATE -> {
                    DatePickerField(
                        value = values.firstOrNull().orEmpty(),
                        onValueChange = { onSingleValueChange(field.id, it) }
                    )
                }

                RuntimeFieldType.DATETIME -> {
                    DateTimePickerField(
                        value = values.firstOrNull().orEmpty(),
                        onValueChange = { onSingleValueChange(field.id, it) }
                    )
                }

                RuntimeFieldType.NOTE -> {
                    Text(field.hint ?: field.label)
                }

                RuntimeFieldType.SELECT_ONE -> {
                    val options = optionsFor(field)
                    if (options.size > 8) {
                        SearchableSingleSelectField(
                            field = field,
                            options = options,
                            selectedValue = values.firstOrNull(),
                            onSingleValueChange = onSingleValueChange
                        )
                    } else {
                        options.forEach { option ->
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(8.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                RadioButton(
                                    selected = values.firstOrNull() == option.value,
                                    onClick = { onSingleValueChange(field.id, option.value) }
                                )
                                Text(
                                    text = option.label,
                                    modifier = Modifier.weight(1f)
                                )
                            }
                        }
                    }
                }

                RuntimeFieldType.SELECT_MULTIPLE -> {
                    optionsFor(field).forEach { option ->
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Checkbox(
                                checked = option.value in values,
                                onCheckedChange = { checked ->
                                    onMultiValueToggle(field.id, option.value, checked)
                                }
                            )
                            Text(
                                text = option.label,
                                modifier = Modifier.weight(1f)
                            )
                        }
                    }
                }

                RuntimeFieldType.CALCULATE -> Unit
            }

            error?.let {
                Text(
                    text = it,
                    color = MaterialTheme.colorScheme.error,
                    style = MaterialTheme.typography.bodySmall
                )
            }
        }
    }
}

@Composable
private fun SearchableSingleSelectField(
    field: RuntimeField,
    options: List<ChoiceOption>,
    selectedValue: String?,
    onSingleValueChange: (String, String) -> Unit
) {
    val selectedLabel = options.firstOrNull { it.value == selectedValue }?.label.orEmpty()
    var query by remember(field.id, selectedValue) { mutableStateOf(selectedLabel) }
    val filteredOptions = remember(query, options) {
        val search = query.trim()
        if (search.isBlank()) {
            options.take(8)
        } else {
            options
                .filter { option -> option.label.contains(search, ignoreCase = true) }
                .take(8)
        }
    }

    OutlinedTextField(
        value = query,
        onValueChange = { query = it },
        modifier = Modifier.fillMaxWidth(),
        label = { Text("Search and select") },
        singleLine = true
    )

    selectedLabel.takeIf { it.isNotBlank() }?.let {
        Text("Selected: $it", style = MaterialTheme.typography.bodySmall)
    }

    filteredOptions.forEach { option ->
        OutlinedButton(
            onClick = {
                query = option.label
                onSingleValueChange(field.id, option.value)
            },
            modifier = Modifier.fillMaxWidth()
        ) {
            Text(option.label)
        }
    }
}

private data class FormStep(
    val title: String,
    val description: String?,
    val fields: List<RuntimeField>
)

private fun buildFormSteps(fields: List<RuntimeField>): List<FormStep> {
    if (fields.isEmpty()) {
        return listOf(FormStep("Review", null, emptyList()))
    }

    val steps = mutableListOf<FormStep>()
    var sectionTitle: String? = null
    var sectionDescription: String? = null

    fields.forEach { field ->
        if (field.isSectionMarker()) {
            sectionTitle = field.label
            sectionDescription = field.hint
        } else {
            steps += FormStep(
                title = sectionTitle ?: fallbackStepTitle(steps.size, listOf(field)),
                description = sectionDescription,
                fields = listOf(field)
            )
        }
    }

    return steps.ifEmpty { listOf(FormStep("Review", null, fields.filterNot { it.isSectionMarker() })) }
}

private fun RuntimeField.keyboardOptions(): KeyboardOptions {
    return when (type) {
        RuntimeFieldType.INTEGER -> KeyboardOptions(keyboardType = KeyboardType.Number)
        RuntimeFieldType.DECIMAL -> KeyboardOptions(keyboardType = KeyboardType.Decimal)
        RuntimeFieldType.DATE,
        RuntimeFieldType.DATETIME -> KeyboardOptions(keyboardType = KeyboardType.Text)
        else -> KeyboardOptions.Default
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DatePickerField(
    value: String,
    onValueChange: (String) -> Unit
) {
    var showPicker by remember { mutableStateOf(false) }
    val selectedDate = remember(value) { parseDateValue(value) }
    val datePickerState = rememberDatePickerState(
        initialSelectedDateMillis = selectedDate?.toPickerMillis()
    )

    OutlinedButton(
        onClick = { showPicker = true },
        modifier = Modifier.fillMaxWidth()
    ) {
        Text(value.ifBlank { "Select date" })
    }

    if (showPicker) {
        DatePickerDialog(
            onDismissRequest = { showPicker = false },
            confirmButton = {
                TextButton(
                    onClick = {
                        datePickerState.selectedDateMillis
                            ?.toLocalDateFromPickerMillis()
                            ?.let { onValueChange(it.format(DateValueFormatter)) }
                        showPicker = false
                    }
                ) {
                    Text("Done")
                }
            },
            dismissButton = {
                TextButton(onClick = { showPicker = false }) {
                    Text("Cancel")
                }
            }
        ) {
            DatePicker(state = datePickerState)
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DateTimePickerField(
    value: String,
    onValueChange: (String) -> Unit
) {
    var showDatePicker by remember { mutableStateOf(false) }
    var showTimePicker by remember { mutableStateOf(false) }
    var pendingDate by remember(value) { mutableStateOf(parseDateTimeValue(value)?.toLocalDate()) }
    val selectedDateTime = remember(value) { parseDateTimeValue(value) }
    val selectedTime = selectedDateTime?.toLocalTime() ?: LocalTime.now()
    val datePickerState = rememberDatePickerState(
        initialSelectedDateMillis = pendingDate?.toPickerMillis()
    )
    val timePickerState = rememberTimePickerState(
        initialHour = selectedTime.hour,
        initialMinute = selectedTime.minute,
        is24Hour = true
    )

    OutlinedButton(
        onClick = { showDatePicker = true },
        modifier = Modifier.fillMaxWidth()
    ) {
        Text(value.ifBlank { "Select date and time" })
    }

    if (showDatePicker) {
        DatePickerDialog(
            onDismissRequest = { showDatePicker = false },
            confirmButton = {
                TextButton(
                    onClick = {
                        pendingDate = datePickerState.selectedDateMillis?.toLocalDateFromPickerMillis()
                        showDatePicker = false
                        showTimePicker = pendingDate != null
                    }
                ) {
                    Text("Next")
                }
            },
            dismissButton = {
                TextButton(onClick = { showDatePicker = false }) {
                    Text("Cancel")
                }
            }
        ) {
            DatePicker(state = datePickerState)
        }
    }

    if (showTimePicker) {
        AlertDialog(
            onDismissRequest = { showTimePicker = false },
            title = { Text("Select time") },
            text = { TimeInput(state = timePickerState) },
            confirmButton = {
                TextButton(
                    onClick = {
                        val date = pendingDate ?: LocalDate.now(ZoneOffset.UTC)
                        val time = LocalTime.of(timePickerState.hour, timePickerState.minute)
                        onValueChange(LocalDateTime.of(date, time).format(DateTimeValueFormatter))
                        showTimePicker = false
                    }
                ) {
                    Text("Done")
                }
            },
            dismissButton = {
                TextButton(onClick = { showTimePicker = false }) {
                    Text("Cancel")
                }
            }
        )
    }
}

private fun RuntimeField.prefersMultilineInput(): Boolean {
    if (type != RuntimeFieldType.TEXT) return false

    val searchable = "${id.lowercase()} ${label.lowercase()} ${hint.orEmpty().lowercase()}"
    return listOf(
        "address",
        "comment",
        "description",
        "detail",
        "narrative",
        "note",
        "observation",
        "remark"
    ).any { searchable.contains(it) }
}

private val DateValueFormatter: DateTimeFormatter = DateTimeFormatter.ISO_LOCAL_DATE
private val DateTimeValueFormatter: DateTimeFormatter = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm")

private fun parseDateValue(value: String): LocalDate? {
    return value.trim()
        .takeIf { it.isNotBlank() }
        ?.let { raw ->
            runCatching { LocalDate.parse(raw, DateValueFormatter) }
                .recoverCatching { parseDateTimeValue(raw)?.toLocalDate() }
                .getOrNull()
        }
}

private fun parseDateTimeValue(value: String): LocalDateTime? {
    val raw = value.trim().takeIf { it.isNotBlank() } ?: return null

    return listOf(
        { LocalDateTime.parse(raw, DateTimeValueFormatter) },
        { LocalDateTime.parse(raw, DateTimeFormatter.ISO_LOCAL_DATE_TIME) },
        { LocalDate.parse(raw, DateValueFormatter).atStartOfDay() }
    ).firstNotNullOfOrNull { parser ->
        runCatching { parser() }.getOrNull()
    }
}

private fun LocalDate.toPickerMillis(): Long {
    return atStartOfDay().toInstant(ZoneOffset.UTC).toEpochMilli()
}

private fun Long.toLocalDateFromPickerMillis(): LocalDate {
    return Instant.ofEpochMilli(this).atZone(ZoneOffset.UTC).toLocalDate()
}

private fun RuntimeField.isSectionMarker(): Boolean {
    return type == RuntimeFieldType.NOTE && id.startsWith("section_")
}

private fun RuntimeField.supportsMrzScan(): Boolean {
    val searchable = "${id.lowercase()} ${label.lowercase()} ${hint.orEmpty().lowercase()}"
    return searchable.contains("mrz") ||
        searchable.contains("passport") ||
        searchable.contains("travel document") ||
        searchable.contains("document number") ||
        searchable.contains("expiry date") ||
        searchable.contains("issuing state") ||
        id in setOf(
            "document_number",
            "id_number",
            "travel_document_number",
            "passport_number",
            "document_type_code",
            "issuing_state_or_org",
            "surname_primary_identifier",
            "given_names_secondary_identifier",
            "traveller_full_name",
            "full_name",
            "full_name_viz",
            "nationality",
            "nationality_code",
            "sex",
            "date_of_birth",
            "dob",
            "document_expiry_date",
            "expiry_date"
        )
}

private fun fallbackStepTitle(index: Int, fields: List<RuntimeField>): String {
    val ids = fields.map { it.id.lowercase() }
    val labels = fields
        .filter { it.type != RuntimeFieldType.NOTE }
        .map { it.label }

    return when {
        ids.any { it.contains("movement") || it.contains("transport") } -> "Movement"
        ids.any { it.contains("document") || it.contains("passport") || it.contains("mrz") || it.contains("visa") || it.contains("id_") } -> "Document"
        ids.any { it.contains("name") || it.contains("sex") || it.contains("birth") || it.contains("nationality") } -> "Traveller"
        ids.any { it.contains("origin") || it.contains("destination") || it.contains("purpose") || it.contains("travel") } -> "Travel Details"
        labels.size == 1 -> labels.first()
        else -> "Step ${index + 1}"
    }
}

private fun validateStep(
    step: FormStep,
    answers: Map<String, List<String>>
): Map<String, String> {
    return step.fields
        .filter { it.required && it.type != RuntimeFieldType.NOTE }
        .mapNotNull { field ->
            val hasAnswer = answers[field.id].orEmpty().any { it.isNotBlank() }
            if (hasAnswer) {
                null
            } else {
                field.id to "${field.label} is required."
            }
        }
        .toMap()
}
