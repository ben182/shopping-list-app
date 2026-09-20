<?php

namespace App\NativeComponents;

use App\Erscheinungsbild\Auswahl;
use Ben182\AppLifecycle\Events\AppForegrounded;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;

/**
 * Gemeinsamer Unterbau aller Screens: er sorgt dafür, dass die App im
 * gewählten Erscheinungsbild dasteht.
 *
 * Die Ansage ans Gerät überlebt den Prozess nicht zuverlässig — deshalb
 * wird sie wiederholt, sobald ein Screen entsteht (also beim App-Start und
 * bei jeder Navigation) und sobald die App aus dem Hintergrund zurückkommt.
 * Ein Anwendungs-Hook beim Boot des Service Providers wäre die naheliegende
 * Stelle, greift aber zu früh: die Tabelle mit der Vorliebe gibt es zu dem
 * Zeitpunkt im Test noch nicht.
 */
abstract class Screen extends NativeComponent
{
    public function __construct()
    {
        app(Auswahl::class)->anwenden();
    }

    /**
     * Die App kommt aus dem Hintergrund zurück — das Ereignis schickt das
     * Plugin `ben182/app-lifecycle`. Absichtlich `final`: überschreibt ein
     * Screen die Methode, fiele mit ihr das Erscheinungsbild weg. Was ein
     * Screen dabei nachladen will, gehört in {@see wiederImVordergrund()}.
     */
    #[On(AppForegrounded::class)]
    final public function appImVordergrund(): void
    {
        app(Auswahl::class)->anwenden();

        $this->wiederImVordergrund();
    }

    /** Hook für Screens, die beim Zurückkommen etwas nachladen wollen. */
    protected function wiederImVordergrund(): void
    {
        //
    }
}
