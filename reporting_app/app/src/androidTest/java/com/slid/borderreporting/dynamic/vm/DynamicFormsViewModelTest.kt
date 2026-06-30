package com.slid.borderreporting.dynamic.vm

import android.content.Context
import androidx.room.Room
import androidx.test.core.app.ApplicationProvider
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.slid.borderreporting.dynamic.local.DynamicDatabase
import com.slid.borderreporting.dynamic.model.SubmissionBatchResponse
import com.slid.borderreporting.dynamic.model.SubmissionRejection
import com.slid.borderreporting.dynamic.repo.DynamicFormRepository
import com.slid.borderreporting.dynamic.test.FakeFormServiceApi
import com.slid.borderreporting.dynamic.test.MainDispatcherRule
import com.slid.borderreporting.dynamic.test.insertActiveForm
import com.slid.borderreporting.dynamic.test.simpleRuntimeForm
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runTest
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class DynamicFormsViewModelTest {

    @get:Rule
    val mainDispatcherRule = MainDispatcherRule()

    private lateinit var db: DynamicDatabase
    private lateinit var api: FakeFormServiceApi
    private lateinit var repository: DynamicFormRepository

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
            api = api
        )
    }

    @After
    fun tearDown() {
        db.close()
    }

    @Test
    fun init_populatesCalculatedFieldsFromActiveForm() = runTest {
        insertActiveForm(db, simpleRuntimeForm())

        val viewModel = DynamicFormsViewModel(repository)
        advanceUntilIdle()

        val state = viewModel.uiState.value

        assertEquals("Falaba Border Post", state.answers["border_post"]?.first())
        assertEquals("Falaba Border Post", state.answers["border_post_copy"]?.first())
    }

    @Test
    fun visibleFields_honorsRelevantRule() = runTest {
        insertActiveForm(db, simpleRuntimeForm())

        val viewModel = DynamicFormsViewModel(repository)
        advanceUntilIdle()

        val before = viewModel.visibleFields().map { it.id }
        assertTrue(before.contains("movement_type"))
        assertFalse(before.contains("entry_gate_code"))

        viewModel.updateSingleValue("movement_type", "entry")
        advanceUntilIdle()

        val after = viewModel.visibleFields().map { it.id }
        assertTrue(after.contains("entry_gate_code"))
    }

    @Test
    fun finalizeSubmission_withMissingRequiredFields_setsValidationErrors() = runTest {
        insertActiveForm(db, simpleRuntimeForm())

        val viewModel = DynamicFormsViewModel(repository)
        advanceUntilIdle()

        viewModel.finalizeSubmission()
        advanceUntilIdle()

        val state = viewModel.uiState.value

        assertEquals("Complete the required fields.", state.message)
        assertTrue(state.validationErrors.containsKey("movement_type"))
        assertTrue(state.validationErrors.containsKey("traveller_full_name"))
    }

    @Test
    fun finalizeSubmission_withValidAnswers_resetsAnswersAndStoresSubmission() = runTest {
        insertActiveForm(db, simpleRuntimeForm())

        val viewModel = DynamicFormsViewModel(repository)
        advanceUntilIdle()

        viewModel.updateSingleValue("movement_type", "entry")
        viewModel.updateSingleValue("traveller_full_name", "Abu Koroma")
        viewModel.updateSingleValue("entry_gate_code", "Gate-01")
        advanceUntilIdle()

        viewModel.finalizeSubmission()
        advanceUntilIdle()

        val state = viewModel.uiState.value
        val stored = db.submissionDao().observeAll().first()

        assertEquals("Submission saved but not sent. Sign in online before using server sync. It will retry when connectivity returns.", state.message)
        assertEquals(1, stored.size)
        assertEquals("pending_sync", stored.first().status)
        assertEquals("Falaba Border Post", state.answers["border_post"]?.first())
        assertEquals("Falaba Border Post", state.answers["border_post_copy"]?.first())
        assertTrue(state.answers["traveller_full_name"].isNullOrEmpty())
    }

    @Test
    fun finalizeSubmission_whenServerRejectsReport_tellsUserItWasNotSent() = runTest {
        insertActiveForm(db, simpleRuntimeForm())
        repository.login("test@example.com", "password", "test-device")

        val viewModel = DynamicFormsViewModel(repository)
        advanceUntilIdle()

        viewModel.updateSingleValue("movement_type", "entry")
        viewModel.updateSingleValue("traveller_full_name", "Abu Koroma")
        viewModel.updateSingleValue("entry_gate_code", "Gate-01")
        advanceUntilIdle()

        viewModel.finalizeSubmission()
        advanceUntilIdle()

        val storedBeforeResponse = db.submissionDao().observeAll().first()
        val localId = storedBeforeResponse.first().localId
        api.submissionBatchResponse = SubmissionBatchResponse(
            rejected = listOf(
                SubmissionRejection(
                    localId = localId,
                    reason = "Document number is required."
                )
            )
        )

        viewModel.syncPending(defaultDeviceId = "test-device")
        advanceUntilIdle()

        val state = viewModel.uiState.value
        val stored = db.submissionDao().observeAll().first().first()

        assertEquals("1 report(s) were not sent. Open Stored Submissions for reasons.", state.message)
        assertEquals("failed", stored.status)
        assertEquals("Document number is required.", stored.syncError)
    }
}
