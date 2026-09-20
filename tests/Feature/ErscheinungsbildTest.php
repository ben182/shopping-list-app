<?php

use App\Erscheinungsbild\Auswahl;
use App\Erscheinungsbild\Modus;
use App\Models\Einstellung;
use Native\Mobile\Testing\Native;

/*
 * Der Seam ist derselbe wie beim Rest des Einstellungen-Screens: der Screen
 * wird über die Route besucht, geprüft wird der Wire-Tree, den das Gerät
 * bekäme.
 */

it('bietet „System“, „Hell“ und „Dunkel“ als Segmented Control an', function () {
    fakeSecureStore();

    $control = knotenMitRef(Native::visit('/einstellungen'), 'erscheinungsbild');

    expect($control['type'])->toBe('button_group')
        ->and($control['props']['options'])->toBe(['System', 'Hell', 'Dunkel'])
        ->and($control['props']['a11y_label'])->toBe('Erscheinungsbild');
});

it('hat bei der ersten Nutzung „System“ ausgewählt', function () {
    fakeSecureStore();

    expect(knotenMitRef(Native::visit('/einstellungen'), 'erscheinungsbild')['props']['value'])->toBe(0);
});

it('stellt den Abschnitt über den Mealie-Abschnitt', function () {
    fakeSecureStore();

    $texte = sichtbarerText(Native::visit('/einstellungen'));

    expect(array_search('Erscheinungsbild', $texte, strict: true))
        ->toBeLessThan(array_search('Mealie-Server', $texte, strict: true));
});

it('übernimmt eine angetippte Option sofort, ohne Speichern-Knopf', function () {
    fakeSecureStore();

    $screen = Native::visit('/einstellungen')->changeTab('erscheinungsbild', 2);

    expect(knotenMitRef($screen, 'erscheinungsbild')['props']['value'])->toBe(2);
});

it('zeigt die zuletzt gewählte Option beim nächsten Öffnen der App wieder', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')->changeTab('erscheinungsbild', 1);

    // Ein zweiter Besuch mountet den Screen frisch — dasselbe, was ein
    // Neustart täte. Übersteht die Wahl das, liegt sie nicht im Screen.
    expect(knotenMitRef(Native::visit('/einstellungen'), 'erscheinungsbild')['props']['value'])->toBe(1);
});

it('legt die Wahl in die lokale Datenbank statt in den Secure Storage', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')
        ->changeTab('erscheinungsbild', 2)
        ->assertNativeNotCalled('SecureStorage.Set');

    expect(app(Auswahl::class)->aktuell())->toBe(Modus::Dunkel);
});

it('bleibt bei „System“, wenn das Gerät eine Position schickt, die es nicht gibt', function () {
    fakeSecureStore();

    $screen = Native::visit('/einstellungen')->changeTab('erscheinungsbild', 99);

    expect(knotenMitRef($screen, 'erscheinungsbild')['props']['value'])->toBe(0);
});

it('fällt auf „System“ zurück, wenn gespeichert steht, was die App nicht kennt', function () {
    fakeSecureStore();

    // So sähe die Tabelle aus, wenn eine ältere Version einen Modus kannte,
    // den es heute nicht mehr gibt.
    Einstellung::query()->create(['schluessel' => 'erscheinungsbild', 'wert' => 'neonpink']);

    expect(knotenMitRef(Native::visit('/einstellungen'), 'erscheinungsbild')['props']['value'])->toBe(0);
});

it('lässt den Mealie-Abschnitt unangetastet', function () {
    fakeSecureStore('mealie-geheim-123');

    $screen = Native::visit('/einstellungen')->changeTab('erscheinungsbild', 2);

    expect(sichtbarerText($screen))->toBe([
        'Erscheinungsbild',
        'Mealie-Server',
        'https://mealie.example.test',
        'API-Token',
        'Speichern',
        'Token hinterlegt',
        'Verbindung testen',
        'Token löschen',
    ]);
});

it('bleibt mit dem neuen Abschnitt für den Screenreader bedienbar', function () {
    fakeSecureStore('mealie-geheim-123');

    Native::visit('/einstellungen')->assertAccessible();
});
