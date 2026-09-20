<?php

namespace App\Release;

use RuntimeException;

/**
 * Lesender und schreibender Zugriff auf einzelne Zeilen der `.env`.
 *
 * Version und Version-Code stehen nur dort — die `.env` ist gitignored, also
 * gibt es im Repo keine zweite Stelle, die sie kennt. Der Git-Tag ist die
 * Aufzeichnung dessen, was veröffentlicht wurde.
 */
final class EnvDatei
{
    public function __construct(private readonly string $pfad) {}

    public function lesen(string $schluessel): ?string
    {
        if (preg_match($this->muster($schluessel), $this->inhalt(), $treffer) !== 1) {
            return null;
        }

        $wert = trim($treffer[1], " \t\"'");

        return $wert === '' ? null : $wert;
    }

    /**
     * Setzt Werte, ohne den Rest der Datei anzufassen: vorhandene Schlüssel
     * werden ersetzt, fehlende ans Ende gehängt.
     *
     * @param  array<string, string|int>  $werte
     */
    public function schreiben(array $werte): void
    {
        $inhalt = $this->inhalt();

        foreach ($werte as $schluessel => $wert) {
            $zeile = $schluessel.'='.$wert;

            $inhalt = preg_match($this->muster($schluessel), $inhalt) === 1
                ? preg_replace($this->muster($schluessel), $zeile, $inhalt, 1)
                : rtrim($inhalt, "\n")."\n".$zeile."\n";
        }

        file_put_contents($this->pfad, $inhalt);
    }

    /**
     * Die Namen aller gesetzten Schlüssel.
     *
     * @return list<string>
     */
    public function schluessel(): array
    {
        preg_match_all('/^([A-Z0-9_]+)=/m', $this->inhalt(), $treffer);

        return array_values(array_unique($treffer[1]));
    }

    private function inhalt(): string
    {
        $inhalt = @file_get_contents($this->pfad);

        if ($inhalt === false) {
            throw new RuntimeException("Keine .env unter {$this->pfad}.");
        }

        return $inhalt;
    }

    private function muster(string $schluessel): string
    {
        return '/^'.preg_quote($schluessel, '/').'=(.*)$/m';
    }
}
