<?php

namespace App\Einkaufen;

use App\Katalog\Artikel;
use App\Katalog\Gruppe;
use App\Katalog\Katalog;
use App\Liste\EigeneListe;
use App\Mealie\Gruppenzuordnung;
use App\Mealie\Sitzung;

/**
 * Die eine Liste, durch die man im Laden läuft: eigene Artikel und offene
 * Mealie-Artikel in denselben Warengruppen.
 */
final class Uebersicht
{
    public function __construct(
        private readonly Katalog $katalog,
        private readonly EigeneListe $liste,
        private readonly Sitzung $mealie,
        private readonly Gruppenzuordnung $zuordnung,
    ) {}

    /**
     * Erst die Katalog-Gruppen in Katalogreihenfolge, danach die Gruppen, die
     * nur aus Mealie-Labels entstanden sind, alphabetisch. Leere Gruppen
     * kommen gar nicht erst vor.
     *
     * @return list<Abschnitt>
     */
    public function abschnitte(): array
    {
        $zeilen = [];

        foreach ($this->katalog->gruppiert($this->liste->artikelIds()) as $gruppe) {
            $zeilen[$gruppe->name] = array_map(
                fn (Artikel $artikel) => new Zeile($artikel->id, $artikel->name, null, ausMealie: false),
                $gruppe->artikel,
            );
        }

        foreach ($this->mealie->offene() as $eintrag) {
            $zeilen[$this->zuordnung->fuerLabel($eintrag->label)][] = new Zeile(
                $eintrag->id,
                $eintrag->text,
                $eintrag->rezepte,
                ausMealie: true,
            );
        }

        return array_values(array_map(
            fn (string $name) => new Abschnitt($name, array_values($zeilen[$name])),
            $this->reihenfolge(array_keys($zeilen)),
        ));
    }

    /**
     * Wie viele Artikel auf dem Screen offen sind: eigene plus nicht
     * abgehakte Mealie-Artikel.
     */
    public function anzahl(): int
    {
        return $this->liste->anzahl() + count($this->mealie->offene());
    }

    /**
     * @param  list<string>  $namen
     * @return list<string>
     */
    private function reihenfolge(array $namen): array
    {
        $katalogNamen = array_map(fn (Gruppe $gruppe) => $gruppe->name, $this->katalog->gruppen());

        $ausKatalog = array_values(array_filter($katalogNamen, fn (string $name) => in_array($name, $namen, strict: true)));
        $zusaetzlich = array_values(array_diff($namen, $katalogNamen));

        usort($zusaetzlich, fn (string $a, string $b) => strcasecmp(self::sortierbar($a), self::sortierbar($b)));

        return [...$ausKatalog, ...$zusaetzlich];
    }

    /**
     * Umlaute sortieren wie ihre Grundbuchstaben — „Öl“ gehört zwischen
     * „Obst“ und „Pasta“, nicht hinter „Zucker“, wo ein reiner Byte-Vergleich
     * es ablegen würde. Ein `Collator` wäre genauer, steht aber in der
     * PHP-Runtime des Geräts nicht sicher zur Verfügung.
     */
    private static function sortierbar(string $name): string
    {
        return str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['a', 'o', 'u', 'A', 'O', 'U', 'ss'],
            $name,
        );
    }
}
