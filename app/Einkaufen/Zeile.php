<?php

namespace App\Einkaufen;

/**
 * Eine Zeile der Einkaufs-Übersicht.
 *
 * Seit der Vorrat in Mealie liegt, kommen alle Zeilen von dort. Das
 * Besteck-Icon unterscheidet deshalb nicht mehr die Herkunft der Liste,
 * sondern die des Artikels: `$ausRezept` heißt, ein Rezept aus dem
 * Wochenplan hat ihn mitgebracht — im Gegensatz zu dem, was von Hand oder
 * aus dem Vorrat dazukam.
 */
final readonly class Zeile
{
    public function __construct(
        public string $id,
        public string $text,
        public ?string $notiz,
        public bool $ausRezept = false,
    ) {}
}
