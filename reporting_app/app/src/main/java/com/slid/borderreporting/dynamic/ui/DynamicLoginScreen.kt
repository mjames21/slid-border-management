package com.slid.borderreporting.dynamic.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import com.slid.borderreporting.dynamic.vm.DynamicFormsUiState

@Composable
fun DynamicLoginScreen(
    uiState: DynamicFormsUiState,
    onServerUrlChange: (String) -> Unit,
    onSetupCodeChange: (String) -> Unit,
    onEmailChange: (String) -> Unit,
    onPasswordChange: (String) -> Unit,
    onTogglePasswordVisible: () -> Unit,
    onScanSetupQr: () -> Unit,
    onApplySetupCode: () -> Unit,
    onDeviceNameChange: (String) -> Unit,
    onLogin: () -> Unit,
    onUseDefaultDeviceName: () -> Unit,
    onClearMessage: () -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .navigationBarsPadding()
            .imePadding()
            .padding(16.dp)
    ) {
        Column(
            modifier = Modifier
                .weight(1f)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            BrandHeader(
                title = uiState.branding?.appTitle ?: "BorderReach",
                subtitle = uiState.branding?.appSubtitle ?: "Mobile border reporting",
                logoBase64 = uiState.branding?.logoBase64,
                logoSize = 64.dp
            )
            Text("Sign in online once to download active forms and enable offline capture.")

            uiState.message?.let { message ->
                Card(modifier = Modifier.fillMaxWidth()) {
                    Text(text = message, modifier = Modifier.padding(14.dp))
                }
            }

            OutlinedTextField(
                value = uiState.setupCode,
                onValueChange = onSetupCodeChange,
                label = { Text("Setup code") },
                modifier = Modifier.fillMaxWidth(),
                minLines = 2,
                maxLines = 4
            )

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedButton(
                    onClick = onScanSetupQr,
                    modifier = Modifier.weight(1f)
                ) {
                    Text("Scan QR")
                }

                OutlinedButton(
                    onClick = onApplySetupCode,
                    modifier = Modifier.weight(1f),
                    enabled = uiState.setupCode.isNotBlank()
                ) {
                    Text("Apply Code")
                }
            }

            OutlinedTextField(
                value = uiState.serverUrl,
                onValueChange = onServerUrlChange,
                label = { Text("Server URL") },
                supportingText = { Text("Use the BorderReach server URL, for example https://borderreach.slid.datahub.gov.sl/.") },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri)
            )

            OutlinedTextField(
                value = uiState.loginEmail,
                onValueChange = onEmailChange,
                label = { Text("Email") },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true
            )

            OutlinedTextField(
                value = uiState.loginPassword,
                onValueChange = onPasswordChange,
                label = { Text("Password") },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true,
                visualTransformation = if (uiState.isLoginPasswordVisible) {
                    VisualTransformation.None
                } else {
                    PasswordVisualTransformation()
                },
                trailingIcon = {
                    TextButton(onClick = onTogglePasswordVisible) {
                        Text(if (uiState.isLoginPasswordVisible) "Hide" else "Show")
                    }
                }
            )

            OutlinedTextField(
                value = uiState.deviceName,
                onValueChange = onDeviceNameChange,
                label = { Text("Device name") },
                modifier = Modifier.fillMaxWidth(),
                singleLine = true
            )

            OutlinedButton(
                onClick = onUseDefaultDeviceName,
                modifier = Modifier.fillMaxWidth()
            ) {
                Text("Use This Device ID")
            }
        }

        Button(
            onClick = {
                onClearMessage()
                onLogin()
            },
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 12.dp),
            enabled = uiState.serverUrl.isNotBlank() && uiState.loginEmail.isNotBlank() && uiState.loginPassword.isNotBlank() && uiState.deviceName.isNotBlank()
        ) {
            Text("Sign In")
        }
    }
}
