<?php

namespace Ben182\Appearance;

/**
 * Legt das Erscheinungsbild der ganzen App fest — Screens, Tab-Leiste,
 * Systemleisten und native Dialoge —, unabhängig davon, was das Gerät
 * eingestellt hat.
 *
 * Die Tokens aus `Native\Mobile\UI\Theme` reichen dafür nicht: sie färben nur,
 * was die App selbst zeichnet. Welche Hälfte der Tokens ein Renderer überhaupt
 * nimmt, entscheidet die Systemeinstellung — und die ändert erst dieses
 * Plugin.
 */
class Appearance
{
    public const BRIDGE_METHOD = 'Appearance.Set';

    /**
     * Gibt es die native Hälfte auf dieser Plattform? Außerhalb einer
     * NativePHP-App (Test, Artisan, Browser) ist die Antwort nein.
     */
    public function available(): bool
    {
        return function_exists('nativephp_call')
            && function_exists('nativephp_can')
            && nativephp_can(self::BRIDGE_METHOD);
    }

    /**
     * Stellt die App auf diesen Stil um. `false` heißt „nicht angekommen“ —
     * für den Aufrufer ein Grund, es später noch einmal zu versuchen, aber
     * keiner, die Wahl des Nutzers zu verwerfen.
     */
    public function set(AppearanceStyle $style): bool
    {
        if (! $this->available()) {
            return false;
        }

        $antwort = nativephp_call(self::BRIDGE_METHOD, json_encode([
            'mode' => $style->value,
        ]));

        if ($antwort === null) {
            return false;
        }

        $entschluesselt = json_decode($antwort, true);

        return is_array($entschluesselt) && ($entschluesselt['success'] ?? false) === true;
    }
}
