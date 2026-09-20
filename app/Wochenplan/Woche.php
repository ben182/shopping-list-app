<?php

namespace App\Wochenplan;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Eine Kalenderwoche von Montag bis Sonntag — die Einheit, in der der
 * Wochenplan gelesen und geblättert wird.
 *
 * Der Montag ist ausdrücklich gesetzt statt dem Gebietsschema überlassen:
 * Mealie bekommt `start_date` und `end_date`, und eine Woche, die je nach
 * Locale am Sonntag beginnt, holte die falschen sieben Tage.
 */
final readonly class Woche
{
    /** Wochentage ausgeschrieben, Index 0 = Montag (ISO). */
    private const WOCHENTAGE = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

    private function __construct(public CarbonImmutable $montag) {}

    public static function aktuelle(): self
    {
        return self::mit(CarbonImmutable::now(config('app.timezone')));
    }

    /** Die Woche, in der dieser Tag liegt. */
    public static function mit(CarbonImmutable $tag): self
    {
        return new self($tag->startOfWeek(CarbonInterface::MONDAY));
    }

    /** Aus dem Schlüssel, in dem der Screen die gewählte Woche hält. */
    public static function ausSchluessel(string $schluessel): self
    {
        return self::mit(CarbonImmutable::parse($schluessel, config('app.timezone')));
    }

    public function sonntag(): CarbonImmutable
    {
        return $this->montag->addDays(6);
    }

    public function vorherige(): self
    {
        return new self($this->montag->subWeek());
    }

    public function naechste(): self
    {
        return new self($this->montag->addWeek());
    }

    /** Der Montag als `Y-m-d` — zugleich Schlüssel der Woche im Zwischenspeicher. */
    public function schluessel(): string
    {
        return $this->montag->format('Y-m-d');
    }

    public function startDatum(): string
    {
        return $this->montag->format('Y-m-d');
    }

    public function endDatum(): string
    {
        return $this->sonntag()->format('Y-m-d');
    }

    /** „KW 39 · 21.09.–27.09.“ */
    public function text(): string
    {
        return sprintf(
            'KW %d · %s–%s',
            $this->montag->isoWeek(),
            $this->montag->format('d.m.'),
            $this->sonntag()->format('d.m.'),
        );
    }

    /**
     * Die sieben Tage der Woche, Montag zuerst.
     *
     * @return list<CarbonImmutable>
     */
    public function tage(): array
    {
        return array_map(fn (int $versatz) => $this->montag->addDays($versatz), range(0, 6));
    }

    /** „Montag, 21.09.“ */
    public static function tagesUeberschrift(CarbonImmutable $tag): string
    {
        return self::WOCHENTAGE[$tag->dayOfWeekIso - 1].', '.$tag->format('d.m.');
    }
}
