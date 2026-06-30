package com.slid.borderreporting.dynamic.local

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import kotlinx.coroutines.flow.Flow

@Dao
interface DynamicFormDao {
    @Query("SELECT * FROM dynamic_form_definitions WHERE isActive = 1 ORDER BY version DESC LIMIT 1")
    fun observeActiveForm(): Flow<DynamicFormDefinitionEntity?>

    @Query("SELECT * FROM dynamic_form_definitions WHERE isActive = 1 ORDER BY version DESC LIMIT 1")
    suspend fun getActiveForm(): DynamicFormDefinitionEntity?

    @Query("UPDATE dynamic_form_definitions SET isActive = 0")
    suspend fun deactivateAll(): Int

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(entity: DynamicFormDefinitionEntity): Long
}

@Dao
interface DynamicSubmissionDao {
    @Query("SELECT * FROM dynamic_submissions ORDER BY createdAt DESC")
    fun observeAll(): Flow<List<DynamicSubmissionEntity>>

    @Query("SELECT * FROM dynamic_submissions WHERE status = 'pending_sync' ORDER BY createdAt ASC")
    suspend fun getPendingSync(): List<DynamicSubmissionEntity>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(entity: DynamicSubmissionEntity): Long

    @Query("UPDATE dynamic_submissions SET status = :status, updatedAt = :updatedAt, syncError = :syncError WHERE localId = :localId")
    suspend fun updateStatus(localId: String, status: String, updatedAt: Long, syncError: String?): Int

    @Query("UPDATE dynamic_submissions SET syncAttemptCount = syncAttemptCount + 1, lastSyncAttemptAt = :attemptedAt, updatedAt = :attemptedAt, syncError = NULL WHERE localId IN (:localIds)")
    suspend fun recordSyncAttempt(localIds: List<String>, attemptedAt: Long): Int

    @Query("UPDATE dynamic_submissions SET updatedAt = :updatedAt, syncError = :syncError WHERE localId IN (:localIds)")
    suspend fun recordSyncError(localIds: List<String>, updatedAt: Long, syncError: String): Int

    @Query("UPDATE dynamic_submissions SET status = 'synced', updatedAt = :updatedAt, syncError = NULL, serverId = :serverId, serverReceivedAt = :serverReceivedAt WHERE localId = :localId")
    suspend fun markSynced(localId: String, updatedAt: Long, serverId: String?, serverReceivedAt: String?): Int

    @Query("UPDATE dynamic_submissions SET status = 'failed', updatedAt = :updatedAt, syncError = :syncError WHERE localId = :localId")
    suspend fun markRejected(localId: String, updatedAt: Long, syncError: String): Int

    @Query("SELECT COUNT(*) FROM dynamic_submissions WHERE status = 'pending_sync'")
    fun observePendingCount(): Flow<Int>
}

@Dao
interface DynamicConfigDao {
    @Query("SELECT * FROM dynamic_config WHERE key = :key LIMIT 1")
    fun observe(key: String): Flow<DynamicConfigEntity?>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(entity: DynamicConfigEntity): Long

    @Query("SELECT * FROM dynamic_config WHERE key = :key LIMIT 1")
    suspend fun get(key: String): DynamicConfigEntity?
}

@Dao
interface AuthSessionDao {
    @Query("SELECT * FROM auth_session WHERE id = 1 LIMIT 1")
    fun observeSession(): Flow<AuthSessionEntity?>

    @Query("SELECT * FROM auth_session WHERE id = 1 LIMIT 1")
    suspend fun getSession(): AuthSessionEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(entity: AuthSessionEntity): Long

    @Query("DELETE FROM auth_session")
    suspend fun clear(): Int
}
