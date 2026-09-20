<?php

namespace Ben182\Appearance;

/**
 * Wonach sich die Oberfläche richten soll. Die Werte sind zugleich das, was
 * über die Bridge geht — Android und iOS lesen genau diese drei Zeichenketten.
 */
enum AppearanceStyle: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';
}
