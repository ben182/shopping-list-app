<?php

namespace App\Katalog;

use App\Mealie\Gruppenzuordnung;

/**
 * In welchen Läden es einen Mealie-Artikel gibt.
 *
 * Mealie kennt keine Läden. Ein Artikel erbt sie deshalb von der Warengruppe,
 * unter der er auf dem Screen landet — es sei denn, an ihm selbst steht
 * etwas anderes (`extras.laeden`). Genau die Vererbung, die der Katalog
 * vorher zwischen Gruppe und Artikel hatte, nur eine Ebene höher.
 *
 * Ohne jede Zuordnung steht ein Artikel in jedem Filter: ein übersehener
 * Artikel wiegt schwerer als eine Zeile zu viel — und ein frisch aus einem
 * Rezept entstandenes Lebensmittel hat nie eine.
 */
final class Ladenzuordnung
{
    public function __construct(
        private readonly Katalog $katalog,
        private readonly Gruppenzuordnung $zuordnung,
    ) {}

    /**
     * @param  list<Laden>  $eigene  Was am Artikel selbst steht
     */
    public function gibtEsIn(?Laden $laden, ?string $label, array $eigene = []): bool
    {
        if ($laden === null) {
            return true;
        }

        $laeden = $eigene !== [] ? $eigene : $this->geerbt($label);

        return $laeden === [] || in_array($laden, $laeden, strict: true);
    }

    /**
     * Die Läden der Warengruppe, unter der das Label landet — leer, wenn es
     * dafür keine Katalog-Gruppe gibt.
     *
     * @return list<Laden>
     */
    private function geerbt(?string $label): array
    {
        return $this->katalog->gruppeMitNamen($this->zuordnung->fuerLabel($label))?->laeden ?? [];
    }
}
