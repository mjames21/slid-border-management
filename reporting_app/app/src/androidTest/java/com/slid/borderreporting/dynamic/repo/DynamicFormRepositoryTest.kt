package com.slid.borderreporting.dynamic.repo

import android.content.Context
import androidx.room.Room
import androidx.test.core.app.ApplicationProvider
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.slid.borderreporting.dynamic.local.DynamicDatabase
import com.slid.borderreporting.dynamic.location.DeviceGeoPoint
import com.slid.borderreporting.dynamic.location.DeviceLocationProvider
import com.slid.borderreporting.dynamic.model.AnswerCodec
import com.slid.borderreporting.dynamic.model.MobileConfigResponse
import com.slid.borderreporting.dynamic.model.SubmissionAcceptance
import com.slid.borderreporting.dynamic.model.SubmissionBatchResponse
import com.slid.borderreporting.dynamic.model.SubmissionRejection
import com.slid.borderreporting.dynamic.test.FakeFormServiceApi
import com.slid.borderreporting.dynamic.test.insertActiveForm
import com.slid.borderreporting.dynamic.test.MainDispatcherRule
import com.slid.borderreporting.dynamic.test.simpleRuntimeForm
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.runTest
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class DynamicFormRepositoryTest {

    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private lateinit var db: DynamicDatabase
    private lateinit var api: FakeFormServiceApi
    private lateinit var repository: DynamicFormRepository
    private val deviceLocationProvider = FakeDeviceLocationProvider(
        DeviceGeoPoint(
            latitude = 9.7401,
            longitude = -11.6502,
            accuracyMeters = 12.5f,
            capturedAt = 1_718_000_000_500
        )
    )

    @Before
    fun setUp() {
        val context = ApplicationProvider.getApplicationContext<Context>()
        db = Room.inMemoryDatabaseBuilder(
            context,
            DynamicDatabase::class.java
        ).allowMainThreadQueries().build()

        api = FakeFormServiceApi()
        repository = DynamicFormRepository(
            formDao = db.formDao(),
            submissionDao = db.submissionDao(),
            configDao = db.configDao(),
            authSessionDao = db.authSessionDao(),
            api = api,
            locationProvider = deviceLocationProvider
        )
    }

    @After
    fun tearDown() {
        db.close()
    }

    @Test
    fun observeActiveForm_readsDownloadedOrInsertedForm() = runTest {
        insertActiveForm(db, simpleRuntimeForm())

        val activeForm = repository.observeActiveForm().first()

        assertNotNull(activeForm)
        assertEquals("SLID Border Reporting", activeForm?.title)
        assertTrue(activeForm?.fields?.isNotEmpty() == true)
    }

    @Test
    fun finalizeSubmission_storesPendingSyncSubmission() = runTest {
        insertActiveForm(db, simpleRuntimeForm())
        val form = repository.observeActiveForm().first() ?: error("Expected active form")

        repository.finalizeSubmission(
            form = form,
            answers = mapOf(
                "movement_type" to listOf("entry"),
                "transport_mode" to listOf("foot"),
                "full_name" to listOf("Mariama Kamara"),
                "id_number" to listOf("A1234567"),
                "nationality" to listOf("SLE")
            )
        )

        val stored = db.submissionDao().getPendingSync()

        assertEquals(1, stored.size)
        assertEquals("pending_sync", stored.first().status)
        assertEquals(0, stored.first().syncAttemptCount)
        assertEquals(9.7401, stored.first().deviceLatitude ?: 0.0, 0.000001)
        assertEquals(-11.6502, stored.first().deviceLongitude ?: 0.0, 0.000001)

        val decoded = AnswerCodec.decode(stored.first().answersJson)
        assertEquals("Mariama Kamara", decoded["full_name"]?.first())
        assertEquals("A1234567", decoded["id_number"]?.first())
    }

    @Test
    fun refreshConfig_replacesActiveFormWithDownloadedForm() = runTest {
        val downloadedForm = simpleRuntimeForm()
        api.mobileConfigResponse = MobileConfigResponse(
            activeForms = listOf(downloadedForm)
        )
        repository.login("test@example.com", "password", "test-device")

        repository.refreshConfig()

        val activeForm = repository.observeActiveForm().first() ?: error("Expected active form")

        assertEquals(downloadedForm.formId, activeForm.formId)
        assertEquals(downloadedForm.version, activeForm.version)
        assertEquals(downloadedForm.title, activeForm.title)
    }

    @Test
    fun syncPending_marksAcceptedAndRejectedSubmissions() = runTest {
        insertActiveForm(db, simpleRuntimeForm())
        val form = repository.observeActiveForm().first() ?: error("Expected active form")
        repository.login("test@example.com", "password", "test-device")

        repository.finalizeSubmission(
            form = form,
            answers = mapOf(
                "movement_type" to listOf("entry"),
                "transport_mode" to listOf("foot"),
                "full_name" to listOf("Accepted Person"),
                "id_number" to listOf("DOC-1"),
                "nationality" to listOf("SLE")
            )
        )

        repository.finalizeSubmission(
            form = form,
            answers = mapOf(
                "movement_type" to listOf("exit"),
                "transport_mode" to listOf("car"),
                "full_name" to listOf("Rejected Person"),
                "id_number" to listOf("DOC-2"),
                "nationality" to listOf("SLE")
            )
        )

        val pendingBefore = db.submissionDao().getPendingSync()
        val acceptedId = pendingBefore[0].localId
        val rejectedId = pendingBefore[1].localId

        api.submissionBatchResponse = SubmissionBatchResponse(
            acceptedIds = listOf(acceptedId),
            accepted = listOf(
                SubmissionAcceptance(
                    localId = acceptedId,
                    serverId = "srv-accepted-1",
                    receivedAt = "2026-06-20T12:00:00Z"
                )
            ),
            rejected = listOf(
                SubmissionRejection(
                    localId = rejectedId,
                    reason = "Validation failed"
                )
            )
        )

        val syncSummary = repository.syncPending(deviceId = "test-device")
        val syncedPayload = api.lastSubmissionBatchRequest?.submissions?.first { it.localId == acceptedId }

        val all = db.submissionDao().observeAll().first()
        val accepted = all.first { it.localId == acceptedId }
        val rejected = all.first { it.localId == rejectedId }

        assertEquals(2, syncSummary.attemptedCount)
        assertEquals(1, syncSummary.acceptedCount)
        assertEquals(1, syncSummary.rejectedCount)
        assertEquals(acceptedId, syncSummary.acceptedIds.first())
        assertEquals(rejectedId, syncSummary.rejected.first().localId)
        assertEquals(9.7401, syncedPayload?.deviceLatitude ?: 0.0, 0.000001)
        assertEquals(-11.6502, syncedPayload?.deviceLongitude ?: 0.0, 0.000001)
        assertEquals(12.5f, syncedPayload?.deviceLocationAccuracyMeters ?: 0f, 0.000001f)
        assertEquals("synced", accepted.status)
        assertEquals(null, accepted.syncError)
        assertEquals(1, accepted.syncAttemptCount)
        assertEquals("srv-accepted-1", accepted.serverId)
        assertEquals("2026-06-20T12:00:00Z", accepted.serverReceivedAt)
        assertEquals("failed", rejected.status)
        assertEquals(1, rejected.syncAttemptCount)
        assertEquals("Validation failed", rejected.syncError)
    }

    @Test
    fun syncPending_whenNetworkFails_keepsReportsQueuedWithRetryMetadata() = runTest {
        insertActiveForm(db, simpleRuntimeForm())
        val form = repository.observeActiveForm().first() ?: error("Expected active form")
        repository.login("test@example.com", "password", "test-device")

        repository.finalizeSubmission(
            form = form,
            answers = mapOf(
                "movement_type" to listOf("entry"),
                "traveller_full_name" to listOf("Offline Person")
            )
        )

        api.throwOnSync = IllegalStateException("timeout")

        val result = runCatching { repository.syncPending(deviceId = "test-device") }
        val stored = db.submissionDao().getPendingSync().first()

        assertTrue(result.isFailure)
        assertEquals("pending_sync", stored.status)
        assertEquals(1, stored.syncAttemptCount)
        assertNotNull(stored.lastSyncAttemptAt)
        assertEquals("timeout", stored.syncError)
    }

    private class FakeDeviceLocationProvider(
        private val geoPoint: DeviceGeoPoint?
    ) : DeviceLocationProvider {
        override suspend fun currentLocation(): DeviceGeoPoint? = geoPoint
    }
}
