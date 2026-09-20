<?php

namespace App\Mealie;

/**
 * Ein Artikel der Mealie-Einkaufsliste, auf das reduziert, was die App von
 * ihm zeigt und braucht. Mealies Nutzlast pro Artikel ist gut zwei Bildschirme
 * lang; alles Weitere gehört nicht in den Screen.
 */
final readonly class Eintrag
{
    public function __construct(
        public string $id,
        public string $text,
        public ?string $label,
        public ?string $rezepte,
        public bool $abgehakt,
    ) {}

    /**
     * @param  array{id?: string, text?: string, label?: ?string, rezepte?: ?string, abgehakt?: bool}  $daten
     */
    public static function ausDaten(array $daten): self
    {
        return new self(
            id: (string) ($daten['id'] ?? ''),
            text: (string) ($daten['text'] ?? ''),
            label: $daten['label'] ?? null,
            rezepte: $daten['rezepte'] ?? null,
            abgehakt: (bool) ($daten['abgehakt'] ?? false),
        );
    }
}
