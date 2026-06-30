package com.slid.borderreporting.dynamic.sync

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.slid.borderreporting.dynamic.di.DynamicRepositoryFactory
import com.slid.borderreporting.dynamic.device.DeviceIdentity

class DynamicSubmissionSyncWorker(
    appContext: Context,
    params: WorkerParameters
) : CoroutineWorker(appContext, params) {

    override suspend fun doWork(): Result {
        val repository = DynamicRepositoryFactory.create(applicationContext)

        return try {
            repository.syncPending(deviceId = repository.storedDeviceId().ifBlank {
                DeviceIdentity.getOrCreate(applicationContext)
            })
            Result.success()
        } catch (_: Exception) {
            Result.retry()
        }
    }

    companion object {
        const val PERIODIC_WORK_NAME = "dynamic-submission-periodic-sync"
        const val ONE_TIME_WORK_NAME = "dynamic-submission-immediate-sync"
    }
}
