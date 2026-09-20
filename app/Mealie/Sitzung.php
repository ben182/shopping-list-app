<?php

namespace App\Mealie;

/**
 * Was die App von Mealie weiß, solange sie läuft.
 *
 * Als Singleton registriert: der Einkaufen-Screen wird bei jedem Tab-Wechsel
 * neu gemountet, die geladene Liste soll das überleben. Erst EKL-009 macht
 * daraus einen Cache, der auch den App-Neustart übersteht.
 */
final class Sitzung
{
    /** @var list<Eintrag> */
    private array $eintraege = [];

    private bool $ladevorgangBegonnen = false;

    /**
     * Ist der Abschnitt „Abgehakt“ aufgeklappt? Der Zustand gehört in die
     * Sitzung und nicht auf den Screen: der wird bei jedem Tab-Wechsel neu
     * gemountet, der Abschnitt soll trotzdem offen bleiben.
     */
    private bool $abgehakteAufgeklappt = false;

    /**
     * Die offenen Artikel — in der Reihenfolge, in der Mealie sie geliefert hat.
     *
     * @return list<Eintrag>
     */
    public function offene(): array
    {
        return array_values(array_filter($this->eintraege, fn (Eintrag $eintrag) => ! $eintrag->abgehakt));
    }

    /**
     * Die abgehakten Artikel — in Mealies Reihenfolge.
     *
     * @return list<Eintrag>
     */
    public function abgehakte(): array
    {
        return array_values(array_filter($this->eintraege, fn (Eintrag $eintrag) => $eintrag->abgehakt));
    }

    /**
     * @return list<Eintrag>
     */
    public function alle(): array
    {
        return $this->eintraege;
    }

    public function finden(string $id): ?Eintrag
    {
        foreach ($this->eintraege as $eintrag) {
            if ($eintrag->id === $id) {
                return $eintrag;
            }
        }

        return null;
    }

    /**
     * Legt den Haken eines Artikels lokal um. Der Screen zeigt das sofort,
     * lange bevor Mealie geantwortet hat — und dieselbe Bewegung rückwärts
     * nimmt es zurück, wenn Mealie widerspricht.
     */
    public function haken(string $id, bool $abgehakt): void
    {
        $this->eintraege = array_map(
            fn (Eintrag $eintrag) => $eintrag->id === $id ? $eintrag->mitHaken($abgehakt) : $eintrag,
            $this->eintraege,
        );
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
     * @param  list<array{id?: string, text?: string, label?: ?string, rezepte?: ?string, abgehakt?: bool}>  $artikel
     */
    public function setzen(array $artikel): void
    {
        $this->eintraege = array_values(array_map(Eintrag::ausDaten(...), $artikel));
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
}
