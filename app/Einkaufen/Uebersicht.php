<?php

namespace App\Einkaufen;

use App\Katalog\Artikel;
use App\Katalog\Gruppe;
use App\Katalog\Katalog;
use App\Katalog\Laden;
use App\Katalog\Ladenfilter;
use App\Liste\EigeneListe;
use App\Mealie\Eintrag;
use App\Mealie\Gruppenzuordnung;
use App\Mealie\Sitzung;

/**
 * Die eine Liste, durch die man im Laden läuft: eigene Artikel und offene
 * Mealie-Artikel in denselben Warengruppen.
 *
 * Steht der Ladenfilter nicht auf „Alle“, bleibt davon übrig, was es im
 * gewählten Laden gibt — die Warengruppen bleiben die Gliederung, der Laden
 * entscheidet nur, was drinsteht.
 */
final class Uebersicht
{
    public function __construct(
        private readonly Katalog $katalog,
        private readonly EigeneListe $liste,
        private readonly Sitzung $mealie,
        private readonly Gruppenzuordnung $zuordnung,
        private readonly Ladenfilter $filter,
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

        foreach ($this->katalog->gruppiert($this->eigeneIds()) as $gruppe) {
            $zeilen[$gruppe->name] = array_map(
                fn (Artikel $artikel) => new Zeile($artikel->id, $artikel->name, null, ausMealie: false),
                $gruppe->artikel,
            );
        }

        foreach ($this->offeneMealieArtikel() as $eintrag) {
            $zeilen[$this->zuordnung->fuerLabel($eintrag->label)][] = new Zeile(
                $eintrag->id,
                $eintrag->text,
                $eintrag->notiz,
                ausMealie: true,
            );
        }

        return array_values(array_map(
            fn (string $name) => new Abschnitt($name, array_values($zeilen[$name])),
            $this->reihenfolge(array_keys($zeilen)),
        ));
    }

    /**
     * Die eigenen Artikel, die auf dem Screen stehen — bei gesetztem Filter
     * nur die, die es im gewählten Laden gibt.
     *
     * @return list<string>
     */
    public function eigeneIds(): array
    {
        return $this->katalog->imLaden($this->liste->artikelIds(), $this->laden());
    }

    /**
     * Die offenen Mealie-Artikel, die auf dem Screen stehen.
     *
     * Mealie kennt keine Läden. Ein Artikel erbt sie deshalb von der
     * Warengruppe, unter der er auf dem Screen landet. Steht er unter einer
     * Überschrift, die es im Katalog nicht gibt, ist nichts zu erben — dann
     * bleibt er in jedem Filter stehen: ein übersehener Artikel wiegt
     * schwerer als eine Zeile zu viel.
     *
     * @return list<Eintrag>
     */
    public function offeneMealieArtikel(): array
    {
        $laden = $this->laden();

        if ($laden === null) {
            return $this->mealie->offene();
        }

        return array_values(array_filter(
            $this->mealie->offene(),
            function (Eintrag $eintrag) use ($laden): bool {
                $gruppe = $this->katalog->gruppeMitNamen($this->zuordnung->fuerLabel($eintrag->label));

                return $gruppe === null
                    || $gruppe->laeden === []
                    || in_array($laden, $gruppe->laeden, strict: true);
            },
        ));
    }

    /**
     * Die abgehakten Mealie-Artikel — ohne Gruppierung, in Mealies
     * Reihenfolge. Eigene Artikel sind nie dabei: die wandern beim Abhaken
     * zurück in den Vorrat, statt liegen zu bleiben.
     *
     * Der Ladenfilter greift hier nicht: der Block sagt, was schon im Wagen
     * liegt, und das gehört vollständig hin.
     *
     * @return list<Zeile>
     */
    public function abgehakte(): array
    {
        return array_map(
            fn (Eintrag $eintrag) => new Zeile($eintrag->id, $eintrag->text, null, ausMealie: true),
            $this->mealie->abgehakte(),
        );
    }

    /**
     * Die Rezepte, aus denen die Mealie-Liste zusammengetragen wurde — jedes
     * einmal, in der Reihenfolge, in der die Artikel sie mitbringen. Sie
     * stehen als eigener Block unter der Liste statt an jeder Zeile: an der
     * Zeile gehört die Notiz aus Mealie hin, und dieselben vier Rezeptnamen
     * unter zwanzig Artikeln sind Rauschen.
     *
     * Abgehakte Artikel zählen mit — ein Rezept verschwindet nicht, weil
     * seine Zutaten schon im Wagen liegen.
     *
     * @return list<string>
     */
    public function rezepte(): array
    {
        $namen = [];

        foreach ($this->mealie->alle() as $eintrag) {
            foreach ($eintrag->rezepte as $name) {
                $namen[$name] = $name;
            }
        }

        return array_values($namen);
    }

    /**
     * Wie viele Artikel auf dem Screen offen sind: eigene plus nicht
     * abgehakte Mealie-Artikel — beides, was der Filter durchlässt.
     */
    public function anzahl(): int
    {
        return count($this->eigeneIds()) + count($this->offeneMealieArtikel());
    }

    /**
     * Wie viele offen wären, stünde der Filter auf „Alle“. Für den
     * Untertitel „5 von 12 Artikeln“.
     */
    public function gesamtzahl(): int
    {
        return $this->liste->anzahl() + count($this->mealie->offene());
    }

    public function laden(): ?Laden
    {
        return $this->filter->laden();
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
