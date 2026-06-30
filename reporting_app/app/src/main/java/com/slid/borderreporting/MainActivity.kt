package com.slid.borderreporting

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import com.slid.borderreporting.dynamic.di.DynamicRepositoryFactory
import com.slid.borderreporting.dynamic.device.DeviceIdentity
import com.slid.borderreporting.dynamic.sync.DynamicSubmissionSyncWorker
import com.slid.borderreporting.dynamic.ui.DynamicFormsAppRoot
import com.slid.borderreporting.ui.theme.SLIDBorderReportingTheme
import java.util.concurrent.TimeUnit

class MainActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)

        scheduleSubmissionSync()

        val repository = DynamicRepositoryFactory.create(applicationContext)
        val deviceId = DeviceIdentity.getOrCreate(applicationContext)

        setContent {
            SLIDBorderReportingTheme {
                DynamicFormsAppRoot(repository = repository, defaultDeviceName = deviceId)
            }
        }
    }

    private fun scheduleSubmissionSync() {
        val constraints = Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build()

        val periodicRequest = PeriodicWorkRequestBuilder<DynamicSubmissionSyncWorker>(
            15,
            TimeUnit.MINUTES
        )
            .setConstraints(constraints)
            .build()

        val oneTimeRequest = OneTimeWorkRequestBuilder<DynamicSubmissionSyncWorker>()
            .setConstraints(constraints)
            .build()

        val workManager = WorkManager.getInstance(applicationContext)

        workManager.enqueueUniquePeriodicWork(
            DynamicSubmissionSyncWorker.PERIODIC_WORK_NAME,
            ExistingPeriodicWorkPolicy.UPDATE,
            periodicRequest
        )

        workManager.enqueueUniqueWork(
            DynamicSubmissionSyncWorker.ONE_TIME_WORK_NAME,
            ExistingWorkPolicy.REPLACE,
            oneTimeRequest
        )
    }
}
