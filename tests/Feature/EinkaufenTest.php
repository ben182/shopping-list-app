<?php

use App\Liste\EigeneListe;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

/*
 * Die Erwartungswerte stammen aus Anhang A der PRD, nicht aus der
 * Konfiguration — sonst prüfte der Test die Konfiguration gegen sich selbst.
 */

/**
 * Setzt Artikel über dieselbe Fachklasse auf die Liste, die der Vorrat
 * benutzt — die Vorbedingung des Tests, nicht sein Prüfgegenstand.
 */
function aufDieListe(string ...$artikelIds): void
{
    foreach ($artikelIds as $artikelId) {
        app(EigeneListe::class)->hinzufuegen($artikelId);
    }
}

it('gruppiert die Artikel auf der Liste in Katalogreihenfolge und blendet leere Gruppen aus', function () {
    aufDieListe('salz', 'aepfel', 'bananen', 'brot', 'wasser-still');

    $abschnitte = listenAbschnitte(Native::visit('/'));

    expect($abschnitte)->toBe([
        ['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['Äpfel', 'Bananen']],
        ['ueberschrift' => 'Brot & Backwaren', 'artikel' => ['Brot']],
        ['ueberschrift' => 'Lebensmittel', 'artikel' => ['Salz']],
        ['ueberschrift' => 'Getränke', 'artikel' => ['Wasser (still)']],
    ]);
});

it('zeigt jeden Artikel als Zeile mit leerer Checkbox vorn', function () {
    aufDieListe('tofu');

    Native::visit('/')
        ->assertElement('list_item', fn (array $node) => ($node['props']['headline'] ?? null) === 'Tofu'
            && ($node['props']['leading_type'] ?? null) === 'checkbox'
            && ($node['props']['leading_checked'] ?? null) === false
            && ($node['on_press'] ?? null) !== null);
});

it('schickt einen angetippten Artikel sofort zurück in den Vorrat', function () {
    aufDieListe('tofu', 'hummus');

    $screen = Native::visit('/')->tap('Tofu');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Kühlregal', 'artikel' => ['Hummus']]]);

    $vorrat = collect(listenAbschnitte(Native::visit('/vorrat')))
        ->firstWhere('ueberschrift', 'Kühlregal')['artikel'];

    expect($vorrat)->toContain('Tofu')->not->toContain('Hummus');
});

it('zeigt einen im Vorrat hinzugefügten Artikel beim Tab-Wechsel an seiner Katalogposition', function () {
    aufDieListe('bananen', 'kartoffeln');

    // Derselbe Weg wie auf dem Gerät: im Vorrat antippen, dann unten auf den
    // Einkaufen-Tab — der Tab-Wechsel ersetzt den Root-Screen.
    $vorrat = Native::visit('/vorrat')->tap('Tomaten');

    expect(collect(listenAbschnitte($vorrat))->firstWhere('ueberschrift', 'Obst & Gemüse')['artikel'])
        ->not->toContain('Tomaten');

    $einkaufen = $vorrat
        ->tap('Einkaufen')
        ->assertReplacedWith('/')
        ->follow();

    $obstUndGemuese = collect(listenAbschnitte($einkaufen))
        ->firstWhere('ueberschrift', 'Obst & Gemüse')['artikel'];

    // Katalogreihenfolge in Anhang A: … Bananen … Tomaten … Kartoffeln.
    expect($obstUndGemuese)->toBe(['Bananen', 'Tomaten', 'Kartoffeln']);
});

it('zählt im Untertitel die offenen Artikel', function () {
    aufDieListe('tofu', 'hummus', 'salz');

    expect(navUntertitel(Native::visit('/')))->toBe('3 Artikel');
});

it('zeigt keinen Untertitel, solange kein Artikel offen ist', function () {
    expect(navUntertitel(Native::visit('/')))->toBeNull();
});

it('zeigt den Leerzustand, wenn nichts auf der Liste steht', function () {
    Native::visit('/', platform: 'android')
        ->assertSee('Liste ist leer.')
        ->assertSee('Tippe auf den Vorrat-Tab, um Artikel hinzuzufügen.')
        ->assertMissingElement('list_item')
        ->assertElement('icon', fn (array $node) => ($node['props']['name'] ?? null) === 'shopping_cart');
});

it('kehrt zum Leerzustand zurück, sobald der letzte Artikel abgehakt ist', function () {
    aufDieListe('tofu');

    Native::visit('/')
        ->tap('Tofu')
        ->assertSee('Liste ist leer.')
        ->assertMissingElement('list_item');

    expect(navUntertitel(Native::visit('/')))->toBeNull();
});

it('rendert die Artikel in einer scrollbaren Liste innerhalb der nativen Chrome', function () {
    aufDieListe('tofu');

    // `native:list` ist der scrollende Container; die Tab-Leiste gehört zur
    // nativen Chrome, die ihre Höhe selbst als Inset an den Inhalt weitergibt.
    Native::visit('/')
        ->assertElement('list')
        ->assertElement('native_root_tabs')
        ->assertHasTabBar();
});

it('beschriftet auch die gefüllte Liste für Screenreader', function () {
    aufDieListe('tofu', 'salz');

    Native::visit('/', platform: 'android')->assertAccessible();
});

it('zeigt die Action „Alles abhaken“ nur, solange mindestens ein Artikel offen ist', function () {
    Native::visit('/')->assertMissingElement('top_bar_action', fn (array $node) => ($node['props']['a11y_label'] ?? null) === 'Alles abhaken');

    aufDieListe('tofu');

    Native::visit('/')->assertElement('top_bar_action', fn (array $node) => ($node['props']['a11y_label'] ?? null) === 'Alles abhaken');
});

it('fragt vor dem Abhaken mit einem nativen Dialog nach', function () {
    aufDieListe('tofu', 'hummus', 'salz');

    Native::visit('/')
        ->press('alleAbhakenBestaetigen')
        ->assertNativeCalled('Dialog.Alert', fn (array $params) => $params['title'] === 'Alles abhaken?'
            && $params['message'] === '3 eigene Artikel wandern zurück in den Vorrat.'
            && collect($params['buttons'])->map(fn ($button) => is_array($button) ? $button['label'] : $button)->all() === ['Abbrechen', 'Abhaken']);
});

it('zählt im Dialogtext den einen Artikel im Singular', function () {
    aufDieListe('tofu');

    Native::visit('/')
        ->press('alleAbhakenBestaetigen')
        ->assertNativeCalled('Dialog.Alert', fn (array $params) => $params['message'] === '1 eigener Artikel wandert zurück in den Vorrat.');
});

it('schickt nach „Abhaken“ alle Artikel zurück in den Vorrat', function () {
    aufDieListe('tofu', 'hummus', 'salz');

    Native::visit('/')
        ->press('alleAbhakenBestaetigen')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Abhaken'])
        ->assertSee('Liste ist leer.')
        ->assertSee('Tippe auf den Vorrat-Tab, um Artikel hinzuzufügen.')
        ->assertMissingElement('list_item')
        ->assertMissingElement('top_bar_action', fn (array $node) => ($node['props']['a11y_label'] ?? null) === 'Alles abhaken');

    $vorrat = collect(listenAbschnitte(Native::visit('/vorrat')))
        ->flatMap(fn (array $abschnitt) => $abschnitt['artikel']);

    expect($vorrat)->toContain('Tofu', 'Hummus', 'Salz')->toHaveCount(112);
});

it('lässt die Liste nach „Abbrechen“ unverändert', function () {
    aufDieListe('tofu', 'salz');

    $screen = Native::visit('/')
        ->press('alleAbhakenBestaetigen')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Abbrechen']);

    expect(listenAbschnitte($screen))->toBe([
        ['ueberschrift' => 'Kühlregal', 'artikel' => ['Tofu']],
        ['ueberschrift' => 'Lebensmittel', 'artikel' => ['Salz']],
    ]);
});

it('lässt die Liste unverändert, wenn der Dialog ohne Button geschlossen wird', function () {
    aufDieListe('tofu', 'salz');

    $screen = Native::visit('/')->press('alleAbhakenBestaetigen');

    expect(listenAbschnitte($screen))->toBe([
        ['ueberschrift' => 'Kühlregal', 'artikel' => ['Tofu']],
        ['ueberschrift' => 'Lebensmittel', 'artikel' => ['Salz']],
    ]);
});
