<?php

use App\Katalog\Katalog;
use App\Katalog\Laden;
use App\Katalog\Ladenfilter;
use App\Liste\EigeneListe;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

/*
 * Der Ladenfilter über der Einkaufsliste. Geprüft wird am Screen: die Chips
 * kommen aus dem Wire-Tree, getippt wird über `toggle()` — ein Chip schickt
 * seinen neuen Zustand durch `on_change`, wie das Gerät es täte.
 *
 * Welcher Artikel in welchem Laden steht, sagt `config/katalog.php`. Die
 * Tests nennen bewusst nur ein paar wenige, deren Zuordnung eindeutig ist —
 * sonst prüften sie die Konfiguration gegen sich selbst.
 */

/**
 * Setzt Artikel über dieselbe Fachklasse auf die Liste, die der Vorrat
 * benutzt — die Vorbedingung des Tests, nicht sein Prüfgegenstand.
 */
function aufDieListeFuerLaeden(string ...$artikelIds): void
{
    foreach ($artikelIds as $artikelId) {
        app(EigeneListe::class)->hinzufuegen($artikelId);
    }
}

/**
 * Beschriftung und Zustand der Filter-Chips in Render-Reihenfolge.
 *
 * @return list<array{label: string, aktiv: bool}>
 */
function ladenChips(TestableComponent $screen): array
{
    return array_values(array_map(
        fn (array $node) => [
            'label' => $node['props']['label'] ?? '',
            'aktiv' => (bool) ($node['props']['value'] ?? false),
        ],
        array_filter(
            knotenMitRefPraefix($screen, 'laden-'),
            fn (array $node) => ($node['type'] ?? null) === 'chip',
        ),
    ));
}

/** Nur die Artikelnamen der Liste, ohne ihre Warengruppen. */
function listenArtikel(TestableComponent $screen): array
{
    return array_merge(...array_column(listenAbschnitte($screen), 'artikel')) ?: [];
}

it('setzt über die Liste einen Chip je Laden, „Alle“ zuerst und aktiv', function () {
    aufDieListeFuerLaeden('tofu');

    expect(ladenChips(Native::visit('/')))->toBe([
        ['label' => 'Alle', 'aktiv' => true],
        ['label' => 'Lidl', 'aktiv' => false],
        ['label' => 'Rewe', 'aktiv' => false],
        ['label' => 'Getränkemarkt', 'aktiv' => false],
    ]);
});

it('lässt die Chips weg, solange nichts auf der Liste steht', function () {
    expect(ladenChips(Native::visit('/')))->toBe([]);
});

it('zeigt nach einem Tap auf einen Chip nur noch, was es in diesem Laden gibt', function () {
    // Äpfel sind Grundnahrungsmittel und damit Lidl; Tofu und Tempeh
    // gehören zu den Spezialitäten bei Rewe, Bier in den Getränkemarkt.
    aufDieListeFuerLaeden('aepfel', 'tofu', 'tempeh', 'bier');

    $screen = Native::visit('/')->toggle('laden-lidl', true);

    expect(listenAbschnitte($screen))->toBe([
        ['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['Äpfel']],
    ]);

    expect(ladenChips($screen))->toBe([
        ['label' => 'Alle', 'aktiv' => false],
        ['label' => 'Lidl', 'aktiv' => true],
        ['label' => 'Rewe', 'aktiv' => false],
        ['label' => 'Getränkemarkt', 'aktiv' => false],
    ]);
});

it('behält die Warengruppen als Überschriften, statt nach Laden zu gruppieren', function () {
    aufDieListeFuerLaeden('zaziki', 'tofu', 'suess-sauer-sauce');

    $ueberschriften = array_column(listenAbschnitte(Native::visit('/')->toggle('laden-rewe', true)), 'ueberschrift');

    expect($ueberschriften)
        ->toBe(['Kühlregal', 'Lebensmittel'])
        ->not->toContain('Rewe');
});

it('führt ein zweiter Tap auf den aktiven Chip zurück auf „Alle“', function () {
    aufDieListeFuerLaeden('tofu', 'bier');

    $screen = Native::visit('/')->toggle('laden-getraenkemarkt', true);

    expect(listenArtikel($screen))->toBe(['Bier']);

    // Das Gerät schickt beim zweiten Tap `false` — der Screen darf sich
    // darauf nicht verlassen und entscheidet am Schlüssel.
    $screen = $screen->toggle('laden-getraenkemarkt', false);

    expect(listenArtikel($screen))->toBe(['Tofu', 'Bier']);
    expect(ladenChips($screen)[0])->toBe(['label' => 'Alle', 'aktiv' => true]);
});

it('wechselt mit einem Tap direkt von einem Laden zum nächsten', function () {
    aufDieListeFuerLaeden('tofu', 'bier');

    $screen = Native::visit('/')
        ->toggle('laden-lidl', true)
        ->toggle('laden-getraenkemarkt', true);

    expect(listenArtikel($screen))->toBe(['Bier']);
});

it('nennt im Untertitel beide Zahlen, solange ein Laden filtert', function () {
    aufDieListeFuerLaeden('aepfel', 'tofu', 'tempeh', 'bier');

    $screen = Native::visit('/');

    expect(navUntertitel($screen))->toBe('4 Artikel');

    // Von den vieren ist nur Äpfel ein Lidl-Artikel.
    expect(navUntertitel($screen->toggle('laden-lidl', true)))->toBe('1 von 4 Artikeln');
});

it('zeigt einen eigenen Leerzustand, wenn im gewählten Laden nichts ansteht', function () {
    aufDieListeFuerLaeden('tofu');

    $screen = Native::visit('/')->toggle('laden-getraenkemarkt', true);

    expect(listenAbschnitte($screen))->toBe([]);

    $screen->assertSee('Nichts für Getränkemarkt auf der Liste.')
        ->assertSee('Tippe oben auf „Alle“, um die ganze Liste zu sehen.')
        ->assertDontSee('Liste ist leer.');

    // Der Weg zurück muss stehen bleiben, sonst führt der Filter in die
    // Sackgasse.
    expect(ladenChips($screen))->toHaveCount(4);
});

it('behält den gewählten Laden über einen Tab-Wechsel hinweg', function () {
    aufDieListeFuerLaeden('tofu', 'bier');

    // Derselbe Weg wie auf dem Gerät: unten in den Vorrat und wieder zurück
    // — jeder Tab-Wechsel ersetzt den Root-Screen und mountet ihn neu.
    $screen = Native::visit('/')
        ->toggle('laden-getraenkemarkt', true)
        ->tap('Vorrat')
        ->follow()
        ->tap('Einkaufen')
        ->follow();

    expect(listenArtikel($screen))->toBe(['Bier']);
});

it('hakt mit „Alles abhaken“ nur ab, was der gewählte Laden zeigt', function () {
    aufDieListeFuerLaeden('aepfel', 'tofu', 'bier');

    $screen = Native::visit('/')
        ->toggle('laden-getraenkemarkt', true)
        ->press('alleAbhaken');

    expect(listenArtikel($screen))->toBe([]);
    expect(app(EigeneListe::class)->artikelIds())->toBe(['aepfel', 'tofu']);

    expect(listenArtikel($screen->toggle('laden-getraenkemarkt', false)))->toBe(['Äpfel', 'Tofu']);
});

it('holt „Rückgängig“ danach genau die abgehakten Artikel zurück', function () {
    aufDieListeFuerLaeden('aepfel', 'bier');

    $screen = Native::visit('/')
        ->toggle('laden-getraenkemarkt', true)
        ->press('alleAbhaken')
        ->press('rueckgaengigMachen');

    expect(listenArtikel($screen))->toBe(['Bier']);
    expect(app(EigeneListe::class)->artikelIds())->toBe(['aepfel', 'bier']);
});

it('hält den gewählten Laden in der Sitzung und sonst nirgends', function () {
    aufDieListeFuerLaeden('tofu', 'bier');

    Native::visit('/')->toggle('laden-getraenkemarkt', true);

    // Ein Singleton, damit die Wahl den Tab-Wechsel übersteht …
    expect(app(Ladenfilter::class))->toBe(app(Ladenfilter::class));
    expect(app(Ladenfilter::class)->laden())->toBe(Laden::Getraenkemarkt);

    // … und nur ein Singleton: ein frischer Prozess beginnt wieder bei
    // „Alle“, statt die App mit einer halben Liste zu öffnen.
    expect((new Ladenfilter)->laden())->toBeNull();
});

it('lässt einen Artikel ohne hinterlegten Laden in jedem Filter stehen', function () {
    config()->set('katalog.gruppen.obst-gemuese.laeden', []);

    $katalog = app(Katalog::class);

    expect($katalog->imLaden(['aepfel'], Laden::Lidl))->toBe(['aepfel']);
    expect($katalog->imLaden(['aepfel'], Laden::Getraenkemarkt))->toBe(['aepfel']);
});

it('ignoriert einen unbekannten Laden-Schlüssel in der Konfiguration', function () {
    config()->set('katalog.gruppen.obst-gemuese.laeden', ['aldi', 'lidl']);

    $laeden = app(Katalog::class)->gruppeMitNamen('Obst & Gemüse')?->laeden;

    expect($laeden)->toBe([Laden::Lidl]);
});

/*
 * Derselbe Filter auf dem Vorrat-Tab: dieselben Chips, dieselbe Wahl, nur
 * andersherum — hier blendet der Laden aus, was man dort nicht bekommt.
 */

it('setzt dieselben Chips auch über den Vorrat', function () {
    expect(ladenChips(Native::visit('/vorrat')))->toBe([
        ['label' => 'Alle', 'aktiv' => true],
        ['label' => 'Lidl', 'aktiv' => false],
        ['label' => 'Rewe', 'aktiv' => false],
        ['label' => 'Getränkemarkt', 'aktiv' => false],
    ]);
});

it('zeigt im Vorrat nach einem Tap nur noch, was es im Laden gibt', function () {
    $screen = Native::visit('/vorrat')->toggle('laden-getraenkemarkt', true);

    expect(array_column(listenAbschnitte($screen), 'ueberschrift'))->toBe(['Getränke']);

    // „Wein“ und „Energy Drink“ stehen im Katalog zusätzlich bei Rewe bzw.
    // überall — im Getränkemarkt gibt es sie trotzdem.
    expect(listenArtikel($screen))->toBe([
        'Wasser (still)', 'Wasser (Sprudel)', 'Saft', 'Bier',
        'Wein (vegan)', 'Cola', 'Energy Drink',
    ]);
});

it('teilt die Wahl zwischen Vorrat und Einkaufen', function () {
    aufDieListeFuerLaeden('tofu', 'bier');

    $einkaufen = Native::visit('/vorrat')
        ->toggle('laden-getraenkemarkt', true)
        ->tap('Einkaufen')
        ->follow();

    expect(listenArtikel($einkaufen))->toBe(['Bier']);
    expect(ladenChips($einkaufen)[3])->toBe(['label' => 'Getränkemarkt', 'aktiv' => true]);
});

it('verbindet im Vorrat Suche und Laden mit UND', function () {
    // „Wasser“ trifft zwei Getränke; bei Lidl bleibt davon keines übrig.
    $screen = Native::visit('/vorrat')->call('suchen', 'Wasser');

    expect(listenArtikel($screen))->toBe(['Wasser (still)', 'Wasser (Sprudel)']);

    expect(listenArtikel($screen->toggle('laden-lidl', true)))->toBe([]);
});

it('sagt beim leeren Suchergebnis dazu, dass ein Laden filtert', function () {
    Native::visit('/vorrat')
        ->toggle('laden-lidl', true)
        ->call('suchen', 'Wasser')
        ->assertSee('Keine Treffer für „Wasser“.')
        ->assertSee('Es werden nur Artikel für Lidl gezeigt.');
});

it('lässt den Hinweis auf den Laden weg, solange keiner filtert', function () {
    Native::visit('/vorrat')
        ->call('suchen', 'Gibtsnicht')
        ->assertSee('Keine Treffer für „Gibtsnicht“.')
        ->assertDontSee('Es werden nur Artikel');
});

it('zeigt im Vorrat einen eigenen Leerzustand, wenn für den Laden alles auf der Liste ist', function () {
    // Alle Getränke auf die Liste — der Getränkemarkt ist damit abgearbeitet,
    // der Rest des Vorrats aber nicht.
    aufDieListeFuerLaeden('wasser-still', 'wasser-sprudel', 'saft', 'bier', 'wein', 'cola', 'energy-drink');

    $screen = Native::visit('/vorrat')->toggle('laden-getraenkemarkt', true);

    expect(listenAbschnitte($screen))->toBe([]);

    $screen->assertSee('Für Getränkemarkt ist alles auf der Liste.')
        ->assertSee('Tippe oben auf „Alle“, um den ganzen Vorrat zu sehen.')
        ->assertDontSee('Alles auf der Liste.');

    expect(ladenChips($screen))->toHaveCount(4);
});

it('lässt die Chips im Vorrat weg, wenn der ganze Katalog auf der Liste steht', function () {
    aufDieListeFuerLaeden(...app(Katalog::class)->artikelIds());

    $screen = Native::visit('/vorrat');

    expect(ladenChips($screen))->toBe([]);
    $screen->assertSee('Alles auf der Liste.');
});

/*
 * Die Aufteilung selbst. Geprüft wird nicht, welcher Artikel wohin gehört —
 * das ist eine Frage des Einkaufens und steht in der Konfiguration —, sondern
 * dass die Aufteilung überhaupt trennt. Ohne diese Regel wandert beim Pflegen
 * schleichend alles zu „Lidl und Rewe" zurück, und die Chips filtern nichts
 * mehr.
 */

it('ordnet jeden Katalog-Artikel genau einem Laden zu', function () {
    $mehrdeutig = [];

    foreach (app(Katalog::class)->gruppen() as $gruppe) {
        foreach ($gruppe->artikel as $artikel) {
            if (count($artikel->laeden) !== 1) {
                $mehrdeutig[$artikel->id] = array_map(fn (Laden $laden) => $laden->value, $artikel->laeden);
            }
        }
    }

    expect($mehrdeutig)->toBe([]);
});

it('deckt mit den drei Läden zusammen den ganzen Katalog ab', function () {
    $katalog = app(Katalog::class);
    $alle = $katalog->artikelIds();

    $verteilt = array_merge(...array_map(
        fn (Laden $laden) => $katalog->imLaden($alle, $laden),
        Laden::alle(),
    ));

    sort($alle);
    sort($verteilt);

    expect($verteilt)->toBe($alle);
});
