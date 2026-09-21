<?php

namespace App\Einkaufen;

use App\Katalog\Gruppe;
use App\Katalog\Katalog;
use App\Mealie\Gruppenzuordnung;

/**
 * Welche Warengruppen beim Anlegen eines Artikels zur Wahl stehen.
 *
 * Die Gruppen sind die des Katalogs — dieselbe Gliederung wie auf der Liste,
 * in derselben Reihenfolge. Was sie erst wählbar macht, ist ein Mealie-Label,
 * das unter ihnen landet: die App kann einem Artikel nur ein Label mitgeben,
 * keine Gruppe. Eine Gruppe ohne passendes Label fällt deshalb weg, statt
 * eine Wahl anzubieten, die Mealie nicht abbilden kann.
 *
 * Passend heißt: das Label heißt wie die Gruppe, oder es landet über
 * `mealie.label_aliase` unter ihr — dieselbe Zuordnung, die beim Zeichnen
 * der Liste greift, nur andersherum gelesen.
 */
final class Gruppenwahl
{
    public function __construct(
        private readonly Katalog $katalog,
        private readonly Gruppenzuordnung $zuordnung,
    ) {}

    /**
     * @param  list<array{id: string, name: string}>  $labels  Was Mealie an Labels kennt
     * @return list<Gruppenoption>
     */
    public function optionen(array $labels): array
    {
        $optionen = [];

        foreach ($this->katalog->gruppen() as $gruppe) {
            $labelId = $this->labelFuer($gruppe, $labels);

            if ($labelId !== null) {
                $optionen[] = new Gruppenoption($gruppe->name, $labelId);
            }
        }

        return $optionen;
    }

    /**
     * Das Label dieser Gruppe — der Namensgleiche zuerst, sonst der erste,
     * den ein Alias hierher zieht.
     *
     * @param  list<array{id: string, name: string}>  $labels
     */
    private function labelFuer(Gruppe $gruppe, array $labels): ?string
    {
        $ueberAlias = null;

        foreach ($labels as $label) {
            if ($label['name'] === $gruppe->name) {
                return $label['id'];
            }

            if ($ueberAlias === null && $this->zuordnung->fuerLabel($label['name']) === $gruppe->name) {
                $ueberAlias = $label['id'];
            }
        }

        return $ueberAlias;
    }
}
