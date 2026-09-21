<?php

namespace App\NativeComponents;

use App\Icons\Android;
use App\Icons\Ios;
use App\Katalog\Gruppe;
use App\Katalog\Katalog;
use App\Katalog\Laden;
use App\Katalog\Ladenfilter;
use App\Liste\EigeneListe;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\Element;
use Native\Mobile\Icon\IconResolver;

class Vorrat extends Screen
{
    /**
     * Der Suchtext, wie er im Feld steht — ungetrimmt, damit der Löschen-Button
     * auch bei reinen Leerzeichen erscheint. Als Komponenten-Zustand ist er
     * beim nächsten Öffnen des Tabs wieder leer.
     */
    public string $suche = '';

    public function navTitle(): string
    {
        return 'Vorrat';
    }

    public function navSubtitle(): ?string
    {
        $anzahl = $this->liste()->anzahl();

        return $anzahl === 0
            ? 'Tippe auf einen Artikel zum Hinzufügen'
            : $anzahl.' auf der Liste';
    }

    /**
     * Alles, was nicht auf der Liste steht, zur Suche passt und es im
     * gewählten Laden gibt — gruppiert und in Katalogreihenfolge.
     *
     * @return list<Gruppe>
     */
    #[Computed]
    public function gruppen(): array
    {
        $katalog = app(Katalog::class);

        return $katalog->gruppiert($katalog->imLaden(
            $katalog->gefiltert($this->liste()->vorratIds(), $this->suche),
            $this->gewaehlterLaden(),
        ));
    }

    /**
     * Die Läden als Chips über der Liste — dieselben wie beim Einkaufen,
     * und dieselbe Wahl: wer bei Lidl steht, will auch beim Nachlegen nur
     * sehen, was es dort gibt.
     *
     * @return list<Laden>
     */
    public function laeden(): array
    {
        return Laden::alle();
    }

    /** Welcher Chip aktiv ist — `null` heißt „Alle“. */
    public function gewaehlterLaden(): ?Laden
    {
        return app(Ladenfilter::class)->laden();
    }

    /**
     * Ein Tap auf einen Chip. Der Chip „Alle“ schickt einen leeren
     * Schlüssel; ein zweiter Tap auf den aktiven Chip führt zurück auf
     * „Alle“, damit man nicht erst zurückzielen muss.
     */
    public function ladenWaehlen(string $schluessel): void
    {
        $filter = app(Ladenfilter::class);

        $filter->setzen($filter->laden()?->value === $schluessel ? '' : $schluessel);

        unset($this->gruppen);
    }

    /**
     * Steht überhaupt etwas im Vorrat, ungeachtet von Suche und Laden? Die
     * View entscheidet daran, ob die Chips etwas zu filtern haben.
     */
    public function hatVorrat(): bool
    {
        return $this->liste()->vorratIds() !== [];
    }

    public function suchen(string $eingabe): void
    {
        $this->suche = $eingabe;

        unset($this->gruppen);
    }

    public function sucheLeeren(): void
    {
        $this->suche = '';

        unset($this->gruppen);
    }

    public function aufDieListe(string $artikelId): void
    {
        $this->liste()->hinzufuegen($artikelId);

        unset($this->gruppen);
    }

    public function render(): Element
    {
        return $this->view('vorrat', [
            'lupenIcon' => $this->iconName(Ios::Magnifyingglass, Android::Search),
            'leerenIcon' => $this->iconName(Ios::Xmark, Android::Close),
        ]);
    }

    private function liste(): EigeneListe
    {
        return app(EigeneListe::class);
    }

    /**
     * `leading-icon` und `icon` nehmen in Blade nur einen einzelnen Namen,
     * keine `ios:`/`android:`-Paare wie `native:icon`. Die Auswahl muss
     * deshalb hier passieren; ohne bekannte Plattform (Tests) gilt Android.
     */
    private function iconName(Ios $ios, Android $android): string
    {
        return IconResolver::resolve(null, $ios, $android)['icon'] ?? $android->value;
    }
}
