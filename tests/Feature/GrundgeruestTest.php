<?php

use Native\Mobile\Testing\Native;

it('startet mit genau drei Tabs in fester Reihenfolge, Einkaufen aktiv', function () {
    $screen = Native::visit('/');

    expect(tabLabels($screen))->toBe(['Einkaufen', 'Vorrat', 'Wochenplan']);

    $screen->assertHasTabBar()
        ->assertTabActive('Einkaufen');
});

it('zeigt auf jedem Tab-Screen den Titel in der Top-Bar', function (string $uri, string $titel) {
    Native::visit($uri)
        ->assertNavTitle($titel)
        ->assertHasTabBar()
        ->assertTabActive($titel);
})->with([
    'Einkaufen' => ['/', 'Einkaufen'],
    'Vorrat' => ['/vorrat', 'Vorrat'],
    'Wochenplan' => ['/wochenplan', 'Wochenplan'],
]);

it('öffnet die Einstellungen über die Zahnrad-Action des Einkaufen-Screens', function () {
    Native::visit('/')
        ->press('oeffneEinstellungen')
        ->assertNavigatedTo('/einstellungen');
});

it('zeigt die Einstellungen als gepushten Screen mit Zurück-Navigation und ohne Tab-Leiste', function () {
    Native::visit('/einstellungen')
        ->assertNavTitle('Einstellungen')
        ->assertTabBarHidden()
        ->assertElement('native_root_stack', fn (array $node) => ($node['props']['back'] ?? false) === true);
});

it('beschriftet jedes Icon für Screenreader', function (string $uri) {
    Native::visit($uri)->assertAccessible();
})->with(['/', '/vorrat', '/wochenplan', '/einstellungen']);
