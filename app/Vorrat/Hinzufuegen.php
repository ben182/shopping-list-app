<?php

namespace App\Vorrat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Kopiert einen Vorratsartikel auf die Einkaufsliste.
 *
 * Kopiert, nicht verschoben: der Vorratseintrag bleibt stehen, er ist der
 * Katalog. Mitgenommen wird, was den Artikel im Laden ausmacht — das
 * Lebensmittel oder die Notiz, seine Warengruppe und die Läden, falls an ihm
 * eine Ausnahme steht.
 *
 * Wie App\Mealie\Einkaufsliste statisch und abhängigkeitsfrei: der Aufruf
 * läuft über `AsyncTask` in einem eigenen Interpreter.
 */
final class Hinzufuegen
{
    /**
     * @param  array<string, mixed>  $vorlage  Mealies Darstellung des Vorratsartikels
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

        // Von den Extras des Vorrats geht nur die Ladenausnahme mit; der
        // Rest ist Buchhaltung der Vorratsliste und hat im Einkauf nichts
        // verloren.
        $extras = is_array($vorlage['extras'] ?? null) ? $vorlage['extras'] : [];

        if (trim((string) ($extras['laeden'] ?? '')) !== '') {
            $nutzlast['extras'] = ['laeden' => $extras['laeden']];
        }

        return $nutzlast;
    }
}
