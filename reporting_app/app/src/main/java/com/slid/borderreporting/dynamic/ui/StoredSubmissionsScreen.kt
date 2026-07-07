package com.slid.borderreporting.dynamic.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.slid.borderreporting.dynamic.model.RuntimeFormDefinition
import com.slid.borderreporting.dynamic.model.StoredSubmission
import com.slid.borderreporting.dynamic.vm.DynamicFormsUiState
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun StoredSubmissionsScreen(
    uiState: DynamicFormsUiState,
    onEditSubmission: (StoredSubmission) -> Unit,
    onBack: () -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .navigationBarsPadding()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        BrandHeader(
            title = uiState.authSession?.appTitle ?: uiState.branding?.appTitle ?: "BorderReach",
            subtitle = "Stored submissions",
            logoBase64 = uiState.authSession?.logoBase64 ?: uiState.branding?.logoBase64
        )

        LazyColumn(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            if (uiState.submissions.isEmpty()) {
                item(key = "empty-submissions") {
                    Card(modifier = Modifier.fillMaxWidth()) {
                        Column(
                            modifier = Modifier.padding(16.dp),
                            verticalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Text(
                                text = "No stored submissions",
                                style = MaterialTheme.typography.titleMedium
                            )
                            Text(
                                text = "Saved drafts and queued reports will appear here.",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                }
            }

            items(uiState.submissions, key = { it.localId }) { submission ->
                SubmissionCard(
                    submission = submission,
                    activeForm = uiState.activeForm,
                    onEditSubmission = onEditSubmission
                )
            }
        }

        OutlinedButton(
            onClick = onBack,
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Back")
        }
    }
}

@Composable
private fun SubmissionCard(
    submission: StoredSubmission,
    activeForm: RuntimeFormDefinition?,
    onEditSubmission: (StoredSubmission) -> Unit
) {
    val travellerName = answerOf(
        submission = submission,
        "traveller_full_name",
        "full_name",
        "traveller_name"
    )
    val documentNumber = answerOf(
        submission = submission,
        "travel_document_number",
        "document_number",
        "id_number"
    )

    Card(modifier = Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier.padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Text(
                text = travellerName.ifBlank { submission.formId },
                style = MaterialTheme.typography.titleMedium
            )
            Text("Document: ${documentNumber.ifBlank { "-" }}")
            Text("Status: ${submission.status.toDisplayStatus()}")
            if (submission.syncAttemptCount > 0) {
                Text("Sync Attempts: ${submission.syncAttemptCount}")
            }
            submission.lastSyncAttemptAt?.let { attemptedAt ->
                Text("Last Sync Attempt: ${attemptedAt.toDisplayText()}")
            }
            submission.serverId?.takeIf { it.isNotBlank() }?.let { serverId ->
                Text("Server Receipt: $serverId")
            }
            submission.serverReceivedAt?.takeIf { it.isNotBlank() }?.let { receivedAt ->
                Text("Server Received: $receivedAt")
            }
            submission.syncError?.takeIf { it.isNotBlank() }?.let { reason ->
                Text(
                    text = "Not sent: ${reason.toOfficerMessage(activeForm)}",
                    color = MaterialTheme.colorScheme.error,
                    style = MaterialTheme.typography.bodySmall
                )
            }
            Text("Form Version: ${submission.formVersion}")
            Text("Recorded At: ${submission.createdAt.toDisplayText()}")

            if (submission.status == "draft" || submission.status == "failed") {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Button(
                        onClick = { onEditSubmission(submission) },
                        modifier = Modifier.weight(1f)
                    ) {
                        Text(if (submission.status == "failed") "Edit / Resend" else "Open Draft")
                    }
                    OutlinedButton(
                        onClick = { onEditSubmission(submission) },
                        modifier = Modifier.weight(1f)
                    ) {
                        Text("Review")
                    }
                }
            }
        }
    }
}

private fun answerOf(
    submission: StoredSubmission,
    vararg keys: String
): String {
    return keys.firstNotNullOfOrNull { key ->
        submission.answers[key].orEmpty().firstOrNull()?.takeIf { it.isNotBlank() }
    }.orEmpty()
}

private fun Long.toDisplayText(): String {
    return SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date(this))
}

private fun String.toDisplayStatus(): String {
    return when (this) {
        "draft" -> "Draft"
        "pending_sync" -> "Queued for sync"
        "synced" -> "Sent"
        "failed" -> "Not sent"
        else -> replaceFirstChar { it.uppercase() }
    }
}

private fun String.toOfficerMessage(form: RuntimeFormDefinition?): String {
    if (form == null) return this

    var friendly = this
    form.fields.forEach { field ->
        friendly = friendly.replace(field.id, field.label)
    }
    return friendly
}
