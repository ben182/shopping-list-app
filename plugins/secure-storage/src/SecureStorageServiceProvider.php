<?php

namespace Ben182\SecureStorage;

use Illuminate\Support\ServiceProvider;
use Native\Mobile\SecureStorage;

/**
 * The PHP half of secure storage already ships in nativephp/mobile — the
 * facade, the result object and the status enum are all in the free core.
 * This plugin supplies the missing native half and binds the core class so
 * `SecureStorage::set()` resolves.
 */
class SecureStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SecureStorage::class, fn () => new SecureStorage);
    }
}
