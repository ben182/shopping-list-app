<?php

namespace App\Erscheinungsbild;

use App\Models\Einstellung;
use Ben182\Appearance\Facades\Appearance;

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

        $this->anwenden($modus);
    }

    /**
     * Schiebt die Wahl ans Gerät — ohne das bliebe sie eine Zeile in SQLite.
     *
     * Ohne Argument gilt, was gespeichert ist; so wird der Aufruf beim
     * App-Start und bei jeder Rückkehr in den Vordergrund zur Wiederholung
     * derselben Ansage. Schlägt sie fehl, weil es die native Hälfte auf
     * dieser Plattform nicht gibt, ist das kein Fehler: die Wahl steht
     * trotzdem in der Tabelle und greift beim nächsten Versuch.
     */
    public function anwenden(?Modus $modus = null): bool
    {
        return Appearance::set(($modus ?? $this->aktuell())->stil());
    }
}
