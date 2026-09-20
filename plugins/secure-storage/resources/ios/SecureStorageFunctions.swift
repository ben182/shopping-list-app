import Foundation
import Security

/// Secure storage backed by the iOS Keychain.
///
/// Every item is stored ThisDeviceOnly so it never rides an iCloud or
/// encrypted-backup restore onto another device, matching the accessibility
/// vocabulary the PHP side sends.
///
/// Namespace: "SecureStorage.*"
enum SecureStorageFunctions {

    private static var service: String {
        Bundle.main.bundleIdentifier ?? "com.nativephp.securestorage"
    }

    private static func protection(for accessibility: String?) -> CFString {
        switch accessibility {
        case "after_first_unlock":
            return kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly
        case "when_passcode_set":
            return kSecAttrAccessibleWhenPasscodeSetThisDeviceOnly
        default:
            return kSecAttrAccessibleWhenUnlockedThisDeviceOnly
        }
    }

    private static func baseQuery(_ key: String) -> [String: Any] {
        [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: key,
        ]
    }

    /// Parameters: key, value (null deletes), accessibility
    /// Returns: success
    final class Set: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let key = parameters["key"] as? String else {
                return ["success": false, "code": "MISSING_KEY"]
            }

            var query = SecureStorageFunctions.baseQuery(key)

            guard let value = parameters["value"] as? String else {
                // A null value means delete, mirroring the Android half.
                let status = SecItemDelete(query as CFDictionary)
                return ["success": status == errSecSuccess || status == errSecItemNotFound]
            }

            let accessibility = SecureStorageFunctions.protection(
                for: parameters["accessibility"] as? String
            )

            // Delete first so re-setting a key also migrates its accessibility
            // rather than silently keeping the old one.
            SecItemDelete(query as CFDictionary)

            query[kSecValueData as String] = Data(value.utf8)
            query[kSecAttrAccessible as String] = accessibility

            let status = SecItemAdd(query as CFDictionary, nil)

            if status == errSecSuccess {
                return ["success": true]
            }

            return ["success": false, "code": "WRITE_FAILED", "message": "OSStatus \(status)"]
        }
    }

    /// Parameters: key
    /// Returns: status (found|not_found|unavailable|error), value, code, message
    final class Get: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let key = parameters["key"] as? String else {
                return ["status": "error", "code": "MISSING_KEY"]
            }

            var query = SecureStorageFunctions.baseQuery(key)
            query[kSecReturnData as String] = true
            query[kSecMatchLimit as String] = kSecMatchLimitOne

            var item: CFTypeRef?
            let status = SecItemCopyMatching(query as CFDictionary, &item)

            switch status {
            case errSecSuccess:
                guard let data = item as? Data, let value = String(data: data, encoding: .utf8) else {
                    return ["status": "error", "code": "MALFORMED_ITEM"]
                }
                return ["status": "found", "value": value]

            case errSecItemNotFound:
                return ["status": "not_found", "value": ""]

            case errSecInteractionNotAllowed:
                // The device is locked too tightly to decrypt — deliberately
                // not the same as "nothing stored".
                return [
                    "status": "unavailable",
                    "code": "PROTECTED_DATA_UNAVAILABLE",
                    "message": "The device is locked and this item cannot be read.",
                ]

            default:
                return ["status": "error", "code": "READ_FAILED", "message": "OSStatus \(status)"]
            }
        }
    }

    /// Parameters: key
    /// Returns: success
    final class Delete: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let key = parameters["key"] as? String else {
                return ["success": false, "code": "MISSING_KEY"]
            }

            let status = SecItemDelete(SecureStorageFunctions.baseQuery(key) as CFDictionary)

            return ["success": status == errSecSuccess || status == errSecItemNotFound]
        }
    }
}
