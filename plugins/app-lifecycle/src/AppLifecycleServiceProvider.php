<?php

namespace Ben182\AppLifecycle;

use Illuminate\Support\ServiceProvider;

/**
 * Dieses Plugin hat keine Bridge-Funktion und keinen PHP-Dienst: seine ganze
 * Arbeit macht die Init-Funktion auf der Geräteseite, die sich beim
 * Lebenszyklus der App anmeldet und daraus ein Native-Event macht.
 */
class AppLifecycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
}
