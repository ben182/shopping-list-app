<?php

namespace App\Katalog;

/**
 * Die Warengruppen der App, gelesen aus `config/katalog.php`. Sie sind zur
 * Laufzeit unveränderlich und bestimmen überall die Reihenfolge der
 * Überschriften — den Weg durch den Laden.
 *
 * Was in den Gruppen steht, kommt aus Mealie; der Katalog kennt nur ihre
 * Namen und die Läden, die seine Artikel von ihnen erben.
 */
final class Katalog
{
    /** @var list<Gruppe>|null */
    private ?array $gruppen = null;

    /**
     * Alle Warengruppen in Anzeigereihenfolge.
     *
     * @return list<Gruppe>
     */
    public function gruppen(): array
    {
        return $this->gruppen ??= array_values(array_map(
            fn (string $gruppeId, array $gruppe) => new Gruppe(
                id: $gruppeId,
                name: $gruppe['name'],
                laeden: Laden::ausSchluesseln($gruppe['laeden'] ?? []),
            ),
            array_keys($katalog = config('katalog.gruppen')),
            $katalog,
        ));
    }

    /**
     * Die Gruppe mit diesem Anzeigenamen — `null` für eine Überschrift, die
     * nur aus einem Mealie-Label entstanden ist.
     */
    public function gruppeMitNamen(string $name): ?Gruppe
    {
        foreach ($this->gruppen() as $gruppe) {
            if ($gruppe->name === $name) {
                return $gruppe;
            }
        }

        return null;
    }

    /**
     * Die übergebenen Gruppennamen in Anzeigereihenfolge: erst die
     * Warengruppen des Katalogs in seiner Reihenfolge, danach die
     * Überschriften, die nur aus einem Mealie-Label entstanden sind,
     * alphabetisch.
     *
     * @param  list<string>  $namen
     * @return list<string>
     */
    public function reihenfolge(array $namen): array
    {
        $katalogNamen = array_map(fn (Gruppe $gruppe) => $gruppe->name, $this->gruppen());

        $ausKatalog = array_values(array_filter($katalogNamen, fn (string $name) => in_array($name, $namen, strict: true)));
        $zusaetzlich = array_values(array_diff($namen, $katalogNamen));

        usort($zusaetzlich, fn (string $a, string $b) => strcasecmp(self::sortierbar($a), self::sortierbar($b)));

        return [...$ausKatalog, ...$zusaetzlich];
    }

    /**
     * Umlaute sortieren wie ihre Grundbuchstaben — „Öl“ gehört zwischen
     * „Obst“ und „Pasta“, nicht hinter „Zucker“, wo ein reiner Byte-Vergleich
     * es ablegen würde. Ein `Collator` wäre genauer, steht aber in der
     * PHP-Runtime des Geräts nicht sicher zur Verfügung.
     */
    private static function sortierbar(string $name): string
    {
        return str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['a', 'o', 'u', 'A', 'O', 'U', 'ss'],
            $name,
        );
    }
}
