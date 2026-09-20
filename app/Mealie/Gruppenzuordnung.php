<?php

namespace App\Mealie;

use App\Katalog\Gruppe;
use App\Katalog\Katalog;

/**
 * Unter welche Überschrift ein Mealie-Label gehört.
 *
 * Mealies Labels und die Warengruppen des Katalogs sind zwei getrennt
 * gepflegte Listen. Wo sie sich decken, wird zusammengelegt; wo nicht, bekommt
 * das Label seine eigene Gruppe — lieber eine Überschrift zu viel als ein
 * Artikel, der im Laden am falschen Regal steht.
 */
final class Gruppenzuordnung
{
    public function __construct(private readonly Katalog $katalog) {}

    public function fuerLabel(?string $label): string
    {
        $label = trim((string) $label);

        if ($label === '') {
            return $this->gruppeOhneLabel();
        }

        if (in_array($label, $this->katalogGruppennamen(), strict: true)) {
            return $label;
        }

        $aliase = config('mealie.label_aliase', []);

        return $aliase[$label] ?? $label;
    }

    public function gruppeOhneLabel(): string
    {
        return (string) config('mealie.gruppe_ohne_label');
    }

    /**
     * @return list<string>
     */
    private function katalogGruppennamen(): array
    {
        return array_map(fn (Gruppe $gruppe) => $gruppe->name, $this->katalog->gruppen());
    }
}
