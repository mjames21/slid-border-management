package com.slid.borderreporting.security

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import androidx.core.content.edit
import java.security.KeyStore
import java.security.SecureRandom
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

object DatabasePassphrase {
    private const val ANDROID_KEYSTORE = "AndroidKeyStore"
    private const val KEY_ALIAS = "slid_database_key_v1"
    private const val PREFS_NAME = "slid_secure_database"
    private const val CIPHER_TEXT_KEY = "cipher_text"
    private const val IV_KEY = "iv"
    private const val AES_MODE = "AES/GCM/NoPadding"
    private const val GCM_TAG_BITS = 128
    private const val PASSPHRASE_BYTES = 32

    fun getOrCreate(context: Context): ByteArray {
        val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val encodedCipherText = prefs.getString(CIPHER_TEXT_KEY, null)
        val encodedIv = prefs.getString(IV_KEY, null)

        if (!encodedCipherText.isNullOrBlank() && !encodedIv.isNullOrBlank()) {
            return decrypt(
                cipherText = Base64.decode(encodedCipherText, Base64.NO_WRAP),
                iv = Base64.decode(encodedIv, Base64.NO_WRAP)
            )
        }

        val passphrase = ByteArray(PASSPHRASE_BYTES).also { SecureRandom().nextBytes(it) }
        val encrypted = encrypt(passphrase)
        prefs.edit {
            putString(CIPHER_TEXT_KEY, Base64.encodeToString(encrypted.cipherText, Base64.NO_WRAP))
            putString(IV_KEY, Base64.encodeToString(encrypted.iv, Base64.NO_WRAP))
        }
        return passphrase
    }

    private fun encrypt(plainText: ByteArray): EncryptedValue {
        val cipher = Cipher.getInstance(AES_MODE)
        cipher.init(Cipher.ENCRYPT_MODE, getOrCreateSecretKey())
        return EncryptedValue(
            cipherText = cipher.doFinal(plainText),
            iv = cipher.iv
        )
    }

    private fun decrypt(cipherText: ByteArray, iv: ByteArray): ByteArray {
        val cipher = Cipher.getInstance(AES_MODE)
        cipher.init(Cipher.DECRYPT_MODE, getOrCreateSecretKey(), GCMParameterSpec(GCM_TAG_BITS, iv))
        return cipher.doFinal(cipherText)
    }

    private fun getOrCreateSecretKey(): SecretKey {
        val keyStore = KeyStore.getInstance(ANDROID_KEYSTORE).apply { load(null) }
        (keyStore.getEntry(KEY_ALIAS, null) as? KeyStore.SecretKeyEntry)?.let {
            return it.secretKey
        }

        val keyGenerator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, ANDROID_KEYSTORE)
        val spec = KeyGenParameterSpec.Builder(
            KEY_ALIAS,
            KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT
        )
            .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
            .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
            .setRandomizedEncryptionRequired(true)
            .build()

        keyGenerator.init(spec)
        return keyGenerator.generateKey()
    }

    private data class EncryptedValue(
        val cipherText: ByteArray,
        val iv: ByteArray
    )
}
