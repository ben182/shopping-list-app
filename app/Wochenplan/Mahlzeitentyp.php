<?php

namespace App\Wochenplan;

/**
 * Mealies `entryType` in der Reihenfolge, in der ein Tag gelesen wird —
 * die Reihenfolge der Fälle *ist* die Sortierung innerhalb eines Tages.
 */
enum Mahlzeitentyp: string
{
    case Fruehstueck = 'breakfast';
    case Mittag = 'lunch';
    case Abend = 'dinner';
    case Beilage = 'side';
    case Snack = 'snack';
    case Getraenk = 'drink';
    case Dessert = 'dessert';

    public function label(): string
    {
        return match ($this) {
            self::Fruehstueck => 'Frühstück',
            self::Mittag => 'Mittag',
            self::Abend => 'Abend',
            self::Beilage => 'Beilage',
            self::Snack => 'Snack',
            self::Getraenk => 'Getränk',
            self::Dessert => 'Dessert',
        };
    }

    public function reihenfolge(): int
    {
        return array_search($this, self::cases(), true);
    }
}
