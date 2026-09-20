<?php

namespace App\Release;

use InvalidArgumentException;
use Stringable;

/**
 * Eine Versionsnummer nach `major.minor.patch` — der `versionName` der App und
 * zugleich der Git-Tag (dort mit `v` davor).
 *
 * Beides muss übereinstimmen: Obtainium liest die Version aus dem Tag-Namen und
 * vergleicht sie mit dem, was auf dem Gerät installiert ist. Weichen Tag und
 * `versionName` voneinander ab, bietet Obtainium ein Update an, das Android
 * anschließend als bereits installiert ablehnt.
 */
final class Version implements Stringable
{
    public function __construct(
        public readonly int $major,
        public readonly int $minor,
        public readonly int $patch,
    ) {}

    /** Akzeptiert `1.2.3` und `v1.2.3`. */
    public static function ausString(string $wert): self
    {
        if (preg_match('/^v?(\d+)\.(\d+)\.(\d+)$/', trim($wert), $treffer) !== 1) {
            throw new InvalidArgumentException("Keine gültige Version: {$wert}");
        }

        return new self((int) $treffer[1], (int) $treffer[2], (int) $treffer[3]);
    }

    public function erhoehen(Stufe $stufe): self
    {
        return match ($stufe) {
            Stufe::Major => new self($this->major + 1, 0, 0),
            Stufe::Minor => new self($this->major, $this->minor + 1, 0),
            Stufe::Patch => new self($this->major, $this->minor, $this->patch + 1),
        };
    }

    public function tag(): string
    {
        return 'v'.$this;
    }

    public function __toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->patch}";
    }
}
