<?php

namespace App\Mealie;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Holt die Labels, die Mealie kennt — die Warengruppen, unter die ein neu
 * angelegter Artikel gehängt werden kann.
 *
 * Ohne Label landet ein Artikel auf dem Einkaufen-Screen unter „Sonstiges“;
 * mit Label steht er dort, wo man im Laden danach greift. Die Zuordnung zu
 * den Gruppen des Katalogs macht App\Einkaufen\Gruppenwahl.
 *
 * Wie App\Mealie\Einkaufsliste statisch und abhängigkeitsfrei: der Aufruf
 * läuft über `AsyncTask` in einem eigenen Interpreter.
 */
final class Warengruppen
{
    /**
     * @return array{labels: list<array{id: string, name: string}>}|array{fehler: string}
     */
    public static function laden(string $basisUrl, string $token, int $timeout): array
    {
        try {
            $antwort = Http::withToken($token)
                ->timeout($timeout)
                ->acceptJson()
                // Ohne `perPage` gäbe Mealie zehn Labels zurück und der Rest
                // der Warengruppen fehlte stillschweigend.
                ->get(rtrim($basisUrl, '/').'/api/groups/labels', ['perPage' => 100]);

            if ($antwort->failed()) {
                return ['fehler' => $antwort->unauthorized() ? 'token' : 'http'];
            }

            $rohdaten = $antwort->json() ?? [];
        } catch (ConnectionException) {
            return ['fehler' => 'netz'];
        }

        $labels = is_array($rohdaten['items'] ?? null) ? $rohdaten['items'] : [];

        return ['labels' => array_values(array_filter(array_map(
            fn (array $label) => [
                'id' => (string) ($label['id'] ?? ''),
                'name' => (string) ($label['name'] ?? ''),
            ],
            $labels,
        ), fn (array $label) => $label['id'] !== '' && $label['name'] !== ''))];
    }
}
