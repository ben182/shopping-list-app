<?php

namespace App\NativeComponents;

use App\Icons\Android;
use App\Icons\Ios;
use App\Katalog\Gruppe;
use App\Katalog\Katalog;
use App\Liste\EigeneListe;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBarOptions;
use Native\Mobile\Edge\NativeComponent;

class Einkaufen extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Einkaufen';
    }

    /**
     * Die Anzahl der offenen Artikel auf diesem Screen. Solange es keine
     * Mealie-Anbindung gibt, sind das nur die eigenen.
     */
    public function navSubtitle(): ?string
    {
        $anzahl = $this->liste()->anzahl();

        return $anzahl === 0 ? null : $anzahl.' Artikel';
    }

    /**
     * Der einzige Weg zu den Einstellungen, der von überall erreichbar ist:
     * das Zahnrad rechts in der Top-Bar des Start-Screens.
     */
    public function navigationOptions(): ?NavBarOptions
    {
        return NavBarOptions::make()
            ->action(
                NavAction::make('einstellungen')
                    ->icon(ios: Ios::Gearshape, android: Android::Settings)
                    ->a11yLabel('Einstellungen')
                    ->press('oeffneEinstellungen')
            );
    }

    /**
     * Was auf der Liste steht — gruppiert und in Katalogreihenfolge, also
     * genau wie im Vorrat.
     *
     * @return list<Gruppe>
     */
    #[Computed]
    public function gruppen(): array
    {
        return app(Katalog::class)->gruppiert($this->liste()->artikelIds());
    }

    public function abhaken(string $artikelId): void
    {
        $this->liste()->entfernen($artikelId);

        unset($this->gruppen);
    }

    public function oeffneEinstellungen(): void
    {
        $this->navigate('/einstellungen');
    }

    public function render(): Element
    {
        return $this->view('einkaufen');
    }

    private function liste(): EigeneListe
    {
        return app(EigeneListe::class);
    }
}
