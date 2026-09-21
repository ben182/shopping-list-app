<?php

namespace App\Mealie;

/**
 * Was die App von Mealie weiß.
 *
 * Als Singleton registriert: der Einkaufen-Screen wird bei jedem Tab-Wechsel
 * neu gemountet, die geladene Liste soll das überleben. Den App-Neustart
 * überlebt sie ebenfalls — dahinter liegt `Cache` in SQLite, aus dem die
 * Sitzung sich beim ersten Zugriff füllt. Der Screen bekommt seine Zeilen
 * deshalb sofort, lange bevor Mealie geantwortet hat.
 */
final class Sitzung
{
    /**
     * Die Artikel — `null`, solange noch niemand gefragt hat. Erst die erste
     * Frage geht an den Cache.
     *
     * @var ?list<Eintrag>
     */
    private ?array $eintraege = null;

    private bool $ladevorgangBegonnen = false;

    /**
     * Ist der Abschnitt „Abgehakt“ aufgeklappt? Der Zustand gehört in die
     * Sitzung und nicht auf den Screen: der wird bei jedem Tab-Wechsel neu
     * gemountet, der Abschnitt soll trotzdem offen bleiben.
     */
    private bool $abgehakteAufgeklappt = false;

    /** Woran das letzte Laden gescheitert ist — `null`, wenn es geklappt hat. */
    private ?Fehler $fehler = null;

    public function __construct(private readonly Cache $cache) {}

    /**
     * Die offenen Artikel — in der Reihenfolge, in der Mealie sie geliefert hat.
     *
     * @return list<Eintrag>
     */
    public function offene(): array
    {
        return array_values(array_filter($this->alle(), fn (Eintrag $eintrag) => ! $eintrag->abgehakt));
    }

    /**
     * Die abgehakten Artikel — in Mealies Reihenfolge.
     *
     * @return list<Eintrag>
     */
    public function abgehakte(): array
    {
        return array_values(array_filter($this->alle(), fn (Eintrag $eintrag) => $eintrag->abgehakt));
    }

    /**
     * @return list<Eintrag>
     */
    public function alle(): array
    {
        return $this->eintraege ??= array_values(array_map(Eintrag::ausDaten(...), $this->cache->artikel()));
    }

    public function finden(string $id): ?Eintrag
    {
        foreach ($this->alle() as $eintrag) {
            if ($eintrag->id === $id) {
                return $eintrag;
            }
        }

        return null;
    }

    /**
     * Legt den Haken eines Artikels lokal um. Der Screen zeigt das sofort,
     * lange bevor Mealie geantwortet hat — und dieselbe Bewegung rückwärts
     * nimmt es zurück, wenn Mealie widerspricht. Der Cache zieht mit, damit
     * ein Neustart nicht den Stand von vor dem Tap zeigt.
     */
    public function haken(string $id, bool $abgehakt): void
    {
        $this->eintraege = array_map(
            fn (Eintrag $eintrag) => $eintrag->id === $id ? $eintrag->mitHaken($abgehakt) : $eintrag,
            $this->alle(),
        );

        $this->cache->aktualisieren($this->daten());
    }

    /**
     * Legt den Haken mehrerer Artikel in einem Zug um — „Alles abhaken“ und
     * seine Rücknahme. Ein Durchlauf statt einer Schleife über `haken()`:
     * der Cache wird dabei einmal geschrieben, nicht je Artikel.
     *
     * @param  list<string>  $ids
     */
    public function hakenMehrere(array $ids, bool $abgehakt): void
    {
        $betroffen = array_flip($ids);

        $this->eintraege = array_map(
            fn (Eintrag $eintrag) => isset($betroffen[$eintrag->id]) ? $eintrag->mitHaken($abgehakt) : $eintrag,
            $this->alle(),
        );

        $this->cache->aktualisieren($this->daten());
    }

    /**
     * Hängt einen Artikel an die Liste, bevor Mealie ihn kennt: der Tap im
     * Vorrat soll sofort auf der Einkaufsliste stehen. Die ID ist bis zur
     * Antwort eine vorläufige, mit der die App den Artikel wiederfindet.
     *
     * @param  array{id?: string, text?: string, notiz?: ?string, label?: ?string, rezepte?: list<string>, abgehakt?: bool, roh?: array<string, mixed>, laeden?: list<string>}  $daten
     */
    public function einfuegen(array $daten): void
    {
        $this->eintraege = [...$this->alle(), Eintrag::ausDaten($daten)];

        $this->cache->aktualisieren($this->daten());
    }

    /**
     * Ersetzt einen vorläufig eingefügten Artikel durch den, den Mealie
     * angelegt hat — erst danach trägt er eine ID, unter der Mealie ihn
     * abhaken lässt.
     *
     * @param  array{id?: string, text?: string, notiz?: ?string, label?: ?string, rezepte?: list<string>, abgehakt?: bool, roh?: array<string, mixed>, laeden?: list<string>}  $daten
     */
    public function ersetzen(string $id, array $daten): void
    {
        $this->eintraege = array_map(
            fn (Eintrag $eintrag) => $eintrag->id === $id ? Eintrag::ausDaten($daten) : $eintrag,
            $this->alle(),
        );

        $this->cache->aktualisieren($this->daten());
    }

    /** Nimmt einen Artikel wieder heraus — Mealie hat ihn nicht angenommen. */
    public function entfernen(string $id): void
    {
        $this->eintraege = array_values(array_filter(
            $this->alle(),
            fn (Eintrag $eintrag) => $eintrag->id !== $id,
        ));

        $this->cache->aktualisieren($this->daten());
    }

    /**
     * Nimmt mehrere Artikel in einem Zug heraus — das Löschen der abgehakten
     * Zeilen. Zurück kommen sie in ihrer flachen Form: nimmt Mealie das
     * Löschen nicht an, stellt {@see einfuegenMehrere()} sie damit wieder her.
     *
     * @param  list<string>  $ids
     * @return list<array<string, mixed>>
     */
    public function entfernenMehrere(array $ids): array
    {
        $betroffen = array_flip($ids);
        $entfernt = [];
        $bleiben = [];

        foreach ($this->alle() as $eintrag) {
            if (isset($betroffen[$eintrag->id])) {
                $entfernt[] = $eintrag->daten();
            } else {
                $bleiben[] = $eintrag;
            }
        }

        $this->eintraege = $bleiben;

        $this->cache->aktualisieren($this->daten());

        return $entfernt;
    }

    /**
     * Hängt mehrere Artikel wieder an die Liste — die Rücknahme eines
     * gescheiterten Löschens. Sie stehen danach am Ende; ihre Stelle in
     * Mealies Reihenfolge bringt der nächste Ladevorgang zurück.
     *
     * @param  list<array<string, mixed>>  $daten
     */
    public function einfuegenMehrere(array $daten): void
    {
        $this->eintraege = [...$this->alle(), ...array_map(Eintrag::ausDaten(...), $daten)];

        $this->cache->aktualisieren($this->daten());
    }

    public function abgehakteAufgeklappt(): bool
    {
        return $this->abgehakteAufgeklappt;
    }

    public function abgehakteUmklappen(): void
    {
        $this->abgehakteAufgeklappt = ! $this->abgehakteAufgeklappt;
    }

    /**
     * Eine frisch geladene Liste. Sie ersetzt den Cache samt Stand und räumt
     * einen vorherigen Fehler ab — das Banner verschwindet damit von selbst,
     * sobald Mealie wieder antwortet.
     *
     * @param  list<array{id?: string, text?: string, notiz?: ?string, label?: ?string, rezepte?: list<string>, abgehakt?: bool, roh?: array<string, mixed>}>  $artikel
     */
    public function setzen(array $artikel): void
    {
        $this->eintraege = array_values(array_map(Eintrag::ausDaten(...), $artikel));
        $this->fehler = null;

        $this->cache->speichern($this->daten());
    }

    public function fehlerMelden(Fehler $fehler): void
    {
        $this->fehler = $fehler;
    }

    /**
     * Was das Banner sagt — `null`, solange es keines gibt. Der Stand kommt
     * aus dem Cache und meint den letzten erfolgreichen Ladevorgang, nicht
     * den gescheiterten.
     */
    public function fehlerzustand(): ?Fehlerzustand
    {
        return $this->fehler === null ? null : new Fehlerzustand($this->fehler, $this->cache->stand());
    }

    /**
     * Alles über Mealie vergessen — Liste, Cache und Fehler. Das passiert,
     * wenn kein Token mehr hinterlegt ist: ohne Token darf auch nichts mehr
     * aus Mealie auf dem Screen stehen.
     */
    public function vergessen(): void
    {
        $this->eintraege = [];
        $this->fehler = null;

        $this->cache->leeren();
    }

    /**
     * Meldet einen Ladevorgang an und sagt, ob es der erste dieser Sitzung
     * war. Nur der erste bekommt eine sichtbare Ladezeile — jeder weitere
     * läuft still, damit die Liste beim Tab-Wechsel nicht flackert.
     */
    public function ersterLadevorgang(): bool
    {
        $erster = ! $this->ladevorgangBegonnen;

        $this->ladevorgangBegonnen = true;

        return $erster;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function daten(): array
    {
        return array_map(fn (Eintrag $eintrag) => $eintrag->daten(), $this->alle());
    }
}
