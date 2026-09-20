package de.ben182.securestorage

import android.content.Context
import android.content.SharedPreferences
import android.util.Log
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import com.nativephp.mobile.bridge.BridgeFunction

/**
 * Secure storage backed by EncryptedSharedPreferences, whose master key lives
 * in the hardware-backed Android Keystore. Values are AES-256-GCM encrypted at
 * rest; keys are encrypted deterministically so they stay look-up-able.
 *
 * Namespace: "SecureStorage.*"
 */
object SecureStorageFunctions {

    private const val TAG = "SecureStorage"
    private const val STORE = "nativephp_secure_store"

    @Volatile
    private var prefs: SharedPreferences? = null

    /**
     * Built once and cached: creating the master key touches the Keystore,
     * which is slow enough to matter on a per-call basis.
     */
    private fun store(context: Context): SharedPreferences =
        prefs ?: synchronized(this) {
            prefs ?: EncryptedSharedPreferences.create(
                context,
                STORE,
                MasterKey.Builder(context)
                    .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
                    .build(),
                EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
            ).also { prefs = it }
        }

    /**
     * Parameters: key (string), value (string|null), accessibility (ignored —
     * iOS-only concept; Android protects everything with the same master key).
     * Returns: success (bool)
     */
    class Set(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val key = parameters["key"] as? String
                ?: return mapOf("success" to false, "code" to "MISSING_KEY")

            return try {
                val value = parameters["value"] as? String

                store(context).edit().apply {
                    // A null value is the documented way to delete a key.
                    if (value == null) remove(key) else putString(key, value)
                }.commit()

                mapOf("success" to true)
            } catch (e: Exception) {
                Log.e(TAG, "Set failed for '$key': ${e.message}", e)
                mapOf("success" to false, "code" to "WRITE_FAILED", "message" to (e.message ?: "Unknown error"))
            }
        }
    }

    /**
     * Parameters: key (string)
     * Returns: status (found|not_found|error), value (string|null), code, message
     */
    class Get(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val key = parameters["key"] as? String
                ?: return mapOf("status" to "error", "code" to "MISSING_KEY")

            return try {
                val value = store(context).getString(key, null)

                if (value == null) {
                    mapOf("status" to "not_found", "value" to "")
                } else {
                    mapOf("status" to "found", "value" to value)
                }
            } catch (e: Exception) {
                Log.e(TAG, "Get failed for '$key': ${e.message}", e)
                mapOf(
                    "status" to "error",
                    "code" to "READ_FAILED",
                    "message" to (e.message ?: "Unknown error")
                )
            }
        }
    }

    /**
     * Parameters: key (string)
     * Returns: success (bool)
     */
    class Delete(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val key = parameters["key"] as? String
                ?: return mapOf("success" to false, "code" to "MISSING_KEY")

            return try {
                store(context).edit().remove(key).commit()
                mapOf("success" to true)
            } catch (e: Exception) {
                Log.e(TAG, "Delete failed for '$key': ${e.message}", e)
                mapOf("success" to false, "code" to "DELETE_FAILED")
            }
        }
    }
}
