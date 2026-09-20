<?php

namespace App\Erscheinungsbild;

use App\Models\Einstellung;

/**
 * Welches Erscheinungsbild der Nutzer gewählt hat — auf der Platte, damit
 * die Wahl den App-Neustart übersteht.
 *
 * Gelesen wird bei jedem Zugriff frisch aus SQLite: die Einstellung ändert
 * sich selten, und ein zwischengespeicherter Wert wäre nur eine weitere
 * Stelle, an der ein veralteter Stand hängen bleiben könnte.
 */
final class Auswahl
{
    private const SCHLUESSEL = 'erscheinungsbild';

    /** Die gewählte Option — vor der ersten Wahl „System“. */
    public function aktuell(): Modus
    {
        return Modus::ausWert(Einstellung::query()->find(self::SCHLUESSEL)?->wert);
    }

    public function waehlen(Modus $modus): void
    {
        Einstellung::query()->updateOrCreate(
            ['schluessel' => self::SCHLUESSEL],
            ['wert' => $modus->value],
        );
    }
}
