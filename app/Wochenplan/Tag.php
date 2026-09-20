<?php

namespace App\Wochenplan;

use Carbon\CarbonImmutable;

/**
 * Ein Tag der Woche mit dem, was für ihn geplant ist.
 */
final readonly class Tag
{
    /**
     * @param  list<Eintrag>  $eintraege
     */
    public function __construct(
        public CarbonImmutable $datum,
        public bool $istHeute,
        public array $eintraege,
    ) {}

    /** „Montag, 21.09.“ — heute mit dem Zusatz, der ihn hervorhebt. */
    public function ueberschrift(): string
    {
        $ueberschrift = Woche::tagesUeberschrift($this->datum);

        return $this->istHeute ? $ueberschrift.' · Heute' : $ueberschrift;
    }
}
