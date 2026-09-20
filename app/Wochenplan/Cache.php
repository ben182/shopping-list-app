<?php

namespace App\Wochenplan;

use App\Models\MealieCache;
use Carbon\CarbonImmutable;

/**
 * Die zuletzt erfolgreich geladenen Wochen, auf der Platte — eine Zeile je
 * Woche, damit das Blättern im Laden auch ohne Verbindung etwas zu zeigen hat.
 *
 * Sie teilt sich die Tabelle mit App\Mealie\Cache; getrennt hält sie ein
 * Präfix vor dem Wochenschlüssel. Die Einkaufsliste hat genau einen
 * Datensatz, der Wochenplan so viele, wie der Nutzer Wochen aufgeschlagen hat.
 */
final class Cache
{
    private const PRAEFIX = 'wochenplan:';

    /**
     * Die Einträge einer Woche — `null`, wenn diese Woche nie geladen wurde.
     * Der Unterschied zählt: eine leere Woche ist ein Ergebnis, eine
     * unbekannte ist keines.
     *
     * @return ?list<array<string, mixed>>
     */
    public function eintraege(string $schluessel): ?array
    {
        $daten = $this->datensatz($schluessel)?->daten;

        return is_array($daten) ? array_values($daten) : null;
    }

    /** Wann diese Woche zuletzt erfolgreich geladen wurde — `null`, solange nie. */
    public function stand(string $schluessel): ?CarbonImmutable
    {
        return $this->datensatz($schluessel)?->geladen_am;
    }

    /**
     * Eine frisch geladene Woche: sie ersetzt die vorherige Fassung derselben
     * Woche und setzt deren Stand neu.
     *
     * @param  list<array<string, mixed>>  $eintraege
     */
    public function speichern(string $schluessel, array $eintraege): void
    {
        MealieCache::query()->updateOrCreate(
            ['schluessel' => self::PRAEFIX.$schluessel],
            ['daten' => array_values($eintraege), 'geladen_am' => CarbonImmutable::now()],
        );
    }

    /** Alle Wochen vergessen — die Einkaufsliste in derselben Tabelle bleibt. */
    public function leeren(): void
    {
        MealieCache::query()->where('schluessel', 'like', self::PRAEFIX.'%')->delete();
    }

    private function datensatz(string $schluessel): ?MealieCache
    {
        return MealieCache::query()->find(self::PRAEFIX.$schluessel);
    }
}
