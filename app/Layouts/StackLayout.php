<?php

namespace App\Layouts;

use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/**
 * Rahmen gepushter Screens: Titel inline plus Zurück-Button, keine Tab-Leiste,
 * damit der gepushte Screen die volle Höhe bekommt.
 */
class StackLayout extends NativeLayout
{
    public function navBar(NativeComponent $screen): ?NavBar
    {
        return NavBar::make()
            ->title($screen->navTitle())
            ->subtitle(method_exists($screen, 'navSubtitle') ? $screen->navSubtitle() : null)
            ->displayMode('inline')
            ->back();
    }

    /**
     * Native chrome: NavHost/Scaffold auf Android, NavigationStack/TabView auf
     * iOS. Bringt Edge-Swipe-Back, Predictive Back und Material-3-Bars mit,
     * statt sie als Column nachzubauen.
     */
    public function usesNativeChrome(): bool
    {
        return true;
    }
}
