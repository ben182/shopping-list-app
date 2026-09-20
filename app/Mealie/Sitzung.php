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
     * Die offenen Artikel — in der Reihenfolge, in der Mealie sie geliefert hat.
     *
     * @return list<Eintrag>
     */
    public function offene(): array
    {
        return array_values(array_filter($this->eintraege, fn (Eintrag $eintrag) => ! $eintrag->abgehakt));
    }

    /**
     * @return list<Eintrag>
     */
    public function alle(): array
    {
        return $this->eintraege;
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
