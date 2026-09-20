<?php

namespace App\Mealie;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Meldet Mealie, dass ein Artikel abgehakt oder wieder offen ist.
 *
 * Wie App\Mealie\Einkaufsliste statisch und abhängigkeitsfrei: der Aufruf
 * läuft über `AsyncTask` in einem eigenen Interpreter.
 */
final class Artikelstatus
{
    /**
     * Mealie erwartet den ganzen Artikel zurück, nicht nur das geänderte
     * Feld — geschickt wird deshalb seine eigene Darstellung mit
     * umgelegtem Haken.
     *
     * @param  array<string, mixed>  $artikel  Mealies Darstellung des Artikels
     * @return array{ok: bool}
     */
    public static function setzen(string $basisUrl, string $token, int $timeout, array $artikel, bool $abgehakt): array
    {
        try {
            $antwort = Http::withToken($token)
                ->timeout($timeout)
                ->acceptJson()
                ->put(
                    rtrim($basisUrl, '/').'/api/households/shopping/items/'.((string) ($artikel['id'] ?? '')),
                    [...$artikel, 'checked' => $abgehakt],
                );
        } catch (ConnectionException) {
            return ['ok' => false];
        }

        return ['ok' => $antwort->successful()];
    }
}
