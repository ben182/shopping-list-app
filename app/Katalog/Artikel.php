<?php

namespace App\Katalog;

final readonly class Artikel
{
    /**
     * @param  list<Laden>  $laeden  Wo es den Artikel gibt — geerbt von der
     *                               Gruppe, sofern der Artikel nichts eigenes sagt.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $gruppeId,
        public array $laeden = [],
    ) {}

    /**
     * Gibt es den Artikel in diesem Laden? Ohne Laden — der Filter steht auf
     * „Alle“ — immer; ebenso, wenn für den Artikel gar kein Laden hinterlegt
     * ist: ein übersehener Artikel wiegt schwerer als eine Zeile zu viel.
     */
    public function gibtEsIn(?Laden $laden): bool
    {
        return $laden === null
            || $this->laeden === []
            || in_array($laden, $this->laeden, strict: true);
    }
}
