<?php

namespace App\Wochenplan;

/**
 * Ein Eintrag des Mealie-Wochenplans, auf das reduziert, was die Zeile zeigt.
 *
 * Mealie kennt zwei Sorten: Einträge mit Rezept (Name, Slug, Bild) und freie
 * Einträge, die nur `title` und `text` tragen. Ein Rezept gilt hier erst dann
 * als Rezept, wenn Name *und* Slug da sind — ohne Slug ließe sich die
 * Rezeptseite nicht öffnen, und eine Zeile, die auf einen Tap nicht reagiert,
 * obwohl sie es verspricht, ist schlimmer als eine ohne Versprechen.
 */
final readonly class Eintrag
{
    public function __construct(
        public string $id,
        public string $datum,
        public ?Mahlzeitentyp $typ,
        public string $typRoh,
        public ?string $rezeptName,
        public ?string $rezeptSlug,
        public ?string $rezeptId,
        public bool $hatBild,
        public string $titel,
        public string $text,
    ) {}

    /**
     * @param  array<string, mixed>  $daten
     */
    public static function ausDaten(array $daten): self
    {
        $typRoh = (string) ($daten['typ'] ?? '');

        return new self(
            id: (string) ($daten['id'] ?? ''),
            datum: (string) ($daten['datum'] ?? ''),
            typ: Mahlzeitentyp::tryFrom($typRoh),
            typRoh: $typRoh,
            rezeptName: $daten['rezeptName'] ?? null,
            rezeptSlug: $daten['rezeptSlug'] ?? null,
            rezeptId: $daten['rezeptId'] ?? null,
            hatBild: (bool) ($daten['hatBild'] ?? false),
            titel: (string) ($daten['titel'] ?? ''),
            text: (string) ($daten['text'] ?? ''),
        );
    }

    /**
     * Zurück in die flache Form, in der der Ladevorgang die Einträge über die
     * `async`-Grenze schickt — genau die, die `ausDaten()` wieder einliest.
     *
     * @return array<string, mixed>
     */
    public function daten(): array
    {
        return [
            'id' => $this->id,
            'datum' => $this->datum,
            'typ' => $this->typRoh,
            'rezeptName' => $this->rezeptName,
            'rezeptSlug' => $this->rezeptSlug,
            'rezeptId' => $this->rezeptId,
            'hatBild' => $this->hatBild,
            'titel' => $this->titel,
            'text' => $this->text,
        ];
    }

    public function hatRezept(): bool
    {
        return $this->rezeptName !== null && $this->rezeptSlug !== null;
    }

    /** Der Mahlzeitentyp auf Deutsch; unbekannte Typen stehen, wie sie kamen. */
    public function overline(): string
    {
        return $this->typ?->label() ?? $this->typRoh;
    }

    public function headline(): string
    {
        return $this->hatRezept() ? (string) $this->rezeptName : $this->titel;
    }

    public function supporting(): string
    {
        return $this->hatRezept() ? '' : $this->text;
    }

    public function bildUrl(): ?string
    {
        if (! $this->hatRezept() || ! $this->hatBild || $this->rezeptId === null) {
            return null;
        }

        return self::basisUrl().'/api/media/recipes/'.$this->rezeptId.'/images/min-original.webp';
    }

    public function rezeptUrl(): ?string
    {
        return $this->hatRezept() ? self::basisUrl().'/g/home/r/'.$this->rezeptSlug : null;
    }

    /** Sortierschlüssel innerhalb eines Tages; Unbekanntes ans Ende. */
    public function reihenfolge(): int
    {
        return $this->typ?->reihenfolge() ?? count(Mahlzeitentyp::cases());
    }

    private static function basisUrl(): string
    {
        return rtrim((string) config('mealie.url'), '/');
    }
}
