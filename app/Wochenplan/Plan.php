<?php

namespace App\Wochenplan;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Holt die Wochenplan-Einträge einer Woche von Mealie.
 *
 * Wie App\Mealie\Einkaufsliste bewusst statisch und abhängigkeitsfrei: der
 * Aufruf läuft über `AsyncTask` in einem eigenen Interpreter, und über diese
 * Grenze geht nur, was sich serialisieren lässt — deshalb flache Arrays als
 * Rückgabe statt Objekte.
 */
final class Plan
{
    /**
     * @return array{eintraege: list<array<string, mixed>>}|array{fehler: string}
     */
    public static function laden(string $basisUrl, string $token, string $start, string $ende, int $timeout): array
    {
        try {
            $antwort = Http::withToken($token)
                ->timeout($timeout)
                ->acceptJson()
                ->get(rtrim($basisUrl, '/').'/api/households/mealplans', [
                    'start_date' => $start,
                    'end_date' => $ende,
                    'perPage' => 100,
                ]);

            if ($antwort->unauthorized()) {
                return ['fehler' => 'token'];
            }

            if ($antwort->failed()) {
                return ['fehler' => 'http'];
            }

            $rohdaten = $antwort->json() ?? [];
        } catch (ConnectionException) {
            return ['fehler' => 'netz'];
        }

        $items = is_array($rohdaten['items'] ?? null) ? $rohdaten['items'] : [];

        return ['eintraege' => array_values(array_map(
            fn (array $eintrag) => self::eintrag($eintrag),
            array_filter($items, 'is_array'),
        ))];
    }

    /**
     * @param  array<string, mixed>  $eintrag
     * @return array<string, mixed>
     */
    private static function eintrag(array $eintrag): array
    {
        $rezept = is_array($eintrag['recipe'] ?? null) ? $eintrag['recipe'] : [];

        $name = self::text($rezept['name'] ?? null);
        $slug = self::text($rezept['slug'] ?? null);

        return [
            'id' => (string) ($eintrag['id'] ?? ''),
            // Mealie schickt `date` als reines Datum; ein Zeitanteil wäre hier
            // ein Fremdkörper und würde den Tagesvergleich brechen.
            'datum' => substr((string) ($eintrag['date'] ?? ''), 0, 10),
            'typ' => (string) ($eintrag['entryType'] ?? ''),
            'rezeptName' => $name,
            'rezeptSlug' => $slug,
            'rezeptId' => self::text($rezept['id'] ?? null),
            'hatBild' => self::text($rezept['image'] ?? null) !== null,
            'titel' => (string) ($eintrag['title'] ?? ''),
            'text' => (string) ($eintrag['text'] ?? ''),
        ];
    }

    private static function text(mixed $wert): ?string
    {
        return is_string($wert) && $wert !== '' ? $wert : null;
    }
}
