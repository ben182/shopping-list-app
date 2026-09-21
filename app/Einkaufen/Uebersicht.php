<?php

namespace App\Einkaufen;

use App\Katalog\Katalog;
use App\Katalog\Laden;
use App\Katalog\Ladenfilter;
use App\Katalog\Ladenzuordnung;
use App\Mealie\Eintrag;
use App\Mealie\Gruppenzuordnung;
use App\Mealie\Sitzung;

/**
 * Die eine Liste, durch die man im Laden läuft: die offenen Artikel der
 * Mealie-Einkaufsliste, gegliedert nach Warengruppen.
 *
 * Steht der Ladenfilter nicht auf „Alle“, bleibt davon übrig, was es im
 * gewählten Laden gibt — die Warengruppen bleiben die Gliederung, der Laden
 * entscheidet nur, was drinsteht.
 */
final class Uebersicht
{
    public function __construct(
        private readonly Katalog $katalog,
        private readonly Sitzung $mealie,
        private readonly Gruppenzuordnung $zuordnung,
        private readonly Ladenzuordnung $ladenzuordnung,
        private readonly Ladenfilter $filter,
    ) {}

    /**
     * Erst die Warengruppen des Katalogs in seiner Reihenfolge, danach die
     * Gruppen, die nur aus einem Mealie-Label entstanden sind, alphabetisch.
     * Leere Gruppen kommen gar nicht erst vor.
     *
     * @return list<Abschnitt>
     */
    public function abschnitte(): array
    {
        $zeilen = [];

        foreach ($this->offeneMealieArtikel() as $eintrag) {
            $zeilen[$this->zuordnung->fuerLabel($eintrag->label)][] = new Zeile(
                $eintrag->id,
                $eintrag->text,
                $eintrag->notiz,
                ausRezept: $eintrag->rezepte !== [],
            );
        }

        return array_values(array_map(
            fn (string $name) => new Abschnitt($name, array_values($zeilen[$name])),
            $this->katalog->reihenfolge(array_keys($zeilen)),
        ));
    }

    /**
     * Die offenen Mealie-Artikel, die auf dem Screen stehen.
     *
     * Mealie kennt keine Läden. Ein Artikel bringt sie deshalb entweder
     * selbst mit — dann stand am Vorratseintrag eine Ausnahme — oder erbt
     * sie von der Warengruppe, unter der er landet. Hat er beides nicht,
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
            fn (Eintrag $eintrag) => $this->ladenzuordnung->gibtEsIn($laden, $eintrag->label, $eintrag->laeden),
        ));
    }

    /**
     * Die abgehakten Artikel — ohne Gruppierung, in Mealies Reihenfolge.
     *
     * Der Ladenfilter greift hier nicht: der Block sagt, was schon im Wagen
     * liegt, und das gehört vollständig hin.
     *
     * @return list<Zeile>
     */
    public function abgehakte(): array
    {
        return array_map(
            fn (Eintrag $eintrag) => new Zeile($eintrag->id, $eintrag->text, null, ausRezept: $eintrag->rezepte !== []),
            $this->mealie->abgehakte(),
        );
    }

    /**
     * Die Rezepte, aus denen die Liste zusammengetragen wurde — jedes einmal,
     * in der Reihenfolge, in der die Artikel sie mitbringen. Sie stehen als
     * eigener Block unter der Liste statt an jeder Zeile: an der Zeile gehört
     * die Notiz aus Mealie hin, und dieselben vier Rezeptnamen unter zwanzig
     * Artikeln sind Rauschen.
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

    /** Wie viele Artikel auf dem Screen offen sind — das, was der Filter durchlässt. */
    public function anzahl(): int
    {
        return count($this->offeneMealieArtikel());
    }

    /**
     * Wie viele offen wären, stünde der Filter auf „Alle“. Für den
     * Untertitel „5 von 12 Artikeln“.
     */
    public function gesamtzahl(): int
    {
        return count($this->mealie->offene());
    }

    public function laden(): ?Laden
    {
        return $this->filter->laden();
    }
}
