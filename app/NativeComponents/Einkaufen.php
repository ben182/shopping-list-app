<?php

namespace App\NativeComponents;

use App\Icons\Android;
use App\Icons\Ios;
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

    public function oeffneEinstellungen(): void
    {
        $this->navigate('/einstellungen');
    }

    public function render(): Element
    {
        return $this->view('einkaufen');
    }
}
