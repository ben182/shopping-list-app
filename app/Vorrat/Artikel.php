<?php

namespace App\Vorrat;

use App\Katalog\Laden;

/**
 * Ein Artikel der Mealie-Vorratsliste — eine Zeile des Vorrat-Screens.
 *
 * Die Vorratsliste ist eine zweite Mealie-Einkaufsliste, die nie eingekauft
 * wird: sie sagt, was man im Haus haben will. Ein Tap kopiert den Artikel in
 * die richtige Einkaufsliste; hier bleibt er stehen.
 *
 * `$roh` trägt Mealies ganze Darstellung mit — sie ist die Vorlage für die
 * Kopie und enthält Felder, die die App selbst nicht anfasst.
 */
final readonly class Artikel
{
    /**
     * @param  list<Laden>  $laeden  Was an diesem Artikel steht — leer heißt
     *                               „erbt von seiner Warengruppe“.
     * @param  array<string, mixed>  $roh
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $label,
        public ?string $lebensmittelId,
        public array $laeden = [],
        public array $roh = [],
    ) {}

    /**
     * @param  array{id?: string, name?: string, label?: ?string, lebensmittelId?: ?string, laeden?: mixed, roh?: array<string, mixed>}  $daten
     */
    public static function ausDaten(array $daten): self
    {
        return new self(
            id: (string) ($daten['id'] ?? ''),
            name: (string) ($daten['name'] ?? ''),
            label: $daten['label'] ?? null,
            lebensmittelId: $daten['lebensmittelId'] ?? null,
            laeden: Laden::ausSchluesseln(is_array($daten['laeden'] ?? null) ? $daten['laeden'] : []),
            roh: $daten['roh'] ?? [],
        );
    }

    /**
     * Zurück in die flache Form, in der der Cache den Vorrat hält.
     *
     * @return array{id: string, name: string, label: ?string, lebensmittelId: ?string, laeden: list<string>, roh: array<string, mixed>}
     */
    public function daten(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'lebensmittelId' => $this->lebensmittelId,
            'laeden' => array_map(fn (Laden $laden) => $laden->value, $this->laeden),
            'roh' => $this->roh,
        ];
    }
}
