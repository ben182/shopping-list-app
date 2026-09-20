<?php

namespace Ben182\Appearance;

use Illuminate\Support\ServiceProvider;

class AppearanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Appearance::class, fn () => new Appearance);
    }
}
