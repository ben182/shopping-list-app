<?php

namespace App\Vorrat;

use App\Models\MealieCache;
use Carbon\CarbonImmutable;

/**
 * Die zuletzt erfolgreich geladene Vorratsliste, auf der Platte.
 *
 * Anders als die Einkaufsliste wird der Vorrat in der App nie verändert —
 * er wird in Mealie gepflegt. Der Cache kennt deshalb nur „ersetzen“ und
 * ist das, was der Screen im Funkloch noch zeigen kann.
 */
final class Cache
{
    private const SCHLUESSEL = 'vorrat';

    /**
     * @return list<array<string, mixed>>
     */
    public function artikel(): array
    {
        $daten = $this->datensatz()?->daten;

        return is_array($daten) ? array_values($daten) : [];
    }

    /** Wann zuletzt erfolgreich geladen wurde — `null`, solange nie. */
    public function stand(): ?CarbonImmutable
    {
        return $this->datensatz()?->geladen_am;
    }

    /**
     * @param  list<array<string, mixed>>  $artikel
     */
    public function speichern(array $artikel): void
    {
        MealieCache::query()->updateOrCreate(
            ['schluessel' => self::SCHLUESSEL],
            ['daten' => $artikel, 'geladen_am' => CarbonImmutable::now()],
        );
    }

    public function leeren(): void
    {
        MealieCache::query()->whereKey(self::SCHLUESSEL)->delete();
    }

    private function datensatz(): ?MealieCache
    {
        return MealieCache::query()->find(self::SCHLUESSEL);
    }
}
