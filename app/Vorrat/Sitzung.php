<?php

namespace App\Vorrat;

use App\Mealie\Fehler;
use App\Mealie\Fehlerzustand;

/**
 * Was die App vom Vorrat weiß.
 *
 * Wie die Sitzung der Einkaufsliste ein Singleton: der Vorrat-Screen wird bei
 * jedem Tab-Wechsel neu gemountet, die geladene Liste soll das überleben.
 * Den App-Neustart überlebt sie ebenfalls — dahinter liegt `Cache` in SQLite.
 */
final class Sitzung
{
    /**
     * Die Artikel — `null`, solange noch niemand gefragt hat. Erst die erste
     * Frage geht an den Cache.
     *
     * @var ?list<Artikel>
     */
    private ?array $artikel = null;

    private bool $ladevorgangBegonnen = false;

    /** Woran das letzte Laden gescheitert ist — `null`, wenn es geklappt hat. */
    private ?Fehler $fehler = null;

    public function __construct(private readonly Cache $cache) {}

    /**
     * @return list<Artikel>
     */
    public function alle(): array
    {
        return $this->artikel ??= array_values(array_map(Artikel::ausDaten(...), $this->cache->artikel()));
    }

    public function finden(string $id): ?Artikel
    {
        foreach ($this->alle() as $artikel) {
            if ($artikel->id === $id) {
                return $artikel;
            }
        }

        return null;
    }

    /**
     * Eine frisch geladene Liste. Sie ersetzt den Cache samt Stand und räumt
     * einen vorherigen Fehler ab.
     *
     * @param  list<array<string, mixed>>  $artikel
     */
    public function setzen(array $artikel): void
    {
        $this->artikel = array_values(array_map(Artikel::ausDaten(...), $artikel));
        $this->fehler = null;

        $this->cache->speichern(array_map(fn (Artikel $eintrag) => $eintrag->daten(), $this->artikel));
    }

    public function fehlerMelden(Fehler $fehler): void
    {
        $this->fehler = $fehler;
    }

    public function fehlerzustand(): ?Fehlerzustand
    {
        return $this->fehler === null ? null : new Fehlerzustand($this->fehler, $this->cache->stand());
    }

    /** Alles über den Vorrat vergessen — ohne Token darf nichts dastehen. */
    public function vergessen(): void
    {
        $this->artikel = [];
        $this->fehler = null;

        $this->cache->leeren();
    }

    /**
     * Meldet einen Ladevorgang an und sagt, ob es der erste dieser Sitzung
     * war. Nur der erste bekommt eine sichtbare Ladezeile.
     */
    public function ersterLadevorgang(): bool
    {
        $erster = ! $this->ladevorgangBegonnen;

        $this->ladevorgangBegonnen = true;

        return $erster;
    }
}
