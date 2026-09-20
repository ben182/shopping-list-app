<?php

namespace App\Mealie;

use Carbon\CarbonImmutable;

/**
 * Was das Banner unter der Top-Bar sagt, wenn Mealie nicht mitspielt: ein
 * Satz und ein Knopf.
 */
final readonly class Fehlerzustand
{
    public function __construct(
        public Fehler $fehler,
        public ?CarbonImmutable $stand,
    ) {}

    public function tokenUngueltig(): bool
    {
        return $this->fehler === Fehler::Token;
    }

    /**
     * Ohne Stand — also ohne je erfolgreich geladene Liste — fehlt der
     * Zusatz ganz, statt einen Zeitpunkt zu erfinden.
     */
    public function text(): string
    {
        if ($this->tokenUngueltig()) {
            return 'Mealie-Token ungültig';
        }

        if ($this->stand === null) {
            return 'Mealie nicht erreichbar';
        }

        return 'Mealie nicht erreichbar · Stand '.$this->standText($this->stand);
    }

    public function aktion(): string
    {
        return $this->tokenUngueltig() ? 'Einstellungen' : 'Erneut versuchen';
    }

    /**
     * Am selben Tag reicht die Uhrzeit; alles Ältere braucht das Datum davor,
     * sonst liest sich ein Stand von gestern wie einer von heute.
     */
    private function standText(CarbonImmutable $stand): string
    {
        $stand = $stand->timezone(config('app.timezone'));

        return $stand->isSameDay(CarbonImmutable::now(config('app.timezone')))
            ? $stand->format('H:i')
            : $stand->format('d.m. H:i');
    }
}
