<?php

namespace App\Katalog;

final readonly class Artikel
{
    public function __construct(
        public string $id,
        public string $name,
        public string $gruppeId,
    ) {}
}
