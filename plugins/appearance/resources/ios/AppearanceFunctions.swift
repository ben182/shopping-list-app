import Foundation
import UIKit

/// Setzt den Hell/Dunkel-Modus für diese App allein.
///
/// `overrideUserInterfaceStyle` auf jedem Fenster zieht die ganze App nach:
/// SwiftUI-Screens, TabView, Navigationsleiste und die nativen Alerts, die
/// über das Fenster laufen. `.unspecified` nimmt die Überschreibung zurück,
/// die App folgt danach wieder dem System.
///
/// Namespace: "Appearance.*"
enum AppearanceFunctions {

    private static func style(for mode: String) -> UIUserInterfaceStyle? {
        switch mode {
        case "light":
            return .light
        case "dark":
            return .dark
        case "system":
            return .unspecified
        default:
            return nil
        }
    }

    /// Parameters: mode ("system", "light", "dark")
    /// Returns: success, code
    final class Set: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let mode = parameters["mode"] as? String else {
                return ["success": false, "code": "MISSING_MODE"]
            }

            guard let style = AppearanceFunctions.style(for: mode) else {
                return ["success": false, "code": "UNKNOWN_MODE"]
            }

            // Fenster gehören dem Main-Thread; der Bridge-Aufruf kommt vom
            // PHP-Thread.
            DispatchQueue.main.async {
                for scene in UIApplication.shared.connectedScenes {
                    guard let windowScene = scene as? UIWindowScene else {
                        continue
                    }

                    for window in windowScene.windows {
                        window.overrideUserInterfaceStyle = style
                    }
                }
            }

            return ["success": true]
        }
    }
}
