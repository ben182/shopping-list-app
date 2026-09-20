<?php

namespace App\Mealie;

use Native\Mobile\Facades\SecureStorage;
use Native\Mobile\SecureStorageAccessibility;
use Native\Mobile\SecureStorageResult;

/**
 * Das Mealie-API-Token — der einzige Weg an den Schlüssel im Keystore.
 *
 * Gelesen wird ausschließlich über `read()`: „nichts hinterlegt“ und „das
 * Gerät ist gesperrt“ sehen als `null` gleich aus, und wer beides verwechselt,
 * löscht irgendwann ein Token, auf das er nur hätte warten müssen.
 */
final class Token
{
    public const SCHLUESSEL = 'einkaufsliste.mealie-token';

    public function lesen(): SecureStorageResult
    {
        return SecureStorage::read(self::SCHLUESSEL);
    }

    /**
     * Geschrieben wird mit `AfterFirstUnlock`, damit ein Hintergrund-Abgleich
     * das Token auch bei gesperrtem Bildschirm noch entschlüsseln darf.
     */
    public function speichern(string $token): bool
    {
        return SecureStorage::set(self::SCHLUESSEL, $token, SecureStorageAccessibility::AfterFirstUnlock);
    }

    public function loeschen(): bool
    {
        return SecureStorage::delete(self::SCHLUESSEL);
    }
}
