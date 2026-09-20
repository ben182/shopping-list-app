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
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;

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
     * Rechts in der Top-Bar: „Alles abhaken“, solange es etwas abzuhaken gibt,
     * und das Zahnrad — der einzige Weg zu den Einstellungen, der von überall
     * erreichbar ist.
     */
    public function navigationOptions(): ?NavBarOptions
    {
        $optionen = NavBarOptions::make();

        if ($this->liste()->anzahl() > 0) {
            $optionen->action(
                NavAction::make('alles-abhaken')
                    ->icon(ios: Ios::CheckmarkCircle, android: Android::DoneAll)
                    ->a11yLabel('Alles abhaken')
                    ->press('alleAbhakenBestaetigen')
            );
        }

        return $optionen->action(
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

    /**
     * Zwei Taps bis zur leeren Liste: Der Dialog fragt nach, und erst sein
     * „Abhaken“ räumt ab. Jeder andere Ausgang — „Abbrechen“, Wegtippen,
     * Zurück-Geste — lässt die Liste stehen, weil dann entweder kein Event
     * kommt oder eines mit einem anderen Label.
     */
    public function alleAbhakenBestaetigen(): void
    {
        $anzahl = $this->liste()->anzahl();

        if ($anzahl === 0) {
            return;
        }

        Dialog::alert(
            'Alles abhaken?',
            $anzahl === 1
                ? '1 Artikel wandert zurück in den Vorrat.'
                : $anzahl.' Artikel wandern zurück in den Vorrat.',
            [
                ['label' => 'Abbrechen', 'style' => 'cancel'],
                ['label' => 'Abhaken', 'style' => 'default'],
            ]
        )->buttonPressed(function (ButtonPressed $event): void {
            if ($event->label !== 'Abhaken') {
                return;
            }

            $this->liste()->alleEntfernen();

            unset($this->gruppen);
        });
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
