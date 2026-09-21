<?php

namespace App\Vorrat;

use App\Katalog\Katalog;
use App\Katalog\Laden;
use App\Katalog\Ladenfilter;
use App\Katalog\Ladenzuordnung;
use App\Mealie\Eintrag;
use App\Mealie\Gruppenzuordnung;
use App\Mealie\Sitzung as Einkaufssitzung;

/**
 * Was im Vorrat-Screen steht: die Artikel der Mealie-Vorratsliste, die
 * gerade nicht auf der Einkaufsliste stehen — gruppiert nach Warengruppe.
 *
 * Abgehakte Einkaufsartikel zählen nicht als „auf der Liste“: was im Wagen
 * liegt, gehört wieder in den Vorrat, sonst wäre er nach einem Einkauf leer,
 * bis jemand in Mealie aufräumt.
 */
final class Uebersicht
{
    public function __construct(
        private readonly Katalog $katalog,
        private readonly Sitzung $vorrat,
        private readonly Einkaufssitzung $einkauf,
        private readonly Gruppenzuordnung $zuordnung,
        private readonly Ladenzuordnung $ladenzuordnung,
        private readonly Ladenfilter $filter,
    ) {}

    /**
     * Die Gruppen des Screens: erst die Warengruppen des Katalogs in seiner
     * Reihenfolge, danach Gruppen, die nur aus einem Mealie-Label entstanden
     * sind, alphabetisch. Leere Gruppen kommen nicht vor.
     *
     * @return list<Abschnitt>
     */
    public function abschnitte(string $suchbegriff = ''): array
    {
        $zeilen = [];

        foreach ($this->sichtbare($suchbegriff) as $artikel) {
            $zeilen[$this->zuordnung->fuerLabel($artikel->label)][] = $artikel;
        }

        return array_values(array_map(
            fn (string $name) => new Abschnitt($name, array_values($zeilen[$name])),
            $this->katalog->reihenfolge(array_keys($zeilen)),
        ));
    }

    /**
     * Die Artikel, die der Screen zeigt — ohne die, die schon auf der
     * Einkaufsliste stehen, und gefiltert nach Suchbegriff und Laden.
     *
     * @return list<Artikel>
     */
    public function sichtbare(string $suchbegriff = ''): array
    {
        $begriff = trim($suchbegriff);
        $laden = $this->laden();

        return array_values(array_filter(
            $this->offene(),
            fn (Artikel $artikel) => ($begriff === '' || mb_stripos($artikel->name, $begriff) !== false)
                && $this->ladenzuordnung->gibtEsIn($laden, $artikel->label, $artikel->laeden),
        ));
    }

    /**
     * Alles, was im Vorrat steht und nicht offen auf der Einkaufsliste liegt —
     * ungeachtet von Suche und Laden. Der Screen entscheidet daran, ob die
     * Chips überhaupt etwas zu filtern haben.
     *
     * @return list<Artikel>
     */
    public function offene(): array
    {
        $aufDerListe = $this->aufDerEinkaufsliste();

        return array_values(array_filter(
            $this->vorrat->alle(),
            fn (Artikel $artikel) => ! isset($aufDerListe[self::kennzeichen($artikel->lebensmittelId, $artikel->name)]),
        ));
    }

    /**
     * Der offene Einkaufslisten-Eintrag zu diesem Vorratsartikel — `null`,
     * wenn er nicht draufsteht.
     */
    public function aufDerListe(Artikel $artikel): ?Eintrag
    {
        return $this->aufDerEinkaufsliste()[self::kennzeichen($artikel->lebensmittelId, $artikel->name)] ?? null;
    }

    /**
     * Der abgehakte Eintrag zu diesem Vorratsartikel — er wird beim Tap
     * wieder geöffnet, statt eine zweite Zeile für dasselbe anzulegen.
     */
    public function abgehakter(Artikel $artikel): ?Eintrag
    {
        $kennzeichen = self::kennzeichen($artikel->lebensmittelId, $artikel->name);

        foreach ($this->einkauf->abgehakte() as $eintrag) {
            if (self::kennzeichenVon($eintrag) === $kennzeichen) {
                return $eintrag;
            }
        }

        return null;
    }

    public function laden(): ?Laden
    {
        return $this->filter->laden();
    }

    /**
     * Die offenen Einkaufslisten-Einträge, nach Kennzeichen erreichbar.
     *
     * @return array<string, Eintrag>
     */
    private function aufDerEinkaufsliste(): array
    {
        $eintraege = [];

        foreach ($this->einkauf->offene() as $eintrag) {
            $eintraege[self::kennzeichenVon($eintrag)] = $eintrag;
        }

        return $eintraege;
    }

    private static function kennzeichenVon(Eintrag $eintrag): string
    {
        $lebensmittelId = $eintrag->roh['foodId'] ?? $eintrag->roh['food']['id'] ?? null;

        return self::kennzeichen(is_string($lebensmittelId) ? $lebensmittelId : null, $eintrag->text);
    }

    /**
     * Woran die App zwei Zeilen als denselben Artikel erkennt: am
     * Mealie-Lebensmittel, wenn es eines gibt, sonst am Namen. Artikel ohne
     * Lebensmittel — Toilettenpapier, Müllbeutel — haben in Mealie nichts
     * Stabileres.
     */
    private static function kennzeichen(?string $lebensmittelId, string $name): string
    {
        return $lebensmittelId !== null && $lebensmittelId !== ''
            ? 'food:'.$lebensmittelId
            : 'name:'.mb_strtolower(trim($name));
    }
}
