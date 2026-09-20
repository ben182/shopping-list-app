<?php

namespace App\Katalog;

/**
 * Die feste Artikelliste der App, gelesen aus `config/katalog.php`. Der
 * Katalog ist zur Laufzeit unveränderlich; er bestimmt überall die
 * Anzeigereihenfolge von Gruppen und Artikeln.
 */
final class Katalog
{
    /** @var list<Gruppe>|null */
    private ?array $gruppen = null;

    /**
     * Alle Gruppen mit allen ihren Artikeln, in Katalogreihenfolge.
     *
     * @return list<Gruppe>
     */
    public function gruppen(): array
    {
        return $this->gruppen ??= array_values(array_map(
            fn (string $gruppeId, array $gruppe) => new Gruppe(
                id: $gruppeId,
                name: $gruppe['name'],
                artikel: array_values(array_map(
                    fn (string $artikelId, string $name) => new Artikel($artikelId, $name, $gruppeId),
                    array_keys($gruppe['artikel']),
                    $gruppe['artikel'],
                )),
            ),
            array_keys($katalog = config('katalog.gruppen')),
            $katalog,
        ));
    }

    /**
     * Alle Artikel-IDs in Katalogreihenfolge.
     *
     * @return list<string>
     */
    public function artikelIds(): array
    {
        $ids = [];

        foreach ($this->gruppen() as $gruppe) {
            foreach ($gruppe->artikel as $artikel) {
                $ids[] = $artikel->id;
            }
        }

        return $ids;
    }

    public function kennt(string $artikelId): bool
    {
        return in_array($artikelId, $this->artikelIds(), strict: true);
    }

    /**
     * Aus den übergebenen IDs die, deren Artikelname den Suchbegriff enthält —
     * ohne Rücksicht auf Groß-/Kleinschreibung. Leerraum am Anfang und Ende des
     * Begriffs zählt nicht mit, ein leerer Begriff lässt alles durch. Die
     * Reihenfolge ist wieder die des Katalogs, nicht die der Eingabe.
     *
     * @param  list<string>  $artikelIds
     * @return list<string>
     */
    public function gefiltert(array $artikelIds, string $suchbegriff): array
    {
        $begriff = trim($suchbegriff);

        if ($begriff === '') {
            return $artikelIds;
        }

        $gesucht = array_flip($artikelIds);
        $treffer = [];

        foreach ($this->gruppen() as $gruppe) {
            foreach ($gruppe->artikel as $artikel) {
                if (isset($gesucht[$artikel->id]) && mb_stripos($artikel->name, $begriff) !== false) {
                    $treffer[] = $artikel->id;
                }
            }
        }

        return $treffer;
    }

    /**
     * Die übergebenen Artikel, gruppiert und in Katalogreihenfolge sortiert.
     * IDs ohne Katalog-Eintrag fallen weg, ebenso Gruppen, von denen dabei
     * nichts übrig bleibt.
     *
     * @param  list<string>  $artikelIds
     * @return list<Gruppe>
     */
    public function gruppiert(array $artikelIds): array
    {
        $gesucht = array_flip($artikelIds);
        $gefuellt = [];

        foreach ($this->gruppen() as $gruppe) {
            $artikel = array_values(array_filter(
                $gruppe->artikel,
                fn (Artikel $artikel) => isset($gesucht[$artikel->id]),
            ));

            if ($artikel !== []) {
                $gefuellt[] = new Gruppe($gruppe->id, $gruppe->name, $artikel);
            }
        }

        return $gefuellt;
    }
}
