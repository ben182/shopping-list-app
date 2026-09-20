<?php

namespace Ben182\Appearance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool available()
 * @method static bool set(\Ben182\Appearance\AppearanceStyle $style)
 */
class Appearance extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ben182\Appearance\Appearance::class;
    }
}
