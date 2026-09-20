<?php

namespace App\Providers;

use App\Einkaufen\Rueckgaengig;
use App\Mealie\Sitzung;
use App\Wochenplan\Sitzung as Wochenplansitzung;
use Illuminate\Support\Facades\URL;
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

        // Dasselbe für den Wochenplan: eine geladene Woche soll beim
        // Tab-Wechsel nicht wieder hinter einem Spinner verschwinden.
        $this->app->singleton(Wochenplansitzung::class);

        // Und für die Rücknahme von „Alles abhaken“: sie muss dem Screen
        // gehören, nicht einem Aufruf — das Ergebnis des Bulk-Updates kommt
        // erst nach dem Rendern zurück und greift dann noch auf sie zu.
        $this->app->singleton(Rueckgaengig::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceHttps();
    }
}
