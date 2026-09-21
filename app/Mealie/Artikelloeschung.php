<?php

namespace App\Mealie;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Wirft Artikel endgültig aus der Mealie-Einkaufsliste.
 *
 * Wie App\Mealie\Artikelstatus statisch und abhängigkeitsfrei: der Aufruf
 * läuft über `AsyncTask` in einem eigenen Interpreter.
 */
final class Artikelloeschung
{
    /**
     * Mealie löscht alle in einem Zug — ein Aufruf statt einem je Artikel.
     *
     * Die IDs stehen dabei als wiederholter Query-Parameter in der URL und
     * nicht im Rumpf: Laravel schickte ein Array als JSON-Body, und Mealie
     * liest die Liste ausdrücklich aus der Query (`ids=…&ids=…`). Ein
     * `http_build_query()` täte es auch nicht — es nummeriert die Schlüssel
     * durch, und `ids[0]=` kennt die API nicht.
     *
     * @param  list<string>  $ids
     * @return array{ok: bool}
     */
    public static function alleLoeschen(string $basisUrl, string $token, int $timeout, array $ids): array
    {
        if ($ids === []) {
            return ['ok' => true];
        }

        $query = implode('&', array_map(fn (string $id) => 'ids='.urlencode($id), $ids));

        try {
            $antwort = Http::withToken($token)
                ->timeout($timeout)
                ->acceptJson()
                ->delete(rtrim($basisUrl, '/').'/api/households/shopping/items?'.$query);
        } catch (ConnectionException) {
            return ['ok' => false];
        }

        return ['ok' => $antwort->successful()];
    }
}
