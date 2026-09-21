<?php

namespace App\Mealie;

use App\Katalog\Laden;

/**
 * Ein Artikel der Mealie-Einkaufsliste, auf das reduziert, was die App von
 * ihm zeigt und braucht. Mealies Nutzlast pro Artikel ist gut zwei Bildschirme
 * lang; alles Weitere gehört nicht in den Screen.
 *
 * Nur `$roh` trägt sie vollständig mit — Mealie will beim Abhaken den ganzen
 * Artikel zurück, und was die App nicht kennt, soll dabei unverändert bleiben.
 */
final readonly class Eintrag
{
    /**
     * @param  list<string>  $rezepte
     * @param  list<Laden>  $laeden  Was an diesem Artikel steht — leer heißt
     *                               „erbt von seiner Warengruppe“.
     * @param  array<string, mixed>  $roh
     */
    public function __construct(
        public string $id,
        public string $text,
        public ?string $notiz,
        public ?string $label,
        public array $rezepte,
        public bool $abgehakt,
        public array $roh = [],
        public array $laeden = [],
    ) {}

    /**
     * Ein älterer Cache kann die Rezepte noch als Zeichenkette halten; die
     * fällt dann weg und steht nach dem nächsten Laden wieder da.
     *
     * @param  array{id?: string, text?: string, notiz?: ?string, label?: ?string, rezepte?: mixed, abgehakt?: bool, roh?: array<string, mixed>, laeden?: mixed}  $daten
     */
    public static function ausDaten(array $daten): self
    {
        return new self(
            id: (string) ($daten['id'] ?? ''),
            text: (string) ($daten['text'] ?? ''),
            notiz: $daten['notiz'] ?? null,
            label: $daten['label'] ?? null,
            rezepte: is_array($daten['rezepte'] ?? null) ? array_values($daten['rezepte']) : [],
            abgehakt: (bool) ($daten['abgehakt'] ?? false),
            roh: $daten['roh'] ?? [],
            laeden: Laden::ausSchluesseln(is_array($daten['laeden'] ?? null) ? $daten['laeden'] : []),
        );
    }

    /**
     * Zurück in die flache Form, in der der Cache die Liste hält — genau
     * die, die `ausDaten()` wieder einliest.
     *
     * @return array{id: string, text: string, notiz: ?string, label: ?string, rezepte: list<string>, abgehakt: bool, roh: array<string, mixed>, laeden: list<string>}
     */
    public function daten(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'notiz' => $this->notiz,
            'label' => $this->label,
            'rezepte' => $this->rezepte,
            'abgehakt' => $this->abgehakt,
            'roh' => $this->roh,
            'laeden' => array_map(fn (Laden $laden) => $laden->value, $this->laeden),
        ];
    }

    /** Derselbe Artikel mit umgelegtem Haken. */
    public function mitHaken(bool $abgehakt): self
    {
        return new self($this->id, $this->text, $this->notiz, $this->label, $this->rezepte, $abgehakt, $this->roh, $this->laeden);
    }
}
