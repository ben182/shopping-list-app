<?php

namespace App\Providers;

use App\Einkaufen\Rueckgaengig;
use App\Katalog\Ladenfilter;
use App\Mealie\Sitzung;
use App\Vorrat\Sitzung as Vorratssitzung;
use App\Wochenplan\Sitzung as Wochenplansitzung;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Die geladene Mealie-Liste gehört der Sitzung, nicht dem Screen:
        // jeder Tab-Wechsel mountet den Einkaufen-Screen neu, und was einmal
        // geladen wurde, soll dabei stehen bleiben.
        $this->app->singleton(Sitzung::class);

        // Und für den Vorrat: er kommt aus Mealie wie die Einkaufsliste und
        // soll den Tab-Wechsel genauso überstehen.
        $this->app->singleton(Vorratssitzung::class);

        // Dasselbe für den Wochenplan: eine geladene Woche soll beim
        // Tab-Wechsel nicht wieder hinter einem Spinner verschwinden.
        $this->app->singleton(Wochenplansitzung::class);

        // Und für die Rücknahme von „Alles abhaken“: sie muss dem Screen
        // gehören, nicht einem Aufruf — das Ergebnis des Bulk-Updates kommt
        // erst nach dem Rendern zurück und greift dann noch auf sie zu.
        $this->app->singleton(Rueckgaengig::class);

        // Und für den Ladenfilter, den Einkaufen und Vorrat sich teilen: wer
        // im Laden steht, soll seine Wahl nicht auf jedem Screen neu treffen.
        $this->app->singleton(Ladenfilter::class);
    }
}
