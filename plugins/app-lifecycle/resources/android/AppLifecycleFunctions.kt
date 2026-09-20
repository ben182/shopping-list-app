package de.ben182.applifecycle

import android.content.Context
import android.util.Log
import com.nativephp.mobile.lifecycle.NativePHPLifecycle
import com.nativephp.mobile.ui.nativerender.NativeElementBridge

/**
 * Reicht den Activity-Lebenszyklus an PHP weiter.
 *
 * NativePHP postet `onResume` auf seinen internen Plugin-Bus, der aber nur
 * Kotlin-Abonnenten kennt. Diese Init-Funktion hängt sich dort ein und legt
 * das Ereignis in die Element-Event-Queue — dasselbe, was der eingebaute
 * ShakeDetector mit `ShakeDetected` tut. Der blockierte PHP-Runloop wacht
 * davon auf und ruft die `#[On]`-Methode der gerade sichtbaren Komponente.
 *
 * Namespace: "AppLifecycle"
 */
private const val TAG = "AppLifecycle"

private const val FOREGROUNDED_EVENT = "Ben182\\AppLifecycle\\Events\\AppForegrounded"

@Volatile
private var angemeldet = false

/**
 * Wird beim Start der Activity genau einmal aufgerufen (Init-Funktion aus
 * nativephp.json). Der Wächter schützt davor, dass eine neu erzeugte Activity
 * ein zweites Abonnement anlegt und jedes Ereignis doppelt ankommt.
 */
fun initAppLifecycle(context: Context) {
    if (angemeldet) {
        return
    }

    angemeldet = true

    NativePHPLifecycle.on(NativePHPLifecycle.Events.ON_RESUME) {
        Log.d(TAG, "App im Vordergrund — melde $FOREGROUNDED_EVENT an PHP")
        NativeElementBridge.sendNativeEvent(FOREGROUNDED_EVENT, "{}")
    }
}
