<?php

namespace App\Mealie;

use App\Models\MealieCache;
use Carbon\CarbonImmutable;

/**
 * Die zuletzt erfolgreich geladene Mealie-Einkaufsliste, auf der Platte.
 *
 * Sie ist das, was der Screen im Laden noch zeigen kann, wenn Mealie nicht
 * mehr antwortet — und der Grund, warum das Fehlerbanner einen „Stand“
 * nennen kann.
 */
final class Cache
{
    private const SCHLUESSEL = 'einkaufsliste';

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
     * Eine frisch geladene Liste: ersetzt die vorherige und setzt den Stand.
     *
     * @param  list<array<string, mixed>>  $artikel
     */
    public function speichern(array $artikel): void
    {
        MealieCache::query()->updateOrCreate(
            ['schluessel' => self::SCHLUESSEL],
            ['daten' => $artikel, 'geladen_am' => CarbonImmutable::now()],
        );
    }

    /**
     * Dieselbe Liste mit umgelegtem Haken. Der Stand bleibt, wo er war: er
     * meint den letzten *Ladevorgang*, und ein Tap ist keiner.
     *
     * @param  list<array<string, mixed>>  $artikel
     */
    public function aktualisieren(array $artikel): void
    {
        $datensatz = $this->datensatz();

        if ($datensatz === null) {
            return;
        }

        $datensatz->update(['daten' => $artikel]);
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
