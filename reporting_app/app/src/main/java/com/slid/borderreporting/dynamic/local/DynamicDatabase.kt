package com.slid.borderreporting.dynamic.local

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.migration.Migration
import androidx.sqlite.db.SupportSQLiteDatabase
import com.slid.borderreporting.security.DatabasePassphrase
import net.sqlcipher.database.SupportFactory

@Database(
    entities = [
        DynamicFormDefinitionEntity::class,
        DynamicSubmissionEntity::class,
        DynamicConfigEntity::class,
        AuthSessionEntity::class
    ],
    version = 9,
    exportSchema = true
)
abstract class DynamicDatabase : RoomDatabase() {
    abstract fun formDao(): DynamicFormDao
    abstract fun submissionDao(): DynamicSubmissionDao
    abstract fun configDao(): DynamicConfigDao
    abstract fun authSessionDao(): AuthSessionDao

    companion object {
        @Volatile
        private var instance: DynamicDatabase? = null

        fun get(context: Context): DynamicDatabase {
            return instance ?: synchronized(this) {
                instance ?: Room.databaseBuilder(
                    context.applicationContext,
                    DynamicDatabase::class.java,
                    "slid_dynamic_forms.db"
                )
                    .openHelperFactory(SupportFactory(DatabasePassphrase.getOrCreate(context), null, false))
                    .addMigrations(
                        MIGRATION_1_2,
                        MIGRATION_2_3,
                        MIGRATION_3_4,
                        MIGRATION_4_5,
                        MIGRATION_5_6,
                        MIGRATION_6_7,
                        MIGRATION_7_8,
                        MIGRATION_8_9
                    )
                    .build()
                    .also { instance = it }
            }
        }

        private val MIGRATION_1_2 = object : Migration(1, 2) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL(
                    """
                    CREATE TABLE IF NOT EXISTS auth_session (
                        id INTEGER NOT NULL PRIMARY KEY,
                        token TEXT NOT NULL,
                        tokenType TEXT NOT NULL,
                        userId INTEGER NOT NULL,
                        userName TEXT NOT NULL,
                        userEmail TEXT NOT NULL,
                        deviceId TEXT NOT NULL,
                        updatedAt INTEGER NOT NULL
                    )
                    """.trimIndent()
                )
            }
        }

        private val MIGRATION_2_3 = object : Migration(2, 3) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL("ALTER TABLE auth_session ADD COLUMN role TEXT")
                db.execSQL("ALTER TABLE auth_session ADD COLUMN borderPostCode TEXT")
                db.execSQL("ALTER TABLE auth_session ADD COLUMN borderPostName TEXT")
                db.execSQL("ALTER TABLE auth_session ADD COLUMN borderPostRegion TEXT")
            }
        }

        private val MIGRATION_3_4 = object : Migration(3, 4) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL("ALTER TABLE auth_session ADD COLUMN countryCode TEXT")
            }
        }

        private val MIGRATION_4_5 = object : Migration(4, 5) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL("ALTER TABLE auth_session ADD COLUMN appTitle TEXT")
                db.execSQL("ALTER TABLE auth_session ADD COLUMN appSubtitle TEXT")
                db.execSQL("ALTER TABLE auth_session ADD COLUMN logoMimeType TEXT")
                db.execSQL("ALTER TABLE auth_session ADD COLUMN logoBase64 TEXT")
            }
        }

        private val MIGRATION_5_6 = object : Migration(5, 6) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL("ALTER TABLE auth_session ADD COLUMN borderPostLatitude REAL")
                db.execSQL("ALTER TABLE auth_session ADD COLUMN borderPostLongitude REAL")
                db.execSQL("ALTER TABLE auth_session ADD COLUMN allowedRadiusMeters INTEGER")
            }
        }

        private val MIGRATION_6_7 = object : Migration(6, 7) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL("ALTER TABLE dynamic_submissions ADD COLUMN deviceLatitude REAL")
                db.execSQL("ALTER TABLE dynamic_submissions ADD COLUMN deviceLongitude REAL")
                db.execSQL("ALTER TABLE dynamic_submissions ADD COLUMN deviceLocationAccuracyMeters REAL")
                db.execSQL("ALTER TABLE dynamic_submissions ADD COLUMN deviceLocationCapturedAt INTEGER")
            }
        }

        private val MIGRATION_7_8 = object : Migration(7, 8) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL("ALTER TABLE dynamic_submissions ADD COLUMN syncAttemptCount INTEGER NOT NULL DEFAULT 0")
                db.execSQL("ALTER TABLE dynamic_submissions ADD COLUMN lastSyncAttemptAt INTEGER")
                db.execSQL("ALTER TABLE dynamic_submissions ADD COLUMN serverId TEXT")
                db.execSQL("ALTER TABLE dynamic_submissions ADD COLUMN serverReceivedAt TEXT")
            }
        }

        private val MIGRATION_8_9 = object : Migration(8, 9) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL("ALTER TABLE auth_session ADD COLUMN borderPostDigitalAddress TEXT")
            }
        }
    }
}
