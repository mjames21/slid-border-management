package com.slid.borderreporting.dynamic.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.slid.borderreporting.dynamic.vm.DynamicFormsUiState

@Composable
fun DynamicHomeScreen(
    uiState: DynamicFormsUiState,
    onOpenForm: () -> Unit,
    onOpenSubmissions: () -> Unit,
    onRefreshConfig: () -> Unit,
    onSyncPending: () -> Unit,
    onServerUrlChange: (String) -> Unit,
    onSaveServerUrl: () -> Unit,
    onLogout: () -> Unit,
    onClearMessage: () -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .navigationBarsPadding()
            .imePadding()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        BrandHeader(
            title = uiState.authSession?.appTitle ?: uiState.branding?.appTitle ?: "BorderReach",
            subtitle = if (uiState.isLoading) {
                "Loading mobile workspace"
            } else {
                uiState.authSession?.appSubtitle ?: uiState.branding?.appSubtitle ?: "Mobile border reporting"
            },
            logoBase64 = uiState.authSession?.logoBase64 ?: uiState.branding?.logoBase64
        )

        if (uiState.isLoading) {
            CircularProgressIndicator()
        } else {
            Card(modifier = Modifier.fillMaxWidth()) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Text(
                        text = "Active Form",
                        style = MaterialTheme.typography.titleMedium
                    )
                    InfoLine(
                        label = "Form",
                        value = uiState.activeForm?.title ?: "No form downloaded."
                    )
                    uiState.activeForm?.let {
                        InfoLine(label = "Module", value = reportingModuleLabel(it.reportingModule))
                        it.standardReference?.takeIf { standard -> standard.isNotBlank() }?.let { standard ->
                            InfoLine(label = "Standard", value = standard)
                        }
                        InfoLine(label = "Version", value = "${it.formId} / ${it.version}")
                    } ?: InfoLine(
                        label = "Status",
                        value = "Download the active form from the service."
                    )
                    InfoLine(label = "Stored", value = uiState.submissions.size.toString())
                    InfoLine(label = "Pending sync", value = uiState.pendingSyncCount.toString())
                    uiState.authSession?.let { InfoLine(label = "Officer", value = it.userName) }
                    uiState.authSession?.countryCode?.let { InfoLine(label = "Country", value = it) }
                    uiState.authSession?.borderPostName?.let { postName ->
                        val postCode = uiState.authSession.borderPostCode.orEmpty()
                        InfoLine(
                            label = "Assigned post",
                            value = "$postName${if (postCode.isNotBlank()) " ($postCode)" else ""}"
                        )
                    }
                    uiState.authSession?.borderPostDigitalAddress?.let { address ->
                        InfoLine(label = "Digital address", value = address)
                    }
                    val longitude = uiState.authSession?.borderPostLongitude
                    val latitude = uiState.authSession?.borderPostLatitude
                    if (longitude != null && latitude != null) {
                        InfoLine(label = "Border lon/lat", value = "$longitude, $latitude")
                    }
                    uiState.authSession?.allowedRadiusMeters?.let { radius ->
                        InfoLine(label = "Location radius", value = "$radius m")
                    }
                    Text(
                        text = "Automatic synchronization runs when network is available.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
        }

        Card(modifier = Modifier.fillMaxWidth()) {
            Column(
                modifier = Modifier.padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Text(
                    text = "Server Connection",
                    style = MaterialTheme.typography.titleMedium
                )
                OutlinedTextField(
                    value = uiState.serverUrl,
                    onValueChange = onServerUrlChange,
                    label = { Text("Server URL") },
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true
                )
                OutlinedButton(
                    onClick = onSaveServerUrl,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text("Save Server URL")
                }
            }
        }

        uiState.message?.let { message ->
            Card(modifier = Modifier.fillMaxWidth()) {
                Text(
                    text = message,
                    modifier = Modifier.padding(16.dp)
                )
            }
        }

        Button(
            onClick = {
                onClearMessage()
                onRefreshConfig()
            },
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Refresh Form Configuration")
        }

        Button(
            onClick = onOpenForm,
            modifier = Modifier.fillMaxWidth(),
            enabled = uiState.activeForm != null
        ) {
            Text("Open Active Form")
        }

        OutlinedButton(
            onClick = onOpenSubmissions,
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("View Stored Submissions")
        }

        OutlinedButton(
            onClick = {
                onClearMessage()
                onSyncPending()
            },
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Sync Pending Submissions Now")
        }

        OutlinedButton(
            onClick = onLogout,
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Sign Out")
        }
    }
}

@Composable
private fun InfoLine(
    label: String,
    value: String
) {
    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium
        )
    }
}

private fun reportingModuleLabel(module: String): String {
    return when (module.lowercase()) {
        "immigration" -> "Immigration"
        "customs" -> "Customs"
        "security" -> "Security / Incident"
        "health" -> "Health / Quarantine"
        else -> "Other Border Service"
    }
}
