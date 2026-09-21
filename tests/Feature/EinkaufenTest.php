<?php

use Ben182\AppLifecycle\Events\AppForegrounded;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

/*
 * Der Einkaufen-Screen zeigt seit dem Umzug des Vorrats nach Mealie nur noch
 * eine Quelle: die Mealie-Einkaufsliste. Die Vorbedingung jedes Tests ist
 * deshalb eine gefakte Liste, nicht mehr ein Eintrag in der Gerätedatenbank.
 */

/**
 * Die Artikel, mit denen diese Geschichte anfängt — in Mealies Darstellung
 * und mit den Labels, unter denen sie in ihre Warengruppen fallen.
 *
 * @return list<array<string, mixed>>
 */
function artikelAufDerListe(): array
{
    return [
        mealieArtikel('Tofu', label: 'Fleischprodukte', position: 0, id: 'tofu-1'),
        mealieArtikel('Hummus', label: 'Milchprodukte', position: 1, id: 'hummus-1'),
        mealieArtikel('Salz', label: 'Gewürze', position: 2, id: 'salz-1'),
    ];
}

it('gruppiert die Artikel auf der Liste in Katalogreihenfolge und blendet leere Gruppen aus', function () {
    mitMealie([
        mealieArtikel('Salz', label: 'Gewürze', position: 0),
        mealieArtikel('Äpfel', label: 'Obst & Gemüse', position: 1),
        mealieArtikel('Bananen', label: 'Obst & Gemüse', position: 2),
        mealieArtikel('Brot', label: 'Backwaren', position: 3),
        mealieArtikel('Wasser (still)', label: 'Getränke', position: 4),
    ]);

    expect(listenAbschnitte(Native::visit('/')))->toBe([
        ['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['Äpfel', 'Bananen']],
        ['ueberschrift' => 'Brot & Backwaren', 'artikel' => ['Brot']],
        ['ueberschrift' => 'Lebensmittel', 'artikel' => ['Salz']],
        ['ueberschrift' => 'Getränke', 'artikel' => ['Wasser (still)']],
    ]);
});

it('zeigt jeden Artikel als Zeile mit leerer Checkbox vorn', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    Native::visit('/')
        ->assertElement('list_item', fn (array $node) => ($node['props']['headline'] ?? null) === 'Tofu'
            && ($node['props']['leading_type'] ?? null) === 'checkbox'
            && ($node['props']['leading_checked'] ?? null) === false
            && ($node['on_press'] ?? null) !== null);
});

it('hakt einen angetippten Artikel ab und nimmt ihn aus seiner Warengruppe', function () {
    mitMealie(artikelAufDerListe());

    $screen = Native::visit('/')->tap('Tofu');

    expect(listenAbschnitte($screen))->toBe([
        ['ueberschrift' => 'Kühlregal', 'artikel' => ['Hummus']],
        ['ueberschrift' => 'Lebensmittel', 'artikel' => ['Salz']],
    ]);

    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)']);
});

it('hakt einen Artikel auch dann ab, wenn nur seine Checkbox getroffen wird', function () {
    mitMealie(artikelAufDerListe());

    $screen = checkboxAntippen(Native::visit('/'), 'mealie-tofu-1');

    expect(listenAbschnitte($screen))->toBe([
        ['ueberschrift' => 'Kühlregal', 'artikel' => ['Hummus']],
        ['ueberschrift' => 'Lebensmittel', 'artikel' => ['Salz']],
    ]);
});

it('zeigt einen im Vorrat angetippten Artikel nach dem Tab-Wechsel auf der Liste', function () {
    mitVorrat(
        [vorratArtikel('Tomaten', label: 'Obst & Gemüse')],
        [mealieArtikel('Bananen', label: 'Obst & Gemüse', position: 0)],
    );

    // Derselbe Weg wie auf dem Gerät: im Vorrat antippen, dann unten auf den
    // Einkaufen-Tab — der Tab-Wechsel ersetzt den Root-Screen.
    $vorrat = Native::visit('/vorrat')->tap('Tomaten');

    expect(vorratZeilen($vorrat))->toBe([]);

    $einkaufen = $vorrat
        ->tap('Einkaufen')
        ->assertReplacedWith('/')
        ->follow();

    expect(collect(listenAbschnitte($einkaufen))->firstWhere('ueberschrift', 'Obst & Gemüse')['artikel'])
        ->toContain('Tomaten');
});

it('zählt im Untertitel die offenen Artikel', function () {
    mitMealie(artikelAufDerListe());

    expect(navUntertitel(Native::visit('/')))->toBe('3 Artikel');
});

it('zeigt keinen Untertitel, solange kein Artikel offen ist', function () {
    mitMealie([]);

    expect(navUntertitel(Native::visit('/')))->toBeNull();
});

it('zeigt den Leerzustand, wenn nichts auf der Liste steht', function () {
    mitMealie([]);

    Native::visit('/', platform: 'android')
        ->assertSee('Liste ist leer.')
        ->assertSee('Tippe oben auf „+“ oder auf den Vorrat-Tab, um Artikel hinzuzufügen.')
        ->assertMissingElement('list_item')
        ->assertElement('icon', fn (array $node) => ($node['props']['name'] ?? null) === 'shopping_cart');
});

it('kehrt zum Leerzustand zurück, sobald der letzte Artikel abgehakt ist', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    Native::visit('/')
        ->tap('Tofu')
        ->assertSee('Liste ist leer.');
});

/*
 * 56 dp ist die Höhe einer Listenzeile aus der PRD, nicht aus dem Code
 * abgelesen. Ohne den Block „Abgehakt“ ist unter der Liste nur die
 * Tab-Leiste — die letzte Zeile soll frei über ihr stehen.
 */
it('lässt ohne den Block „Abgehakt“ am Listenende eine Zeilenhöhe Luft', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    $luft = listenLuft(Native::visit('/'));

    expect($luft)->not->toBeNull();
    expect($luft['layout']['height'] ?? null)->toBe(56.0);
});

it('zeigt im Leerzustand keine Luft', function () {
    mitMealie([]);

    expect(knotenMitRef(Native::visit('/'), 'listenende'))->toBeNull();
});

it('rendert die Artikel in einer scrollbaren Liste innerhalb der nativen Chrome', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    // `native:list` ist der scrollende Container; die Tab-Leiste gehört zur
    // nativen Chrome, die ihre Höhe selbst als Inset an den Inhalt weitergibt.
    Native::visit('/')
        ->assertElement('list')
        ->assertElement('native_root_tabs')
        ->assertHasTabBar();
});

it('beschriftet auch die gefüllte Liste für Screenreader', function () {
    mitMealie(artikelAufDerListe());

    Native::visit('/', platform: 'android')->assertAccessible();
});

it('lässt die Action „Alles abhaken“ weg, solange nichts offen ist', function () {
    mitMealie([]);

    Native::visit('/')->assertMissingElement('top_bar_action', fn (array $node) => ($node['props']['a11y_label'] ?? null) === 'Alles abhaken');
});

it('zeigt die Action „Alles abhaken“, sobald ein Artikel offen ist', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    Native::visit('/')->assertElement('top_bar_action', fn (array $node) => ($node['props']['a11y_label'] ?? null) === 'Alles abhaken');
});

it('hakt ohne Nachfrage sofort alles ab und stellt die Leiste zum Rückgängigmachen hin', function () {
    mitMealie(artikelAufDerListe());

    $screen = Native::visit('/')->press('alleAbhaken');

    $screen->assertNativeNotCalled('Dialog.Alert')
        ->assertSee('Liste ist leer.')
        ->assertSee('Tippe oben auf „+“ oder auf den Vorrat-Tab, um Artikel hinzuzufügen.')
        ->assertMissingElement('top_bar_action', fn (array $node) => ($node['props']['a11y_label'] ?? null) === 'Alles abhaken');

    expect(texteIn(rueckgaengigLeiste($screen) ?? []))->toBe(['3 Artikel abgehakt', 'Rückgängig']);
    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (3)']);
});

it('zählt im Leistentext den einen Artikel im Singular', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    $screen = Native::visit('/')->press('alleAbhaken');

    expect(texteIn(rueckgaengigLeiste($screen) ?? []))->toContain('1 Artikel abgehakt');
});

it('holt mit „Rückgängig“ genau die Artikel in ihre Warengruppen zurück', function () {
    mitMealie([
        mealieArtikel('Tofu', label: 'Fleischprodukte', position: 0),
        mealieArtikel('Salz', label: 'Gewürze', position: 1),
    ]);

    $screen = Native::visit('/')->press('alleAbhaken')->press('rueckgaengigMachen');

    expect(listenAbschnitte($screen))->toBe([
        ['ueberschrift' => 'Kühlregal', 'artikel' => ['Tofu']],
        ['ueberschrift' => 'Lebensmittel', 'artikel' => ['Salz']],
    ]);

    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('gibt der Leiste einen Textknopf in der Akzentfarbe', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    $screen = Native::visit('/')->press('alleAbhaken');

    // `ghost` ist der Knopf ohne Fläche — seine Schrift zeichnet das Gerät
    // aus dem Theme in der Primärfarbe. Im Baum steht deshalb die Variante,
    // nicht die Farbe.
    expect(knotenMitRef($screen, 'rueckgaengig')['props'] ?? [])
        ->toMatchArray(['label' => 'Rückgängig', 'variant' => 'ghost']);
});

it('beschriftet das Kreuz der Leiste für Screenreader und räumt sie damit ab', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    $screen = Native::visit('/', platform: 'android')->press('alleAbhaken');

    $screen->assertAccessible();

    expect(knotenMitRef($screen, 'rueckgaengig-schliessen')['props']['a11y_label'] ?? null)
        ->toBe('Schließen');

    $screen->press('leisteSchliessen');

    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('macht den Vorgang nach dem Kreuz nicht mehr rückgängig', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    $screen = Native::visit('/')
        ->press('alleAbhaken')
        ->press('leisteSchliessen')
        ->press('rueckgaengigMachen');

    $screen->assertSee('Liste ist leer.');
});

it('räumt die Leiste beim Pull-to-Refresh ab', function () {
    mitMealie([
        mealieArtikel('Tofu', label: 'Fleischprodukte', position: 0),
        mealieArtikel('Salz', label: 'Gewürze', position: 1),
    ]);

    $screen = Native::visit('/')->press('alleAbhaken')->press('rueckgaengigMachen');

    // Die Artikel stehen wieder da — jetzt die Interaktion, die die zweite
    // Leiste abräumen soll.
    $screen->press('alleAbhaken')->press('neuLaden');

    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('räumt die Leiste beim Öffnen der Einstellungen ab', function () {
    mitMealie([
        mealieArtikel('Tofu', label: 'Fleischprodukte', position: 0),
        mealieArtikel('Salz', label: 'Gewürze', position: 1),
    ]);

    $screen = Native::visit('/')->press('alleAbhaken')->press('oeffneEinstellungen');

    // Der Weg des Geräts: die Einstellungen kommen auf den Stapel, und der
    // Rückweg nimmt den Einkaufen-Screen samt Zustand wieder auf.
    $zurueck = $screen->followNavigation()->goBack();

    expect(rueckgaengigLeiste($zurueck))->toBeNull();
});

it('räumt die Leiste beim Tipp auf eine Zeile ab', function () {
    mitMealie([
        mealieArtikel('Tofu', label: 'Fleischprodukte', position: 0),
        mealieArtikel('Salz', label: 'Gewürze', position: 1),
    ]);

    $screen = Native::visit('/')
        ->press('alleAbhaken')
        ->press('rueckgaengigMachen')
        ->tap('Tofu');

    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('räumt die Leiste beim Tab-Wechsel ab', function () {
    mitVorrat([vorratArtikel('Äpfel', label: 'Obst & Gemüse')], [mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    Native::visit('/')->press('alleAbhaken');

    expect(rueckgaengigLeiste(Native::visit('/vorrat')))->toBeNull();
    expect(rueckgaengigLeiste(Native::visit('/')))->toBeNull();
});

it('räumt die Leiste ab, wenn die App aus dem Hintergrund zurückkommt', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    $screen = Native::visit('/')->press('alleAbhaken');

    $screen->emitNative(AppForegrounded::class);

    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('ersetzt eine stehende Leiste durch die des neuen Vorgangs', function () {
    mitMealie([
        mealieArtikel('Tofu', label: 'Fleischprodukte', position: 0),
        mealieArtikel('Salz', label: 'Gewürze', position: 1),
    ]);

    $screen = Native::visit('/')->press('alleAbhaken');

    expect(texteIn(rueckgaengigLeiste($screen) ?? []))->toContain('2 Artikel abgehakt');

    // Einen der beiden zurückholen, ohne die Leiste zu berühren — dann steht
    // ein zweiter Vorgang mit anderer Anzahl an.
    $screen->tap('Abgehakt (2)')->tap('Tofu')->press('alleAbhaken');

    expect(texteIn(rueckgaengigLeiste($screen) ?? []))->toContain('1 Artikel abgehakt');
});

it('lässt bei leerer Liste weder die Action noch eine Leiste stehen', function () {
    mitMealie([]);

    $screen = Native::visit('/');

    $screen->assertMissingElement('top_bar_action', fn (array $node) => ($node['props']['a11y_label'] ?? null) === 'Alles abhaken')
        ->assertSee('Liste ist leer.');

    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('schickt keine eigene Liste mehr mit: alles, was dasteht, kommt aus Mealie', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Fleischprodukte')]);

    Native::visit('/')->tap('Tofu');

    Http::assertSent(fn ($anfrage) => $anfrage->method() === 'PUT'
        && str_contains($anfrage->url(), '/api/households/shopping/items/'));
});
