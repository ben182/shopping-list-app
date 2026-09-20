import Foundation

/// Reicht den App-Lebenszyklus an PHP weiter.
///
/// Der AppDelegate postet `NativePHP.didBecomeActive` im NotificationCenter;
/// dieses Abonnement macht daraus ein Native-Event, das der PHP-Runloop als
/// `#[On(AppForegrounded::class)]` empfängt — dieselbe Wirkung wie die
/// Android-Hälfte über `NativePHPLifecycle.ON_RESUME`.
///
/// Namespace: "AppLifecycle"
private let foregroundedEvent = "Ben182\\AppLifecycle\\Events\\AppForegrounded"

private var foregroundObserver: NSObjectProtocol?

/// Init-Funktion aus nativephp.json, beim Start einmal aufgerufen. Der
/// Wächter verhindert ein zweites Abonnement.
func initAppLifecycle() {
    guard foregroundObserver == nil else {
        return
    }

    foregroundObserver = NotificationCenter.default.addObserver(
        forName: .didBecomeActive,
        object: nil,
        queue: .main
    ) { _ in
        LaravelBridge.shared.send?(foregroundedEvent, [:])
    }
}
