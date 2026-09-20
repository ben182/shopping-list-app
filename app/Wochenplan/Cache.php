<?php

namespace App\Wochenplan;

use App\Models\MealieCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

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

    /** So viele Wochen vor und nach der aktuellen bleiben beim Aufräumen stehen. */
    private const FENSTER = 4;

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

    /**
     * Die Wochen vergessen, die weit von der aktuellen Kalenderwoche
     * wegliegen: mehr als vier davor oder dahinter. So bleiben höchstens neun
     * stehen, statt dass jede je aufgeschlagene Woche für immer liegen bleibt.
     *
     * Die gerade geladene Woche bleibt in jedem Fall — sie ist ja
     * aufgeschlagen, auch wenn der Nutzer weit geblättert hat.
     */
    public function aufraeumen(string $behalten): void
    {
        $montag = Woche::aktuelle()->montag;

        $fruehestens = Woche::mit($montag->subWeeks(self::FENSTER))->schluessel();
        $spaetestens = Woche::mit($montag->addWeeks(self::FENSTER))->schluessel();

        MealieCache::query()
            ->where('schluessel', 'like', self::PRAEFIX.'%')
            ->whereKeyNot(self::PRAEFIX.$behalten)
            ->where(function (Builder $abfrage) use ($fruehestens, $spaetestens): void {
                // Die Schlüssel tragen das Datum als `Y-m-d`, deshalb ordnet
                // sie der Textvergleich genauso wie der Kalender.
                $abfrage->where('schluessel', '<', self::PRAEFIX.$fruehestens)
                    ->orWhere('schluessel', '>', self::PRAEFIX.$spaetestens);
            })
            ->delete();
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
