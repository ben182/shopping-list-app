<?php

namespace App\Einkaufen;

/**
 * Eine Zeile der Einkaufs-Übersicht — entweder ein eigener Katalog-Artikel
 * oder ein Artikel aus Mealie. Der Screen unterscheidet sie nur noch am
 * Trailing-Icon und daran, was ein Tap auslöst.
 */
final readonly class Zeile
{
    public function __construct(
        public string $id,
        public string $text,
        public ?string $zusatz,
        public bool $ausMealie,
    ) {}
}
