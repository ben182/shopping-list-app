<?php

namespace App\Mealie;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Legt einen Artikel auf der Mealie-Einkaufsliste an.
 *
 * Beide Wege dorthin laufen hier durch: der Tap im Vorrat, der einen
 * Vorratsartikel kopiert — kopiert, nicht verschoben, der Vorratseintrag
 * bleibt stehen —, und der von Hand getippte Artikel. Was mitgeht, ist in
 * beiden Fällen dasselbe: das Lebensmittel oder die Notiz, die Warengruppe
 * und die Läden, falls an dem Artikel eine Ausnahme steht.
 *
 * Wie App\Mealie\Einkaufsliste statisch und abhängigkeitsfrei: der Aufruf
 * läuft über `AsyncTask` in einem eigenen Interpreter.
 */
final class Artikelanlage
{
    /**
     * @param  array<string, mixed>  $vorlage  Mealies Darstellung des Artikels —
     *                                          aus dem Vorrat oder von Hand gebaut
     * @return array{ok: bool, artikel?: array<string, mixed>}
     */
    public static function ausfuehren(string $basisUrl, string $token, int $timeout, string $listenId, array $vorlage): array
    {
        try {
            $antwort = Http::withToken($token)
                ->timeout($timeout)
                ->acceptJson()
                ->post(
                    rtrim($basisUrl, '/').'/api/households/shopping/items',
                    self::nutzlast($listenId, $vorlage),
                );
        } catch (ConnectionException) {
            return ['ok' => false];
        }

        if ($antwort->failed()) {
            return ['ok' => false];
        }

        $daten = $antwort->json() ?? [];
        $angelegt = $daten['createdItems'][0] ?? $daten;

        return ['ok' => true, 'artikel' => is_array($angelegt) ? $angelegt : []];
    }

    /**
     * @param  array<string, mixed>  $vorlage
     * @return array<string, mixed>
     */
    private static function nutzlast(string $listenId, array $vorlage): array
    {
        $lebensmittelId = $vorlage['foodId'] ?? $vorlage['food']['id'] ?? null;
        $labelId = $vorlage['labelId'] ?? $vorlage['label']['id'] ?? null;
        $einheitId = $vorlage['unitId'] ?? $vorlage['unit']['id'] ?? null;

        $nutzlast = [
            'shoppingListId' => $listenId,
            'quantity' => (float) ($vorlage['quantity'] ?? 0),
            'checked' => false,
        ];

        // Die Warengruppe steht am Artikel selbst, nicht nur am Lebensmittel:
        // sonst landete Toilettenpapier unter „Sonstiges“.
        if (is_string($labelId) && $labelId !== '') {
            $nutzlast['labelId'] = $labelId;
        }

        if (is_string($einheitId) && $einheitId !== '') {
            $nutzlast['unitId'] = $einheitId;
        }

        if (is_string($lebensmittelId) && $lebensmittelId !== '') {
            $nutzlast['foodId'] = $lebensmittelId;
            $nutzlast['isFood'] = true;
            $nutzlast['note'] = (string) ($vorlage['note'] ?? '');
        } else {
            $nutzlast['isFood'] = false;
            $nutzlast['note'] = trim((string) ($vorlage['note'] ?? $vorlage['display'] ?? ''));
        }

        // Von den Extras der Vorlage geht nur die Ladenausnahme mit; der
        // Rest ist Buchhaltung der Vorratsliste und hat im Einkauf nichts
        // verloren.
        $extras = is_array($vorlage['extras'] ?? null) ? $vorlage['extras'] : [];

        if (trim((string) ($extras['laeden'] ?? '')) !== '') {
            $nutzlast['extras'] = ['laeden' => $extras['laeden']];
        }

        return $nutzlast;
    }
}
