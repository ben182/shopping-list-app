package de.ben182.appearance

import android.app.UiModeManager
import android.content.Context
import android.util.Log
import com.nativephp.mobile.bridge.BridgeFunction

/**
 * Setzt den Hell/Dunkel-Modus für diese App allein.
 *
 * `UiModeManager.setApplicationNightMode()` (API 31) legt eine Überschreibung
 * auf die Konfiguration des App-Prozesses. Die Activity trägt
 * `configChanges="uiMode"`, wird also nicht neu erzeugt, sondern bekommt ein
 * `onConfigurationChanged` — davon liest Compose `isSystemInDarkTheme()` neu
 * (alle Screens, Tab-Leiste, native Dialoge) und NativePHP färbt Status- und
 * Navigationsleiste um. `AppCompatDelegate` wäre der übliche Weg, greift hier
 * aber nicht: die MainActivity ist eine FragmentActivity ohne AppCompat.
 *
 * Namespace: "Appearance.*"
 */
object AppearanceFunctions {

    private const val TAG = "Appearance"

    /**
     * Parameters: mode (string) — "system", "light" oder "dark"
     * Returns: success (bool), code, message
     */
    class Set(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val mode = parameters["mode"] as? String
                ?: return mapOf("success" to false, "code" to "MISSING_MODE")

            // MODE_NIGHT_AUTO ist hier nicht „nach Uhrzeit“, sondern räumt die
            // Überschreibung ab: die App folgt danach wieder dem System.
            val nightMode = when (mode) {
                "light" -> UiModeManager.MODE_NIGHT_NO
                "dark" -> UiModeManager.MODE_NIGHT_YES
                "system" -> UiModeManager.MODE_NIGHT_AUTO
                else -> return mapOf("success" to false, "code" to "UNKNOWN_MODE")
            }

            return try {
                val uiModeManager = context.getSystemService(Context.UI_MODE_SERVICE) as UiModeManager

                uiModeManager.setApplicationNightMode(nightMode)

                mapOf("success" to true)
            } catch (e: Exception) {
                Log.e(TAG, "Set failed for '$mode': ${e.message}", e)
                mapOf(
                    "success" to false,
                    "code" to "APPLY_FAILED",
                    "message" to (e.message ?: "Unknown error")
                )
            }
        }
    }
}
