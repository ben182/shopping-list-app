<?php

use Native\Mobile\Testing\Native;
use Native\Mobile\UI\Theme;

it('benutzt Indigo als Primärfarbe in beiden Erscheinungsbildern', function () {
    // Werte aus der PRD, nicht aus der Konfiguration abgeleitet.
    expect(Theme::get('light.primary'))->toBe('#4F46E5')
        ->and(Theme::get('dark.primary'))->toBe('#818CF8');
});

it('schickt zu jeder Farbe eine davon verschiedene Dark-Mode-Entsprechung mit', function (string $uri) {
    $paare = farbPaare(Native::visit($uri, platform: 'android')->tree());

    expect($paare)->not->toBeEmpty();

    foreach ($paare as $paar) {
        expect($paar['dunkel'])
            ->not->toBeNull("Farbe {$paar['hell']} hat keine Dark-Mode-Entsprechung — feste Farbe statt Theme-Klasse?")
            ->not->toBe($paar['hell']);
    }
})->with(['/', '/vorrat', '/wochenplan', '/einstellungen']);
