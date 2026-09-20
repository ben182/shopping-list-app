<?php

namespace App\Einkaufen;

/**
 * Was sich gerade noch zurücknehmen lässt — höchstens ein Vorgang, und der
 * nur, bis der Nutzer das nächste Mal etwas tut.
 *
 * Als Singleton registriert, obwohl der Zustand kurzlebig ist: Es geht um
 * den Screen-übergreifenden Lauf eines `async`-Ergebnisses, das nach dem
 * Rendern zurückkommt. Der Einkaufen-Screen räumt den Vorgang beim Mounten
 * ab, womit ein Tab-Wechsel die Leiste von selbst abräumt.
 */
final class Rueckgaengig
{
    private ?Abhakvorgang $vorgang = null;

    public function merken(Abhakvorgang $vorgang): void
    {
        $this->vorgang = $vorgang;
    }

    /**
     * Ein leer gewordener Vorgang zählt nicht mehr: Hat Mealie das Abhaken
     * abgelehnt und waren keine eigenen Artikel dabei, ist nichts passiert,
     * was sich zurücknehmen ließe.
     */
    public function vorgang(): ?Abhakvorgang
    {
        return $this->vorgang?->leer() ? null : $this->vorgang;
    }

    public function verwerfen(): void
    {
        $this->vorgang = null;
    }
}
