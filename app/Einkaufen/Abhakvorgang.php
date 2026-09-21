<?php

namespace App\Einkaufen;

/**
 * Ein „Alles abhaken“, das sich noch zurücknehmen lässt: welche Artikel
 * dabei den Haken bekommen haben.
 *
 * Von den Artikeln liegt hier Mealies eigene Darstellung — dieselbe, die
 * beim Zurückholen wieder zum Server geht. Sie ist absichtlich nicht
 * unveränderlich: nimmt Mealie das Abhaken nicht an, bleibt nichts übrig,
 * was sich zurücknehmen ließe, und die Leiste verschwindet.
 */
final class Abhakvorgang
{
    /**
     * @param  list<array<string, mixed>>  $artikel  Mealies Darstellung der Artikel
     */
    public function __construct(private array $artikel = []) {}

    public function anzahl(): int
    {
        return count($this->artikel);
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
     * @return list<array<string, mixed>>
     */
    public function artikel(): array
    {
        return $this->artikel;
    }

    /**
     * Mealie hat das Abhaken abgelehnt: die Artikel stehen wieder offen auf
     * der Liste und gehören damit nicht mehr zu dem, was sich zurücknehmen
     * lässt.
     */
    public function vergessen(): void
    {
        $this->artikel = [];
    }
}
