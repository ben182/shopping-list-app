<?php

use App\Models\ListenArtikel;
use Native\Mobile\Testing\Native;

/*
 * Die Erwartungswerte stammen aus Anhang A der PRD, nicht aus der
 * Konfiguration — sonst prüfte der Test die Konfiguration gegen sich selbst.
 */

it('zeigt bei leerer Liste den ganzen Katalog in Katalogreihenfolge', function () {
    $abschnitte = listenAbschnitte(Native::visit('/vorrat'));

    expect(array_column($abschnitte, 'ueberschrift'))->toBe([
        'Obst & Gemüse',
        'Brot & Backwaren',
        'Kühlregal',
        'Tiefkühl',
        'Lebensmittel',
        'Getränke',
        'Haushalt',
        'Drogerie',
    ]);

    expect($abschnitte[0]['artikel'])->toBe([
        'Äpfel', 'Bananen', 'Beeren', 'Zitronen', 'Avocado', 'Tomaten', 'Gurken',
        'Salat', 'Paprika', 'Spinat', 'Brokkoli', 'Champignons', 'Zwiebeln',
        'Knoblauch', 'Ingwer', 'Karotten', 'Kartoffeln',
    ]);

    expect($abschnitte[7]['artikel'])->toBe([
        'Zahnpasta', 'Duschgel', 'Shampoo', 'Deo', 'Handseife',
        'Taschentücher', 'Feuchttücher (Toilette)',
    ]);

    expect(array_map(fn (array $a) => count($a['artikel']), $abschnitte))
        ->toBe([17, 5, 26, 7, 35, 7, 8, 7]);
});

it('sortiert die Artikel einer Gruppe nach Katalog, nicht alphabetisch', function () {
    $abschnitte = listenAbschnitte(Native::visit('/vorrat'));

    $getraenke = collect($abschnitte)->firstWhere('ueberschrift', 'Getränke')['artikel'];

    expect($getraenke)->toBe([
        'Wasser (still)', 'Wasser (Sprudel)', 'Saft', 'Bier',
        'Wein (vegan)', 'Cola', 'Energy Drink',
    ]);

    expect($getraenke)->not->toBe(collect($getraenke)->sort()->values()->all());
});

it('zeigt jeden Artikel als tappbare Zeile mit Plus-Icon', function () {
    Native::visit('/vorrat', platform: 'android')
        ->assertElement('list_item', fn (array $node) => ($node['props']['headline'] ?? null) === 'Tofu'
            && ($node['props']['trailing_icon'] ?? null) === 'add'
            && ($node['on_press'] ?? null) !== null);
});

it('nimmt einen angetippten Artikel sofort aus dem Vorrat', function () {
    $screen = Native::visit('/vorrat')->tap('Tofu');

    $kuehlregal = collect(listenAbschnitte($screen))->firstWhere('ueberschrift', 'Kühlregal')['artikel'];

    expect($kuehlregal)->not->toContain('Tofu')->toHaveCount(25);

    // Die Lücke schließt sich, der Rest behält seine Katalogreihenfolge.
    expect($kuehlregal[8])->toBe('Räuchertofu');
});

it('behält den Zustand über einen App-Neustart hinweg', function () {
    Native::visit('/vorrat')->tap('Tofu');

    $nachNeustart = Native::visit('/vorrat');

    expect(array_merge(...array_column(listenAbschnitte($nachNeustart), 'artikel')))
        ->not->toContain('Tofu');
    expect(navUntertitel($nachNeustart))->toBe('1 auf der Liste');
});

it('blendet eine Gruppe samt Überschrift aus, wenn alle ihre Artikel auf der Liste sind', function () {
    $screen = Native::visit('/vorrat');

    foreach (['Brot', 'Brötchen', 'Toast', 'Wraps', 'Hot Dog Brötchen'] as $artikel) {
        $screen->tap($artikel);
    }

    expect(array_column(listenAbschnitte($screen), 'ueberschrift'))
        ->not->toContain('Brot & Backwaren')
        ->toHaveCount(7);
});

it('zeigt im Untertitel den Hinweis, solange die Liste leer ist', function () {
    expect(navUntertitel(Native::visit('/vorrat')))
        ->toBe('Tippe auf einen Artikel zum Hinzufügen');
});

it('zählt im Untertitel die Artikel auf der Liste', function () {
    $screen = Native::visit('/vorrat');

    foreach (['Tofu', 'Hummus', 'Nudeln', 'Reis', 'Salz'] as $artikel) {
        $screen->tap($artikel);
    }

    expect(navUntertitel($screen))->toBe('5 auf der Liste');
});

it('zeigt den Leerzustand, wenn alles auf der Liste ist', function () {
    $screen = Native::visit('/vorrat', platform: 'android');

    foreach (config('katalog.gruppen') as $gruppe) {
        foreach (array_keys($gruppe['artikel']) as $artikelId) {
            $screen->call('aufDieListe', $artikelId);
        }
    }

    expect(listenAbschnitte($screen))->toBe([]);

    $screen->assertSee('Alles auf der Liste.')
        ->assertMissingElement('list_item')
        ->assertElement('icon', fn (array $node) => ($node['props']['name'] ?? null) === 'check');
});

it('ignoriert gespeicherte Artikel, die es im Katalog nicht mehr gibt', function () {
    ListenArtikel::create(['artikel_id' => 'tofu']);
    ListenArtikel::create(['artikel_id' => 'abgeschaffter-artikel']);

    $screen = Native::visit('/vorrat');

    expect(navUntertitel($screen))->toBe('1 auf der Liste');
    $screen->assertDontSee('abgeschaffter-artikel');
});
