<?php

namespace App\Wochenplan;

use App\Mealie\Fehler;

/**
 * Was diese App-Sitzung vom Wochenplan schon weiß: je Woche die geladenen
 * Einträge, und der Grund, falls das letzte Laden scheiterte.
 *
 * Der Zustand gehört der Sitzung, nicht dem Screen — jeder Tab-Wechsel
 * mountet den Wochenplan neu, und eine schon geladene Woche soll dann nicht
 * wieder hinter einem Spinner verschwinden.
 */
final class Sitzung
{
    /** @var array<string, list<Eintrag>> */
    private array $wochen = [];

    private ?Fehler $fehler = null;

    public function hat(string $schluessel): bool
    {
        return array_key_exists($schluessel, $this->wochen);
    }

    /**
     * @return list<Eintrag>
     */
    public function eintraege(string $schluessel): array
    {
        return $this->wochen[$schluessel] ?? [];
    }

    /**
     * Eine frisch geladene Woche. Sie räumt zugleich den Fehlerzustand ab:
     * wenn Mealie antwortet, ist der Grund von vorhin erledigt.
     *
     * @param  list<array<string, mixed>>  $eintraege
     */
    public function setzen(string $schluessel, array $eintraege): void
    {
        $this->wochen[$schluessel] = array_map(
            fn (array $daten) => Eintrag::ausDaten($daten),
            array_values($eintraege),
        );

        $this->fehler = null;
    }

    public function fehlerMelden(Fehler $fehler): void
    {
        $this->fehler = $fehler;
    }

    public function fehler(): ?Fehler
    {
        return $this->fehler;
    }

    /** Kein Token mehr — was aus Mealie kam, hat hier nichts mehr verloren. */
    public function vergessen(): void
    {
        $this->wochen = [];
        $this->fehler = null;
    }
}
