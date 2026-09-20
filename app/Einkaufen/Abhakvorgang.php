<?php

namespace App\Einkaufen;

/**
 * Ein „Alles abhaken“, das sich noch zurücknehmen lässt: welche eigenen
 * Artikel dabei von der Liste geräumt wurden und welche Mealie-Artikel den
 * Haken bekommen haben.
 *
 * Von den Mealie-Artikeln liegt hier Mealies eigene Darstellung — dieselbe,
 * die beim Zurückholen wieder zum Server geht. Sie ist absichtlich nicht
 * unveränderlich: nimmt Mealie das Abhaken nicht an, fällt die Mealie-Hälfte
 * weg und die Leiste zählt nur noch die eigenen Artikel.
 */
final class Abhakvorgang
{
    /**
     * @param  list<string>  $eigeneIds
     * @param  list<array<string, mixed>>  $mealieArtikel  Mealies Darstellung der Artikel
     */
    public function __construct(
        private readonly array $eigeneIds,
        private array $mealieArtikel = [],
    ) {}

    public function anzahl(): int
    {
        return count($this->eigeneIds) + count($this->mealieArtikel);
    }

    /** Nichts mehr zurückzunehmen — dann gehört auch keine Leiste mehr hin. */
    public function leer(): bool
    {
        return $this->anzahl() === 0;
    }

    /** Was links in der Leiste steht. */
    public function text(): string
    {
        return $this->anzahl() === 1
            ? '1 Artikel abgehakt'
            : $this->anzahl().' Artikel abgehakt';
    }

    /**
     * @return list<string>
     */
    public function eigeneIds(): array
    {
        return $this->eigeneIds;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mealieArtikel(): array
    {
        return $this->mealieArtikel;
    }

    /**
     * Mealie hat das Abhaken abgelehnt: die Artikel stehen wieder offen auf
     * der Liste und gehören damit nicht mehr zu dem, was sich zurücknehmen
     * lässt.
     */
    public function mealieVergessen(): void
    {
        $this->mealieArtikel = [];
    }
}
