<?php

namespace App\Layouts;

use App\Icons\Android;
use App\Icons\Ios;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/**
 * Rahmen der drei Root-Screens: Titelleiste oben, Tab-Leiste unten.
 */
class TabsLayout extends NativeLayout
{
    public function navBar(NativeComponent $screen): ?NavBar
    {
        return NavBar::make()
            ->title($screen->navTitle())
            ->subtitle(method_exists($screen, 'navSubtitle') ? $screen->navSubtitle() : null)
            ->displayMode('large')
            ->scrollBehavior('collapse');
    }

    public function tabBar(NativeComponent $screen): ?TabBar
    {
        return TabBar::make()
            ->add(Tab::link('Einkaufen', '/', ios: Ios::Cart, android: Android::ShoppingCart))
            ->add(Tab::link('Vorrat', '/vorrat', ios: Ios::Archivebox, android: Android::Inventory2))
            ->add(Tab::link('Wochenplan', '/wochenplan', ios: Ios::Calendar, android: Android::CalendarMonth))
            ->highlight('/'.ltrim(request()->path(), '/'));
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
