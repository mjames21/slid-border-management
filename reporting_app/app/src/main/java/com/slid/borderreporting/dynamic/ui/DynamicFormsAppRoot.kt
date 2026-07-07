package com.slid.borderreporting.dynamic.ui

import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.ContextCompat
import androidx.lifecycle.viewmodel.compose.viewModel
import com.slid.borderreporting.dynamic.repo.DynamicFormRepository
import com.slid.borderreporting.dynamic.vm.DynamicFormsViewModel

private enum class DynamicScreen {
    LOGIN,
    SETUP_QR_SCANNER,
    HOME,
    FORM,
    MRZ_SCANNER,
    SUBMISSIONS
}

@Composable
fun DynamicFormsAppRoot(
    repository: DynamicFormRepository,
    defaultDeviceName: String
) {
    val factory = remember(repository) { DynamicFormsViewModelFactory(repository) }
    val viewModel: DynamicFormsViewModel = viewModel(factory = factory)
    val uiState by viewModel.uiState.collectAsState()
    val currentScreen = remember { mutableStateOf(DynamicScreen.HOME) }
    val context = LocalContext.current
    var locationPermissionRequested by remember { mutableStateOf(false) }
    val locationPermissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestMultiplePermissions()
    ) {
        locationPermissionRequested = true
    }

    val targetScreen = when {
        uiState.authSession == null && currentScreen.value == DynamicScreen.SETUP_QR_SCANNER -> DynamicScreen.SETUP_QR_SCANNER
        uiState.authSession == null -> DynamicScreen.LOGIN
        currentScreen.value == DynamicScreen.SETUP_QR_SCANNER -> DynamicScreen.HOME
        else -> currentScreen.value
    }

    LaunchedEffect(defaultDeviceName, uiState.authSession) {
        if (uiState.authSession == null && uiState.deviceName.isBlank()) {
            viewModel.updateDeviceName(defaultDeviceName)
        }
    }

    LaunchedEffect(uiState.authSession) {
        if (uiState.authSession != null && !locationPermissionRequested && !context.hasLocationPermission()) {
            locationPermissionLauncher.launch(
                arrayOf(
                    Manifest.permission.ACCESS_FINE_LOCATION,
                    Manifest.permission.ACCESS_COARSE_LOCATION
                )
            )
        }
    }

    when (targetScreen) {
        DynamicScreen.LOGIN -> DynamicLoginScreen(
            uiState = uiState,
            onServerUrlChange = viewModel::updateServerUrl,
            onSetupCodeChange = viewModel::updateSetupCode,
            onEmailChange = viewModel::updateLoginEmail,
            onPasswordChange = viewModel::updateLoginPassword,
            onTogglePasswordVisible = viewModel::toggleLoginPasswordVisible,
            onScanSetupQr = { currentScreen.value = DynamicScreen.SETUP_QR_SCANNER },
            onApplySetupCode = viewModel::applyManualSetupCode,
            onDeviceNameChange = viewModel::updateDeviceName,
            onLogin = viewModel::login,
            onUseDefaultDeviceName = { viewModel.updateDeviceName(defaultDeviceName) },
            onClearMessage = viewModel::clearMessage
        )

        DynamicScreen.SETUP_QR_SCANNER -> SetupQrScannerScreen(
            onQrCode = { setupCode ->
                viewModel.applySetupCode(setupCode)
                currentScreen.value = DynamicScreen.HOME
            },
            onBack = { currentScreen.value = DynamicScreen.HOME }
        )

        DynamicScreen.HOME -> DynamicHomeScreen(
            uiState = uiState,
            onOpenForm = {
                viewModel.startNewSubmission()
                currentScreen.value = DynamicScreen.FORM
            },
            onOpenSubmissions = { currentScreen.value = DynamicScreen.SUBMISSIONS },
            onRefreshConfig = viewModel::refreshConfig,
            onSyncPending = { viewModel.syncPending(defaultDeviceName) },
            onServerUrlChange = viewModel::updateServerUrl,
            onSaveServerUrl = viewModel::saveCurrentServerUrl,
            onLogout = viewModel::logout,
            onClearMessage = viewModel::clearMessage
        )

        DynamicScreen.FORM -> DynamicFormScreen(
            uiState = uiState,
            visibleFields = viewModel.visibleFields(),
            optionsFor = viewModel::optionsFor,
            onSingleValueChange = viewModel::updateSingleValue,
            onMultiValueToggle = viewModel::toggleMultiValue,
            onSaveDraft = viewModel::saveDraft,
            onFinalize = { viewModel.finalizeSubmission(defaultDeviceName) },
            onScanMrz = { currentScreen.value = DynamicScreen.MRZ_SCANNER },
            onBack = { currentScreen.value = DynamicScreen.HOME },
            onClearMessage = viewModel::clearMessage
        )

        DynamicScreen.MRZ_SCANNER -> MrzScannerScreen(
            onMrzCaptured = { parsedMrz ->
                viewModel.applyMrzScan(parsedMrz)
                currentScreen.value = DynamicScreen.FORM
            },
            onBack = { currentScreen.value = DynamicScreen.FORM }
        )

        DynamicScreen.SUBMISSIONS -> StoredSubmissionsScreen(
            uiState = uiState,
            onEditSubmission = { submission ->
                viewModel.editStoredSubmission(submission)
                currentScreen.value = DynamicScreen.FORM
            },
            onBack = { currentScreen.value = DynamicScreen.HOME }
        )
    }
}

private fun android.content.Context.hasLocationPermission(): Boolean {
    val fine = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION)
    val coarse = ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION)

    return fine == PackageManager.PERMISSION_GRANTED || coarse == PackageManager.PERMISSION_GRANTED
}
