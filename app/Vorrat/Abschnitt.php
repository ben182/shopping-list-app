<?php

namespace App\Vorrat;

final readonly class Abschnitt
{
    /**
     * @param  list<Artikel>  $artikel
     */
    public function __construct(
        public string $name,
        public array $artikel,
    ) {}
}
