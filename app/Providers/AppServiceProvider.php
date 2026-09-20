<?php

namespace App\Providers;

use App\Mealie\Sitzung;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceHttps();
    }
}
