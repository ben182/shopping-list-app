<?php

namespace App\Wochenplan;

use App\Mealie\Fehler;
use App\Mealie\Fehlerzustand;

/**
 * Was diese App-Sitzung vom Wochenplan schon weiß: je Woche die geladenen
 * Einträge, und der Grund, falls das letzte Laden scheiterte.
 *
 * Der Zustand gehört der Sitzung, nicht dem Screen — jeder Tab-Wechsel
 * mountet den Wochenplan neu, und eine schon geladene Woche soll dann nicht
 * wieder hinter einem Spinner verschwinden. Den App-Neustart überlebt sie
 * ebenfalls: dahinter liegt `Cache` in SQLite, aus dem sich jede Woche beim
 * ersten Zugriff füllt.
 */
final class Sitzung
{
    /**
     * Je Woche ihre Einträge — `null` für „diese Woche kennt die App nicht“.
     * Ein fehlender Schlüssel heißt dagegen nur „noch nicht im Cache
     * nachgesehen“.
     *
     * @var array<string, ?list<Eintrag>>
     */
    private array $wochen = [];

    private ?Fehler $fehler = null;

    public function __construct(private readonly Cache $cache) {}

    public function hat(string $schluessel): bool
    {
        return $this->geladen($schluessel) !== null;
    }

    /**
     * @return list<Eintrag>
     */
    public function eintraege(string $schluessel): array
    {
        return $this->geladen($schluessel) ?? [];
    }

    /**
     * Eine frisch geladene Woche. Sie räumt zugleich den Fehlerzustand ab:
     * wenn Mealie antwortet, ist der Grund von vorhin erledigt — und den
     * Cache, in dem sonst jede je aufgeschlagene Woche liegen bliebe.
     *
     * @param  list<array<string, mixed>>  $eintraege
     */
    public function setzen(string $schluessel, array $eintraege): void
    {
        $eintraege = array_values($eintraege);

        $this->wochen[$schluessel] = array_map(Eintrag::ausDaten(...), $eintraege);

        $this->fehler = null;

        $this->cache->speichern($schluessel, $eintraege);
        $this->cache->aufraeumen($schluessel);
    }

    public function fehlerMelden(Fehler $fehler): void
    {
        $this->fehler = $fehler;
    }

    public function fehler(): ?Fehler
    {
        return $this->fehler;
    }

    /**
     * Was das Banner über dieser Woche sagt — `null`, solange es keines gibt.
     * Der Stand gehört der Woche, die gerade aufgeschlagen ist: eine andere,
     * jüngere Woche im Cache sagt über diese hier nichts.
     */
    public function fehlerzustand(string $schluessel): ?Fehlerzustand
    {
        return $this->fehler === null ? null : new Fehlerzustand($this->fehler, $this->cache->stand($schluessel));
    }

    /** Kein Token mehr — was aus Mealie kam, hat hier nichts mehr verloren. */
    public function vergessen(): void
    {
        $this->wochen = [];
        $this->fehler = null;

        $this->cache->leeren();
    }

    /**
     * @return ?list<Eintrag>
     */
    private function geladen(string $schluessel): ?array
    {
        if (! array_key_exists($schluessel, $this->wochen)) {
            $daten = $this->cache->eintraege($schluessel);

            $this->wochen[$schluessel] = $daten === null ? null : array_map(Eintrag::ausDaten(...), $daten);
        }

        return $this->wochen[$schluessel];
    }
}
