<?php

namespace App\Liste;

use App\Katalog\Katalog;
use App\Models\ListenArtikel;

/**
 * Welche Katalog-Artikel gerade auf der eigenen Einkaufsliste stehen.
 *
 * Gespeichert wird nur die Artikel-ID. Ein App-Update darf den Katalog
 * ändern, ohne den gespeicherten Zustand anzufassen — IDs, die es danach
 * nicht mehr gibt, filtert diese Klasse beim Lesen heraus, damit sie
 * nirgends auftauchen und in keiner Anzahl mitzählen.
 */
final class EigeneListe
{
    public function __construct(private readonly Katalog $katalog) {}

    /**
     * Die Artikel auf der Liste, in Katalogreihenfolge und ohne die IDs,
     * die der Katalog nicht mehr kennt.
     *
     * @return list<string>
     */
    public function artikelIds(): array
    {
        $gespeichert = array_flip(ListenArtikel::query()->pluck('artikel_id')->all());

        return array_values(array_filter(
            $this->katalog->artikelIds(),
            fn (string $artikelId) => isset($gespeichert[$artikelId]),
        ));
    }

    /**
     * Die Katalog-Artikel, die nicht auf der Liste stehen — der Vorrat.
     *
     * @return list<string>
     */
    public function vorratIds(): array
    {
        $aufDerListe = array_flip($this->artikelIds());

        return array_values(array_filter(
            $this->katalog->artikelIds(),
            fn (string $artikelId) => ! isset($aufDerListe[$artikelId]),
        ));
    }

    public function anzahl(): int
    {
        return count($this->artikelIds());
    }

    public function hinzufuegen(string $artikelId): void
    {
        if (! $this->katalog->kennt($artikelId)) {
            return;
        }

        ListenArtikel::query()->firstOrCreate(['artikel_id' => $artikelId]);
    }

    public function entfernen(string $artikelId): void
    {
        ListenArtikel::query()->whereKey($artikelId)->delete();
    }
}
