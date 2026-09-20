<?php

namespace App\Erscheinungsbild;

use Ben182\Appearance\AppearanceStyle;

/**
 * Wonach sich die App beim Hell/Dunkel-Modus richtet. Die Reihenfolge der
 * Fälle ist die Reihenfolge im Segmented Control — der Index einer Option
 * ist ihre Position hier.
 */
enum Modus: string
{
    case System = 'system';
    case Hell = 'hell';
    case Dunkel = 'dunkel';

    /** Was auf der Option steht. */
    public function beschriftung(): string
    {
        return match ($this) {
            self::System => 'System',
            self::Hell => 'Hell',
            self::Dunkel => 'Dunkel',
        };
    }

    /**
     * Die Beschriftungen aller Optionen in Anzeigereihenfolge.
     *
     * @return list<string>
     */
    public static function beschriftungen(): array
    {
        return array_map(fn (self $modus) => $modus->beschriftung(), self::cases());
    }

    /**
     * Der Stil, den das Plugin ans Gerät schiebt.
     */
    public function stil(): AppearanceStyle
    {
        return match ($this) {
            self::System => AppearanceStyle::System,
            self::Hell => AppearanceStyle::Light,
            self::Dunkel => AppearanceStyle::Dark,
        };
    }

    /**
     * Die Position dieser Option im Segmented Control.
     */
    public function position(): int
    {
        $position = array_search($this, self::cases(), strict: true);

        return is_int($position) ? $position : 0;
    }

    /**
     * Die Option an dieser Position. Ein Index, den es nicht gibt, fällt auf
     * „System“ zurück — der Screen soll an einem krummen Event nicht
     * zerbrechen.
     */
    public static function anPosition(int $position): self
    {
        return self::cases()[$position] ?? self::System;
    }

    /**
     * Der Modus zu einem gespeicherten Wert. Was die Tabelle nicht (mehr)
     * hergibt, ist „System“ — so sieht auch die erste Nutzung aus.
     */
    public static function ausWert(?string $wert): self
    {
        return $wert === null ? self::System : (self::tryFrom($wert) ?? self::System);
    }
}
