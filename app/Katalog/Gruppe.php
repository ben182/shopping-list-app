<?php

namespace App\Katalog;

final readonly class Gruppe
{
    /**
     * @param  list<Artikel>  $artikel
     * @param  list<Laden>  $laeden  Die Läden, die ein Artikel dieser Gruppe
     *                               erbt, solange er nichts eigenes sagt.
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $artikel,
        public array $laeden = [],
    ) {}
}
