<?php

namespace App\Einkaufen;

/**
 * Eine Warengruppe, die ein neuer Artikel bekommen kann: die Überschrift,
 * unter der er auf dem Screen landen wird, und das Mealie-Label, das ihn
 * dorthin bringt.
 */
final readonly class Gruppenoption
{
    public function __construct(
        public string $name,
        public string $labelId,
    ) {}
}
