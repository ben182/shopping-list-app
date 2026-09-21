<?php

namespace App\Vorrat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Holt die Mealie-Vorratsliste und übersetzt sie in das, was der Screen
 * zeichnet.
 *
 * Wie App\Mealie\Einkaufsliste bewusst statisch und abhängigkeitsfrei: der
 * Aufruf läuft über `AsyncTask` in einem eigenen Interpreter, und über diese
 * Grenze geht nur, was sich serialisieren lässt.
 */
final class Vorratsliste
{
    /**
     * @return array{artikel: list<array{id: string, name: string, label: ?string, lebensmittelId: ?string, laeden: list<string>, roh: array<string, mixed>}>}|array{fehler: string}
     */
    public static function laden(string $basisUrl, string $token, string $listenId, int $timeout): array
    {
        try {
            $antwort = Http::withToken($token)
                ->timeout($timeout)
                ->acceptJson()
                ->get(rtrim($basisUrl, '/').'/api/households/shopping/lists/'.$listenId);

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

        $artikel = is_array($rohdaten['listItems'] ?? null) ? $rohdaten['listItems'] : [];

        return ['artikel' => array_map(self::artikel(...), self::sortiert($artikel))];
    }

    /**
     * Mealies eigene Reihenfolge: erst `position`, dann der Erstellzeitpunkt —
     * dieselbe wie bei der Einkaufsliste. Sie ist der Weg durch den Laden,
     * den der Katalog vorher im Code festhielt.
     *
     * @param  list<array<string, mixed>>  $artikel
     * @return list<array<string, mixed>>
     */
    private static function sortiert(array $artikel): array
    {
        usort($artikel, fn (array $a, array $b) => [(int) ($a['position'] ?? 0), (string) ($a['createdAt'] ?? '')]
            <=> [(int) ($b['position'] ?? 0), (string) ($b['createdAt'] ?? '')]);

        return array_values($artikel);
    }

    /**
     * @param  array<string, mixed>  $eintrag
     * @return array{id: string, name: string, label: ?string, lebensmittelId: ?string, laeden: list<string>, roh: array<string, mixed>}
     */
    private static function artikel(array $eintrag): array
    {
        $label = $eintrag['label']['name'] ?? $eintrag['food']['label']['name'] ?? null;
        $lebensmittelId = $eintrag['foodId'] ?? $eintrag['food']['id'] ?? null;

        return [
            'id' => (string) ($eintrag['id'] ?? ''),
            'name' => self::name($eintrag),
            'label' => is_string($label) && $label !== '' ? $label : null,
            'lebensmittelId' => is_string($lebensmittelId) && $lebensmittelId !== '' ? $lebensmittelId : null,
            'laeden' => self::laeden($eintrag),
            'roh' => $eintrag,
        ];
    }

    /**
     * Was auf der Zeile steht. `display` schreibt Mealie selbst zusammen und
     * ist bei Menge 0 — so legt die App den Vorrat an — nur der Name. Fehlt
     * es, bleiben Notiz und Lebensmittel.
     *
     * @param  array<string, mixed>  $eintrag
     */
    private static function name(array $eintrag): string
    {
        foreach ([$eintrag['display'] ?? '', $eintrag['note'] ?? '', $eintrag['food']['name'] ?? ''] as $kandidat) {
            if (trim((string) $kandidat) !== '') {
                return trim((string) $kandidat);
            }
        }

        return '';
    }

    /**
     * Die Läden aus `extras.laeden` — Mealies Extras halten nur flache
     * Zeichenketten, mehrere Läden stehen deshalb kommagetrennt. Was hier
     * fehlt, erbt der Artikel später von seiner Warengruppe.
     *
     * @param  array<string, mixed>  $eintrag
     * @return list<string>
     */
    private static function laeden(array $eintrag): array
    {
        $extras = is_array($eintrag['extras'] ?? null) ? $eintrag['extras'] : [];
        $laeden = trim((string) ($extras['laeden'] ?? ''));

        if ($laeden === '') {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $laeden))));
    }
}
