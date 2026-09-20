<?php

namespace App\Einkaufen;

final readonly class Abschnitt
{
    /**
     * @param  list<Zeile>  $zeilen
     */
    public function __construct(
        public string $name,
        public array $zeilen,
    ) {}
}
