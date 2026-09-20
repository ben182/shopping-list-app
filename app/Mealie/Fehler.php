<?php

namespace App\Mealie;

/**
 * Warum das Laden von Mealie gescheitert ist — in den drei Stufen, die der
 * Nutzer auseinanderhalten muss: „das Token stimmt nicht“ gegen „Mealie ist
 * gerade nicht da“. Netz- und HTTP-Fehler lesen sich für ihn gleich, bleiben
 * aber getrennt, weil der Unterschied in Fehlerberichten zählt.
 */
enum Fehler: string
{
    case Netz = 'netz';
    case Http = 'http';
    case Token = 'token';

    /** Alles Unbekannte gilt als Netzfehler — das ist die harmlosere Annahme. */
    public static function ausSchluessel(string $schluessel): self
    {
        return self::tryFrom($schluessel) ?? self::Netz;
    }
}
