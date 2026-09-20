<?php

use App\Erscheinungsbild\Akzentfarbe;
use App\Erscheinungsbild\Auswahl;
use App\Erscheinungsbild\Farbwahl;
use App\Erscheinungsbild\Modus;
use App\Models\Einstellung;
use Ben182\AppLifecycle\Events\AppForegrounded;
use Native\Mobile\Testing\Native;
use Native\Mobile\UI\Theme;

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

/*
 * Ab hier: die Wirkung der Wahl. Der Seam bleibt der Wire-Tree bzw. die
 * Fake-Bridge — geprüft wird, was das Gerät zu sehen bekäme.
 */

it('schiebt „Dunkel“ sofort ans Gerät', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')
        ->changeTab('erscheinungsbild', 2)
        ->assertNativeCalled('Appearance.Set', fn (array $params) => $params['mode'] === 'dark');
});

it('schiebt „Hell“ sofort ans Gerät', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')
        ->changeTab('erscheinungsbild', 1)
        ->assertNativeCalled('Appearance.Set', fn (array $params) => $params['mode'] === 'light');
});

it('gibt die App mit „System“ wieder dem Systemthema zurück', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')
        ->changeTab('erscheinungsbild', 2)
        ->changeTab('erscheinungsbild', 0)
        ->assertNativeCalled('Appearance.Set', fn (array $params) => $params['mode'] === 'system');
});

it('wendet den gespeicherten Modus beim App-Start an', function () {
    fakeSecureStore();

    Einstellung::query()->create(['schluessel' => 'erscheinungsbild', 'wert' => 'dunkel']);

    // Der Start landet auf dem ersten Tab — noch bevor jemand die
    // Einstellungen öffnet, muss die App dunkel sein.
    Native::visit('/')
        ->assertNativeCalled('Appearance.Set', fn (array $params) => $params['mode'] === 'dark');
});

it('wendet den gespeicherten Modus bei der Rückkehr in den Vordergrund erneut an', function () {
    fakeSecureStore();

    Einstellung::query()->create(['schluessel' => 'erscheinungsbild', 'wert' => 'hell']);

    Native::visit('/vorrat')
        ->assertNativeCalledTimes('Appearance.Set', 1)
        ->emitNative(AppForegrounded::class)
        ->assertNativeCalledTimes('Appearance.Set', 2);
});

it('speichert die Wahl auch dann, wenn es die native Hälfte auf der Plattform nicht gibt', function () {
    fakeSecureStore()->withoutCapability('Appearance.Set');

    $screen = Native::visit('/einstellungen')
        ->changeTab('erscheinungsbild', 2)
        ->assertNativeNotCalled('Appearance.Set');

    expect(knotenMitRef($screen, 'erscheinungsbild')['props']['value'])->toBe(2)
        ->and(app(Auswahl::class)->aktuell())->toBe(Modus::Dunkel);
});

it('wendet den Modus auf jedem Screen an, nicht nur auf dem Startbildschirm', function (string $route) {
    fakeSecureStore();

    Einstellung::query()->create(['schluessel' => 'erscheinungsbild', 'wert' => 'dunkel']);

    Native::visit($route)
        ->assertNativeCalled('Appearance.Set', fn (array $params) => $params['mode'] === 'dark');
})->with([
    'Einkaufen' => '/',
    'Vorrat' => '/vorrat',
    'Wochenplan' => '/wochenplan',
    'Einstellungen' => '/einstellungen',
]);

/*
 * Ab hier: die Akzentfarbe. Derselbe Seam — die Kreise stehen im Wire-Tree
 * des Einstellungen-Screens, ihre Wirkung im Wire-Tree der anderen Screens.
 */

it('stellt sechs Akzentkreise in der vorgegebenen Reihenfolge unter den Modus-Umschalter', function () {
    fakeSecureStore();

    $screen = Native::visit('/einstellungen');
    $refs = array_column(knotenMitRefPraefix($screen, ''), 'ref');

    expect($refs)->toBe([
        'erscheinungsbild',
        'akzentfarbe-indigo',
        'akzentfarbe-blau',
        'akzentfarbe-gruen',
        'akzentfarbe-orange',
        'akzentfarbe-rosa',
        'akzentfarbe-violett',
        'mealie-token',
        'token-speichern',
        'verbindung-testen',
        'token-loeschen',
    ]);
});

it('zeigt das Häkchen nur im ausgewählten Kreis', function () {
    fakeSecureStore();

    $kreise = knotenMitRefPraefix(Native::visit('/einstellungen'), 'akzentfarbe-');

    $mitHaken = array_map(
        fn (array $kreis) => in_array('icon', knotenTypen($kreis), strict: true),
        $kreise,
    );

    expect($mitHaken)->toBe([true, false, false, false, false, false]);
});

it('benennt jeden Kreis für den Screenreader und sagt dazu, welcher gewählt ist', function () {
    fakeSecureStore();

    $kreise = knotenMitRefPraefix(Native::visit('/einstellungen'), 'akzentfarbe-');

    expect(array_map(fn (array $kreis) => $kreis['props']['a11y_label'], $kreise))->toBe([
        'Akzentfarbe Indigo, ausgewählt',
        'Akzentfarbe Blau',
        'Akzentfarbe Grün',
        'Akzentfarbe Orange',
        'Akzentfarbe Rosa',
        'Akzentfarbe Violett',
    ]);
});

it('trägt in jedem Kreis seine eigene Farbe, hell wie dunkel', function () {
    fakeSecureStore();

    $kreise = knotenMitRefPraefix(Native::visit('/einstellungen'), 'akzentfarbe-');

    $farben = array_map(
        fn (array $kreis) => [$kreis['style']['bg_color'], $kreis['props']['dark_bg_color']],
        $kreise,
    );

    // Die Werte stehen hier als Literale, damit der Test nicht dieselbe
    // Tabelle liest, die er prüfen soll.
    expect($farben)->toBe([
        ['#4F46E5', '#818CF8'],
        ['#2563EB', '#60A5FA'],
        ['#15803D', '#4ADE80'],
        ['#C2410C', '#FB923C'],
        ['#DB2777', '#F472B6'],
        ['#7C3AED', '#A78BFA'],
    ]);
});

it('hat bei der ersten Nutzung Indigo ausgewählt', function () {
    fakeSecureStore();

    expect(knotenMitRef(Native::visit('/einstellungen'), 'akzentfarbe-indigo')['props']['a11y_label'])
        ->toBe('Akzentfarbe Indigo, ausgewählt');
});

it('übernimmt eine angetippte Farbe sofort, ohne Speichern-Knopf', function () {
    fakeSecureStore();

    $screen = Native::visit('/einstellungen')->press('akzentfarbe-gruen');

    expect(knotenMitRef($screen, 'akzentfarbe-gruen')['props']['a11y_label'])
        ->toBe('Akzentfarbe Grün, ausgewählt')
        ->and(knotenMitRef($screen, 'akzentfarbe-indigo')['props']['a11y_label'])
        ->toBe('Akzentfarbe Indigo');
});

it('zeigt die zuletzt gewählte Farbe beim nächsten Öffnen der App wieder', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')->press('akzentfarbe-rosa');

    // Ein zweiter Besuch mountet den Screen frisch — dasselbe, was ein
    // Neustart täte.
    expect(knotenMitRef(Native::visit('/einstellungen'), 'akzentfarbe-rosa')['props']['a11y_label'])
        ->toBe('Akzentfarbe Rosa, ausgewählt');
});

it('legt die Farbe in die lokale Datenbank statt in den Secure Storage', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')
        ->press('akzentfarbe-violett')
        ->assertNativeNotCalled('SecureStorage.Set');

    expect(app(Farbwahl::class)->aktuell())->toBe(Akzentfarbe::Violett);
});

it('fällt auf Indigo zurück, wenn gespeichert steht, was die App nicht kennt', function () {
    fakeSecureStore();

    Einstellung::query()->create(['schluessel' => 'akzentfarbe', 'wert' => 'neonpink']);

    expect(knotenMitRef(Native::visit('/einstellungen'), 'akzentfarbe-indigo')['props']['a11y_label'])
        ->toBe('Akzentfarbe Indigo, ausgewählt');
});

it('schiebt die gewählte Farbe als Theme ans Gerät — dort hängen Tab-Leiste, Knöpfe und Checkboxen dran', function (string $route) {
    fakeSecureStore();

    Native::visit('/einstellungen')->press('akzentfarbe-gruen');

    // Ein neuer Screen darf die Farbe nicht wieder auf die Konfiguration
    // zurückfallen lassen.
    Native::visit($route);

    // Literale statt der Enum-Werte: sonst prüfte der Test die Tabelle
    // gegen sich selbst.
    expect(Theme::get('light.primary'))->toBe('#15803D')
        ->and(Theme::get('dark.primary'))->toBe('#4ADE80');
})->with([
    'Einkaufen' => '/',
    'Vorrat' => '/vorrat',
    'Wochenplan' => '/wochenplan',
    'Einstellungen' => '/einstellungen',
]);
