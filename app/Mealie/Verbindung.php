<?php

namespace App\Mealie;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Der Verbindungstest der Einstellungen: ein Griff an Mealie, dessen Ergebnis
 * in einem Satz Platz hat.
 *
 * Bewusst statisch und ohne Abhängigkeiten im Konstruktor — der Aufruf läuft
 * über `AsyncTask` in einem eigenen Interpreter, und was dorthin geht, muss
 * sich serialisieren lassen.
 */
final class Verbindung
{
    /**
     * Fragt Mealie nach Nutzer und Version und fasst die Antwort in einen
     * Satz. Alles, was kein 401 ist, gilt als „nicht erreichbar“ — die
     * Unterscheidung, die der Nutzer braucht, ist „falsches Token“ gegen
     * „falsches Netz“, nicht der HTTP-Code.
     *
     * @return array{text: string}
     */
    public static function pruefen(string $basisUrl, string $token, int $timeout): array
    {
        try {
            $nutzer = self::anfrage($basisUrl, $token, $timeout, '/api/users/self');

            if ($nutzer->unauthorized()) {
                return ['text' => 'Token ungültig'];
            }

            $ueber = self::anfrage($basisUrl, $token, $timeout, '/api/app/about');

            if ($ueber->unauthorized()) {
                return ['text' => 'Token ungültig'];
            }

            if ($nutzer->failed() || $ueber->failed()) {
                return ['text' => 'Mealie nicht erreichbar'];
            }

            return ['text' => sprintf(
                'Verbunden: %s, Mealie %s',
                $nutzer->json('username') ?? 'unbekannt',
                $ueber->json('version') ?? 'unbekannt',
            )];
        } catch (ConnectionException) {
            return ['text' => 'Mealie nicht erreichbar'];
        }
    }

    private static function anfrage(string $basisUrl, string $token, int $timeout, string $pfad): Response
    {
        return Http::withToken($token)
            ->timeout($timeout)
            ->acceptJson()
            ->get(rtrim($basisUrl, '/').$pfad);
    }
}
