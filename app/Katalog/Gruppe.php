<?php

namespace App\Katalog;

final readonly class Gruppe
{
    /**
     * @param  list<Artikel>  $artikel
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $artikel,
    ) {}
}
