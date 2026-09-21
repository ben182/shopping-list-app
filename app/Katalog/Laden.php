<?php

namespace App\Katalog;

/**
 * Die Läden, auf die sich der Einkauf verteilt.
 *
 * Der Wert ist der Schlüssel, unter dem `config/katalog.php` einen Laden
 * nennt; die Bezeichnung steht auf dem Filter-Chip im Einkaufen-Screen. Ein
 * Artikel kann in mehreren Läden zu haben sein — der Filter fragt „bekomme
 * ich das hier?“, nicht „wo kaufe ich das sonst?“.
 */
enum Laden: string
{
    case Lidl = 'lidl';
    case Rewe = 'rewe';
    case Getraenkemarkt = 'getraenkemarkt';
    case Dm = 'dm';
    case Rossmann = 'rossmann';
    case Budni = 'budni';

    /**
     * Alle Läden in Anzeigereihenfolge — die der Fälle.
     *
     * @return list<self>
     */
    public static function alle(): array
    {
        return self::cases();
    }

    /**
     * Die Läden zu den Schlüsseln aus dem Katalog. Unbekannte Schlüssel
     * fallen still weg: ein Tippfehler in der Konfiguration soll den Screen
     * nicht sprengen.
     *
     * @param  list<string>  $schluessel
     * @return list<self>
     */
    public static function ausSchluesseln(array $schluessel): array
    {
        return array_values(array_filter(array_map(self::tryFrom(...), $schluessel)));
    }

    public function bezeichnung(): string
    {
        return match ($this) {
            self::Lidl => 'Lidl',
            self::Rewe => 'Rewe',
            self::Getraenkemarkt => 'Getränkemarkt',
            self::Dm => 'dm',
            self::Rossmann => 'Rossmann',
            self::Budni => 'Budni',
        };
    }
}
