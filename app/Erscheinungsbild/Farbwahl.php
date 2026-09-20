<?php

namespace App\Erscheinungsbild;

use App\Models\Einstellung;
use App\NativeComponents\Screen;
use Native\Mobile\UI\Theme;

/**
 * Welche Akzentfarbe der Nutzer gewählt hat — neben dem Modus die zweite
 * Zeile in der Einstellungstabelle, aus denselben Gründen dort und nicht im
 * Secure Storage.
 *
 * Wirksam wird die Wahl über die Theme-Tokens: alles, was `text-theme-primary`
 * oder `bg-theme-primary` trägt, liest sie beim Rendern frisch. Was die App
 * nicht selbst zeichnet — Android-Systemdialoge, der Date-Picker — bleibt
 * davon unberührt und damit beim Indigo aus `config/nativephp.php`.
 */
final class Farbwahl
{
    private const SCHLUESSEL = 'akzentfarbe';

    /** Die gewählte Farbe — vor der ersten Wahl Indigo. */
    public function aktuell(): Akzentfarbe
    {
        return Akzentfarbe::ausWert(Einstellung::query()->find(self::SCHLUESSEL)?->wert);
    }

    public function waehlen(Akzentfarbe $farbe): void
    {
        Einstellung::query()->updateOrCreate(
            ['schluessel' => self::SCHLUESSEL],
            ['wert' => $farbe->value],
        );

        $this->anwenden($farbe);
    }

    /**
     * Legt die Farbe über die Tokens aus `config/native-ui.php`.
     *
     * Der Merge ist statischer Zustand im Paket und überlebt den Prozess
     * nicht: beim App-Start lädt der Service Provider wieder die
     * Konfiguration. Deshalb wird er bei jedem entstehenden Screen
     * wiederholt — siehe {@see Screen}.
     */
    public function anwenden(?Akzentfarbe $farbe = null): void
    {
        Theme::merge(($farbe ?? $this->aktuell())->tokens());
    }
}
