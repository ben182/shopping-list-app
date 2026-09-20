<?php

use App\Liste\EigeneListe;
use Ben182\AppLifecycle\Events\AppForegrounded;
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

it('schickt einen Artikel auch dann zurück in den Vorrat, wenn nur seine Checkbox getroffen wird', function () {
    aufDieListe('tofu', 'hummus');

    $screen = checkboxAntippen(Native::visit('/'), 'einkaufen-tofu');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Kühlregal', 'artikel' => ['Hummus']]]);
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

it('hakt ohne Nachfrage sofort alles ab und stellt die Leiste zum Rückgängigmachen hin', function () {
    aufDieListe('tofu', 'hummus', 'salz');

    $screen = Native::visit('/')->press('alleAbhaken');

    $screen->assertNativeNotCalled('Dialog.Alert')
        ->assertSee('Liste ist leer.')
        ->assertSee('Tippe auf den Vorrat-Tab, um Artikel hinzuzufügen.')
        ->assertMissingElement('list_item')
        ->assertMissingElement('top_bar_action', fn (array $node) => ($node['props']['a11y_label'] ?? null) === 'Alles abhaken');

    expect(texteIn(rueckgaengigLeiste($screen) ?? []))->toBe(['3 Artikel abgehakt', 'Rückgängig']);

    $vorrat = collect(listenAbschnitte(Native::visit('/vorrat')))
        ->flatMap(fn (array $abschnitt) => $abschnitt['artikel']);

    expect($vorrat)->toContain('Tofu', 'Hummus', 'Salz')->toHaveCount(112);
});

it('zählt im Leistentext den einen Artikel im Singular', function () {
    aufDieListe('tofu');

    $screen = Native::visit('/')->press('alleAbhaken');

    expect(texteIn(rueckgaengigLeiste($screen) ?? []))->toContain('1 Artikel abgehakt');
});

it('holt mit „Rückgängig“ genau die eigenen Artikel in ihre Warengruppen zurück', function () {
    aufDieListe('tofu', 'salz');

    $screen = Native::visit('/')->press('alleAbhaken')->press('rueckgaengigMachen');

    expect(listenAbschnitte($screen))->toBe([
        ['ueberschrift' => 'Kühlregal', 'artikel' => ['Tofu']],
        ['ueberschrift' => 'Lebensmittel', 'artikel' => ['Salz']],
    ]);

    expect(rueckgaengigLeiste($screen))->toBeNull();

    $vorrat = collect(listenAbschnitte(Native::visit('/vorrat')))
        ->flatMap(fn (array $abschnitt) => $abschnitt['artikel']);

    expect($vorrat)->not->toContain('Tofu')->not->toContain('Salz');
});

it('gibt der Leiste einen Textknopf in der Akzentfarbe', function () {
    aufDieListe('tofu');

    $screen = Native::visit('/')->press('alleAbhaken');

    // `ghost` ist der Knopf ohne Fläche — seine Schrift zeichnet das Gerät
    // aus dem Theme in der Primärfarbe. Im Baum steht deshalb die Variante,
    // nicht die Farbe.
    expect(knotenMitRef($screen, 'rueckgaengig')['props'] ?? [])
        ->toMatchArray(['label' => 'Rückgängig', 'variant' => 'ghost']);
});

it('beschriftet das Kreuz der Leiste für Screenreader und räumt sie damit ab', function () {
    aufDieListe('tofu');

    $screen = Native::visit('/', platform: 'android')->press('alleAbhaken');

    $screen->assertAccessible();

    expect(knotenMitRef($screen, 'rueckgaengig-schliessen')['props']['a11y_label'] ?? null)
        ->toBe('Schließen');

    $screen->press('leisteSchliessen');

    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('macht den Vorgang nach dem Kreuz nicht mehr rückgängig', function () {
    aufDieListe('tofu');

    $screen = Native::visit('/')
        ->press('alleAbhaken')
        ->press('leisteSchliessen')
        ->press('rueckgaengigMachen');

    $screen->assertSee('Liste ist leer.');
});

it('räumt die Leiste beim Pull-to-Refresh ab', function () {
    aufDieListe('tofu', 'salz');

    $screen = Native::visit('/')->press('alleAbhaken')->press('rueckgaengigMachen');

    // Die Artikel stehen wieder da — jetzt die Interaktion, die die zweite
    // Leiste abräumen soll.
    $screen->press('alleAbhaken')->press('neuLaden');

    expect(rueckgaengigLeiste($screen))->toBeNull();

    $screen->press('rueckgaengigMachen')->assertSee('Liste ist leer.');
});

it('räumt die Leiste beim Öffnen der Einstellungen ab', function () {
    aufDieListe('tofu', 'salz');

    $screen = Native::visit('/')->press('alleAbhaken')->press('oeffneEinstellungen');

    // Der Weg des Geräts: die Einstellungen kommen auf den Stapel, und der
    // Rückweg nimmt den Einkaufen-Screen samt Zustand wieder auf.
    $zurueck = $screen->followNavigation()->goBack();

    expect(rueckgaengigLeiste($zurueck))->toBeNull();

    $zurueck->press('rueckgaengigMachen')->assertSee('Liste ist leer.');
});

it('räumt die Leiste beim Tipp auf eine Zeile ab', function () {
    aufDieListe('tofu', 'salz');

    $screen = Native::visit('/')
        ->press('alleAbhaken')
        ->press('rueckgaengigMachen')
        ->tap('Tofu');

    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('räumt die Leiste beim Tab-Wechsel ab', function () {
    aufDieListe('tofu');

    Native::visit('/')->press('alleAbhaken');

    expect(rueckgaengigLeiste(Native::visit('/vorrat')))->toBeNull();
    expect(rueckgaengigLeiste(Native::visit('/')))->toBeNull();
});

it('räumt die Leiste ab, wenn die App aus dem Hintergrund zurückkommt', function () {
    aufDieListe('tofu');

    $screen = Native::visit('/')->press('alleAbhaken');

    $screen->emitNative(AppForegrounded::class);

    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('ersetzt eine stehende Leiste durch die des neuen Vorgangs', function () {
    aufDieListe('tofu', 'salz');

    $screen = Native::visit('/')->press('alleAbhaken');

    // Erst den Vorrat wieder auf die Liste holen, ohne die Leiste zu
    // berühren — dann steht ein zweiter Vorgang mit anderer Anzahl an.
    aufDieListe('tofu');

    $screen->press('alleAbhaken');

    expect(texteIn(rueckgaengigLeiste($screen) ?? []))->toContain('1 Artikel abgehakt');

    $screen->press('rueckgaengigMachen');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Kühlregal', 'artikel' => ['Tofu']]]);
});

it('lässt bei leerer Liste weder die Action noch eine Leiste stehen', function () {
    $screen = Native::visit('/');

    $screen->assertMissingElement('top_bar_action', fn (array $node) => ($node['props']['a11y_label'] ?? null) === 'Alles abhaken')
        ->assertSee('Liste ist leer.');

    expect(rueckgaengigLeiste($screen))->toBeNull();
});
