<?php

namespace App\Mealie;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Holt die Mealie-Einkaufsliste und übersetzt sie in das, was der Screen
 * zeichnet.
 *
 * Wie App\Mealie\Verbindung bewusst statisch und abhängigkeitsfrei: der
 * Aufruf läuft über `AsyncTask` in einem eigenen Interpreter, und über diese
 * Grenze geht nur, was sich serialisieren lässt — deshalb auch flache Arrays
 * als Rückgabe statt Objekte.
 */
final class Einkaufsliste
{
    /**
     * @return array{artikel: list<array{id: string, text: string, label: ?string, rezepte: ?string, abgehakt: bool, roh: array<string, mixed>}>}|array{fehler: string}
     */
    public static function laden(string $basisUrl, string $token, string $listenId, int $timeout): array
    {
        $basisUrl = rtrim($basisUrl, '/');

        try {
            $antwort = Http::withToken($token)
                ->timeout($timeout)
                ->acceptJson()
                ->get($basisUrl.'/api/households/shopping/lists/'.$listenId);

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
        $rezeptnamen = self::rezeptnamen($rohdaten, $artikel, $basisUrl, $token, $timeout);

        return ['artikel' => array_map(
            fn (array $eintrag) => self::eintrag($eintrag, $rezeptnamen),
            self::sortiert($artikel),
        )];
    }

    /**
     * Mealies eigene Reihenfolge: erst `position`, dann der Erstellzeitpunkt.
     * Beide Felder können fehlen und sind dann 0 bzw. leer — das hält die
     * Eingangsreihenfolge, statt sie zu würfeln.
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
     * @param  array<string, string>  $rezeptnamen
     * @return array{id: string, text: string, label: ?string, rezepte: ?string, abgehakt: bool, roh: array<string, mixed>}
     */
    private static function eintrag(array $eintrag, array $rezeptnamen): array
    {
        $label = $eintrag['label']['name'] ?? $eintrag['food']['label']['name'] ?? null;

        $rezepte = array_values(array_unique(array_filter(array_map(
            fn (array $bezug) => $rezeptnamen[(string) ($bezug['recipeId'] ?? '')] ?? null,
            is_array($eintrag['recipeReferences'] ?? null) ? $eintrag['recipeReferences'] : [],
        ))));

        return [
            'id' => (string) ($eintrag['id'] ?? ''),
            'text' => (string) ($eintrag['display'] ?? ''),
            'label' => is_string($label) && $label !== '' ? $label : null,
            'rezepte' => $rezepte === [] ? null : implode(' · ', $rezepte),
            'abgehakt' => (bool) ($eintrag['checked'] ?? false),
            'roh' => $eintrag,
        ];
    }

    /**
     * Rezept-ID auf Rezeptnamen. Die Namen stehen meist schon in der Antwort —
     * Mealie hängt jedes auf der Liste referenzierte Rezept oben an. Nur was
     * dort fehlt, wird einzeln nachgeladen; die Namen gelten für den ganzen
     * Ladevorgang, ein Rezept wird also höchstens einmal geholt.
     *
     * @param  array<string, mixed>  $rohdaten
     * @param  list<array<string, mixed>>  $artikel
     * @return array<string, string>
     */
    private static function rezeptnamen(array $rohdaten, array $artikel, string $basisUrl, string $token, int $timeout): array
    {
        $namen = [];

        foreach (is_array($rohdaten['recipeReferences'] ?? null) ? $rohdaten['recipeReferences'] : [] as $bezug) {
            $id = (string) ($bezug['recipeId'] ?? $bezug['recipe']['id'] ?? '');
            $name = $bezug['recipe']['name'] ?? null;

            if ($id !== '' && is_string($name) && $name !== '') {
                $namen[$id] = $name;
            }
        }

        foreach (self::fehlendeRezeptIds($artikel, $namen) as $id) {
            $name = self::rezeptname($basisUrl, $token, $timeout, $id);

            if ($name !== null) {
                $namen[$id] = $name;
            }
        }

        return $namen;
    }

    /**
     * @param  list<array<string, mixed>>  $artikel
     * @param  array<string, string>  $namen
     * @return list<string>
     */
    private static function fehlendeRezeptIds(array $artikel, array $namen): array
    {
        $fehlend = [];

        foreach ($artikel as $eintrag) {
            foreach (is_array($eintrag['recipeReferences'] ?? null) ? $eintrag['recipeReferences'] : [] as $bezug) {
                $id = (string) ($bezug['recipeId'] ?? '');

                if ($id !== '' && ! isset($namen[$id])) {
                    $fehlend[$id] = $id;
                }
            }
        }

        return array_values($fehlend);
    }

    /**
     * Ein einzelnes Rezept nachschlagen. Scheitert das, bleibt die Zeile
     * ohne Untertitel — kein Grund, die ganze Liste fallen zu lassen.
     */
    private static function rezeptname(string $basisUrl, string $token, int $timeout, string $rezeptId): ?string
    {
        try {
            $antwort = Http::withToken($token)
                ->timeout($timeout)
                ->acceptJson()
                ->get($basisUrl.'/api/recipes/'.$rezeptId);
        } catch (ConnectionException) {
            return null;
        }

        $name = $antwort->successful() ? $antwort->json('name') : null;

        return is_string($name) && $name !== '' ? $name : null;
    }
}
