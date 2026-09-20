<?php

namespace App\Erscheinungsbild;

/**
 * Die Primärfarbe der App. Die Reihenfolge der Fälle ist die Reihenfolge der
 * Kreise in den Einstellungen; der erste Fall ist zugleich die Farbe vor der
 * ersten Wahl.
 *
 * Jeder Fall bringt vier Werte mit: die Primärfarbe für Hell und für Dunkel
 * und dazu die Textfarbe, die darauf noch lesbar ist. Die hellen Töne tragen
 * Weiß, die dunklen sind dafür zu hell und tragen deshalb die dunkle
 * Textfarbe des Themes — geprüft wird das in `ThemeTest` gegen die
 * WCAG-Formel, nicht gegen dieselbe Tabelle.
 */
enum Akzentfarbe: string
{
    case Indigo = 'indigo';
    case Blau = 'blau';
    case Gruen = 'gruen';
    case Orange = 'orange';
    case Rosa = 'rosa';
    case Violett = 'violett';

    /** Die dunkle Textfarbe, wo Weiß auf der Primärfarbe untergeht. */
    private const DUNKLE_SCHRIFT = '#0F172A';

    /** Wie die Farbe heißt — nur für den Screenreader, nicht auf dem Bild. */
    public function beschriftung(): string
    {
        return match ($this) {
            self::Indigo => 'Indigo',
            self::Blau => 'Blau',
            self::Gruen => 'Grün',
            self::Orange => 'Orange',
            self::Rosa => 'Rosa',
            self::Violett => 'Violett',
        };
    }

    /** Die Primärfarbe im hellen Erscheinungsbild. */
    public function hell(): string
    {
        return match ($this) {
            self::Indigo => '#4F46E5',
            self::Blau => '#2563EB',
            self::Gruen => '#15803D',
            self::Orange => '#C2410C',
            self::Rosa => '#DB2777',
            self::Violett => '#7C3AED',
        };
    }

    /** Die Primärfarbe im dunklen Erscheinungsbild. */
    public function dunkel(): string
    {
        return match ($this) {
            self::Indigo => '#818CF8',
            self::Blau => '#60A5FA',
            self::Gruen => '#4ADE80',
            self::Orange => '#FB923C',
            self::Rosa => '#F472B6',
            self::Violett => '#A78BFA',
        };
    }

    /** Was auf der hellen Primärfarbe steht. */
    public function aufHell(): string
    {
        return '#FFFFFF';
    }

    /** Was auf der dunklen Primärfarbe steht. */
    public function aufDunkel(): string
    {
        return self::DUNKLE_SCHRIFT;
    }

    /**
     * Die Theme-Tokens dieser Farbe — genau das, was `Theme::merge()` nimmt.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function tokens(): array
    {
        return [
            'light' => ['primary' => $this->hell(), 'on-primary' => $this->aufHell()],
            'dark' => ['primary' => $this->dunkel(), 'on-primary' => $this->aufDunkel()],
        ];
    }

    /**
     * Was der Screenreader zum Kreis sagt. Ob die Farbe die gewählte ist,
     * steht im Label selbst: der Kreis ist ein schlichtes Druckfeld, das von
     * sich aus keinen Auswahlzustand meldet.
     */
    public function a11yLabel(bool $gewaehlt): string
    {
        return 'Akzentfarbe '.$this->beschriftung().($gewaehlt ? ', ausgewählt' : '');
    }

    /**
     * Die Farbe zu einem gespeicherten Wert. Was die Tabelle nicht (mehr)
     * hergibt, ist Indigo — so sieht auch die erste Nutzung aus.
     */
    public static function ausWert(?string $wert): self
    {
        return $wert === null ? self::Indigo : (self::tryFrom($wert) ?? self::Indigo);
    }
}
