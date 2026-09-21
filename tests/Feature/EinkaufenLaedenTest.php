<?php

use App\Katalog\Katalog;
use App\Katalog\Laden;
use App\Katalog\Ladenfilter;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

/*
 * Der Ladenfilter über Einkaufsliste und Vorrat. Geprüft wird am Screen: die
 * Chips kommen aus dem Wire-Tree, getippt wird über `toggle()` — ein Chip
 * schickt seinen neuen Zustand durch `on_change`, wie das Gerät es täte.
 *
 * Welche Läden eine Warengruppe hat, sagt `config/katalog.php`; die Ausnahme
 * am einzelnen Artikel kommt als `extras.laeden` aus Mealie. Die Tests nennen
 * bewusst nur ein paar wenige, deren Zuordnung eindeutig ist — sonst prüften
 * sie die Konfiguration gegen sich selbst.
 */

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

/**
 * Vier Artikel, über die drei Läden verteilt: Äpfel erben Lidl von ihrer
 * Warengruppe, Tofu und Tempeh tragen die Rewe-Ausnahme aus dem Vorrat mit,
 * Bier erbt den Getränkemarkt.
 *
 * @return list<array<string, mixed>>
 */
function artikelInDreiLaeden(): array
{
    return [
        mealieArtikel('Äpfel', label: 'Obst & Gemüse', position: 0),
        mealieArtikel('Tofu', label: 'Kühlregal', position: 1, laeden: 'rewe'),
        mealieArtikel('Tempeh', label: 'Kühlregal', position: 2, laeden: 'rewe'),
        mealieArtikel('Bier', label: 'Getränke', position: 3),
    ];
}

it('setzt über die Liste einen Chip je Laden, „Alle“ zuerst und aktiv', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Kühlregal')]);

    expect(ladenChips(Native::visit('/')))->toBe([
        ['label' => 'Alle', 'aktiv' => true],
        ['label' => 'Lidl', 'aktiv' => false],
        ['label' => 'Rewe', 'aktiv' => false],
        ['label' => 'Getränkemarkt', 'aktiv' => false],
        ['label' => 'dm', 'aktiv' => false],
        ['label' => 'Rossmann', 'aktiv' => false],
        ['label' => 'Budni', 'aktiv' => false],
    ]);
});

it('lässt die Chips weg, solange nichts auf der Liste steht', function () {
    mitMealie([]);

    expect(ladenChips(Native::visit('/')))->toBe([]);
});

it('zeigt nach einem Tap auf einen Chip nur noch, was es in diesem Laden gibt', function () {
    mitMealie(artikelInDreiLaeden());

    $screen = Native::visit('/')->toggle('laden-lidl', true);

    expect(listenAbschnitte($screen))->toBe([
        ['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['Äpfel']],
    ]);

    expect(ladenChips($screen))->toBe([
        ['label' => 'Alle', 'aktiv' => false],
        ['label' => 'Lidl', 'aktiv' => true],
        ['label' => 'Rewe', 'aktiv' => false],
        ['label' => 'Getränkemarkt', 'aktiv' => false],
        ['label' => 'dm', 'aktiv' => false],
        ['label' => 'Rossmann', 'aktiv' => false],
        ['label' => 'Budni', 'aktiv' => false],
    ]);
});

it('lässt die Ausnahme am Artikel die Läden seiner Warengruppe überschreiben', function () {
    mitMealie([
        mealieArtikel('Sojajoghurt', label: 'Kühlregal', position: 0),
        mealieArtikel('Tofu', label: 'Kühlregal', position: 1, laeden: 'rewe'),
    ]);

    $screen = Native::visit('/');

    expect(listenArtikel($screen->toggle('laden-lidl', true)))->toBe(['Sojajoghurt']);
    expect(listenArtikel($screen->toggle('laden-rewe', true)))->toBe(['Tofu']);
});

it('behält die Warengruppen als Überschriften, statt nach Laden zu gruppieren', function () {
    mitMealie([
        mealieArtikel('Zaziki', label: 'Kühlregal', position: 0, laeden: 'rewe'),
        mealieArtikel('Süß-Sauer-Sauce', label: 'Würzmittel', position: 1, laeden: 'rewe'),
    ]);

    $ueberschriften = array_column(listenAbschnitte(Native::visit('/')->toggle('laden-rewe', true)), 'ueberschrift');

    expect($ueberschriften)
        ->toBe(['Kühlregal', 'Lebensmittel'])
        ->not->toContain('Rewe');
});

it('führt ein zweiter Tap auf den aktiven Chip zurück auf „Alle“', function () {
    mitMealie([
        mealieArtikel('Tofu', label: 'Kühlregal', position: 0, laeden: 'rewe'),
        mealieArtikel('Bier', label: 'Getränke', position: 1),
    ]);

    $screen = Native::visit('/')->toggle('laden-getraenkemarkt', true);

    expect(listenArtikel($screen))->toBe(['Bier']);

    // Das Gerät schickt beim zweiten Tap `false` — der Screen darf sich
    // darauf nicht verlassen und entscheidet am Schlüssel.
    $screen = $screen->toggle('laden-getraenkemarkt', false);

    expect(listenArtikel($screen))->toBe(['Tofu', 'Bier']);
    expect(ladenChips($screen)[0])->toBe(['label' => 'Alle', 'aktiv' => true]);
});

it('wechselt mit einem Tap direkt von einem Laden zum nächsten', function () {
    mitMealie([
        mealieArtikel('Tofu', label: 'Kühlregal', position: 0, laeden: 'rewe'),
        mealieArtikel('Bier', label: 'Getränke', position: 1),
    ]);

    $screen = Native::visit('/')
        ->toggle('laden-rewe', true)
        ->toggle('laden-getraenkemarkt', true);

    expect(listenArtikel($screen))->toBe(['Bier']);
});

it('nennt im Untertitel beide Zahlen, solange ein Laden filtert', function () {
    mitMealie(artikelInDreiLaeden());

    $screen = Native::visit('/');

    expect(navUntertitel($screen))->toBe('4 Artikel');

    // Von den vieren ist nur Äpfel ein Lidl-Artikel.
    expect(navUntertitel($screen->toggle('laden-lidl', true)))->toBe('1 von 4 Artikeln');
});

it('zeigt einen eigenen Leerzustand, wenn im gewählten Laden nichts ansteht', function () {
    mitMealie([mealieArtikel('Tofu', label: 'Kühlregal', laeden: 'rewe')]);

    $screen = Native::visit('/')->toggle('laden-getraenkemarkt', true);

    expect(listenAbschnitte($screen))->toBe([]);

    $screen->assertSee('Nichts für Getränkemarkt auf der Liste.')
        ->assertSee('Tippe oben auf „Alle“, um die ganze Liste zu sehen.')
        ->assertDontSee('Liste ist leer.');

    // Der Weg zurück muss stehen bleiben, sonst führt der Filter in die
    // Sackgasse.
    expect(ladenChips($screen))->toHaveCount(7);
});

it('behält den gewählten Laden über einen Tab-Wechsel hinweg', function () {
    mitVorrat(
        [vorratArtikel('Wasser (still)', label: 'Getränke')],
        [
            mealieArtikel('Tofu', label: 'Kühlregal', position: 0, laeden: 'rewe'),
            mealieArtikel('Bier', label: 'Getränke', position: 1),
        ],
    );

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
    mitMealie([
        mealieArtikel('Äpfel', label: 'Obst & Gemüse', position: 0, id: 'aepfel-1'),
        mealieArtikel('Bier', label: 'Getränke', position: 1, id: 'bier-1'),
    ]);

    $screen = Native::visit('/')
        ->toggle('laden-getraenkemarkt', true)
        ->press('alleAbhaken');

    expect(listenArtikel($screen))->toBe([]);
    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)']);

    expect(listenArtikel($screen->toggle('laden-getraenkemarkt', false)))->toBe(['Äpfel']);
});

it('holt „Rückgängig“ danach genau die abgehakten Artikel zurück', function () {
    mitMealie([
        mealieArtikel('Äpfel', label: 'Obst & Gemüse', position: 0, id: 'aepfel-1'),
        mealieArtikel('Bier', label: 'Getränke', position: 1, id: 'bier-1'),
    ]);

    $screen = Native::visit('/')
        ->toggle('laden-getraenkemarkt', true)
        ->press('alleAbhaken')
        ->press('rueckgaengigMachen');

    expect(listenArtikel($screen))->toBe(['Bier']);
    expect(abgehaktZeilen($screen))->toBe([]);
});

it('hält den gewählten Laden in der Sitzung und sonst nirgends', function () {
    mitMealie([mealieArtikel('Bier', label: 'Getränke')]);

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

    mitMealie([mealieArtikel('Äpfel', label: 'Obst & Gemüse')]);

    $screen = Native::visit('/');

    expect(listenArtikel($screen->toggle('laden-lidl', true)))->toBe(['Äpfel']);
    expect(listenArtikel($screen->toggle('laden-getraenkemarkt', true)))->toBe(['Äpfel']);
});

it('lässt einen Artikel in einer Gruppe ohne Katalog-Eintrag in jedem Filter stehen', function () {
    mitMealie([mealieArtikel('1 Packung Katzenstreu', label: 'Tierbedarf')]);

    expect(listenArtikel(Native::visit('/')->toggle('laden-rewe', true)))->toBe(['1 Packung Katzenstreu']);
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
    mitVorrat([vorratArtikel('Äpfel', label: 'Obst & Gemüse')]);

    expect(ladenChips(Native::visit('/vorrat')))->toBe([
        ['label' => 'Alle', 'aktiv' => true],
        ['label' => 'Lidl', 'aktiv' => false],
        ['label' => 'Rewe', 'aktiv' => false],
        ['label' => 'Getränkemarkt', 'aktiv' => false],
        ['label' => 'dm', 'aktiv' => false],
        ['label' => 'Rossmann', 'aktiv' => false],
        ['label' => 'Budni', 'aktiv' => false],
    ]);
});

it('zeigt im Vorrat nach einem Tap nur noch, was es im Laden gibt', function () {
    mitVorrat([
        vorratArtikel('Äpfel', label: 'Obst & Gemüse', position: 0),
        vorratArtikel('Wasser (still)', label: 'Getränke', position: 1),
        vorratArtikel('Bier', label: 'Getränke', position: 2),
    ]);

    $screen = Native::visit('/vorrat')->toggle('laden-getraenkemarkt', true);

    expect(array_column(listenAbschnitte($screen), 'ueberschrift'))->toBe(['Getränke']);
    expect(listenArtikel($screen))->toBe(['Wasser (still)', 'Bier']);
});

it('teilt die Wahl zwischen Vorrat und Einkaufen', function () {
    mitVorrat(
        [vorratArtikel('Äpfel', label: 'Obst & Gemüse')],
        [
            mealieArtikel('Tofu', label: 'Kühlregal', position: 0, laeden: 'rewe'),
            mealieArtikel('Bier', label: 'Getränke', position: 1),
        ],
    );

    $einkaufen = Native::visit('/vorrat')
        ->toggle('laden-getraenkemarkt', true)
        ->tap('Einkaufen')
        ->follow();

    expect(listenArtikel($einkaufen))->toBe(['Bier']);
    expect(ladenChips($einkaufen)[3])->toBe(['label' => 'Getränkemarkt', 'aktiv' => true]);
});

it('verbindet im Vorrat Suche und Laden mit UND', function () {
    mitVorrat([
        vorratArtikel('Wasser (still)', label: 'Getränke', position: 0),
        vorratArtikel('Wasser (Sprudel)', label: 'Getränke', position: 1),
        vorratArtikel('Äpfel', label: 'Obst & Gemüse', position: 2),
    ]);

    $screen = Native::visit('/vorrat')->call('suchen', 'Wasser');

    expect(listenArtikel($screen))->toBe(['Wasser (still)', 'Wasser (Sprudel)']);

    // Wasser gibt es im Getränkemarkt, nicht bei Lidl.
    expect(listenArtikel($screen->toggle('laden-lidl', true)))->toBe([]);
});

it('sagt beim leeren Suchergebnis dazu, dass ein Laden filtert', function () {
    mitVorrat([vorratArtikel('Wasser (still)', label: 'Getränke')]);

    Native::visit('/vorrat')
        ->toggle('laden-lidl', true)
        ->call('suchen', 'Wasser')
        ->assertSee('Keine Treffer für „Wasser“.')
        ->assertSee('Es werden nur Artikel für Lidl gezeigt.');
});

it('lässt den Hinweis auf den Laden weg, solange keiner filtert', function () {
    mitVorrat([vorratArtikel('Äpfel', label: 'Obst & Gemüse')]);

    Native::visit('/vorrat')
        ->call('suchen', 'Gibtsnicht')
        ->assertSee('Keine Treffer für „Gibtsnicht“.')
        ->assertDontSee('Es werden nur Artikel');
});

it('zeigt im Vorrat einen eigenen Leerzustand, wenn für den Laden alles auf der Liste ist', function () {
    // Das eine Getränk steht schon auf der Einkaufsliste — der Getränkemarkt
    // ist damit abgearbeitet, der Rest des Vorrats aber nicht.
    mitVorrat(
        [
            vorratArtikel('Bier', label: 'Getränke', position: 0),
            vorratArtikel('Äpfel', label: 'Obst & Gemüse', position: 1),
        ],
        [mealieArtikel('bier')],
    );

    $screen = Native::visit('/vorrat')->toggle('laden-getraenkemarkt', true);

    expect(listenAbschnitte($screen))->toBe([]);

    $screen->assertSee('Für Getränkemarkt ist alles auf der Liste.')
        ->assertSee('Tippe oben auf „Alle“, um den ganzen Vorrat zu sehen.')
        ->assertDontSee('Alles auf der Liste.');

    expect(ladenChips($screen))->toHaveCount(7);
});

it('lässt die Chips im Vorrat weg, wenn der ganze Vorrat auf der Liste steht', function () {
    mitVorrat(
        [vorratArtikel('Äpfel', label: 'Obst & Gemüse')],
        [mealieArtikel('äpfel')],
    );

    $screen = Native::visit('/vorrat');

    expect(ladenChips($screen))->toBe([]);
    $screen->assertSee('Alles auf der Liste.');
});

/*
 * Die Aufteilung selbst. Geprüft wird nicht, welcher Artikel wohin gehört —
 * das steht seit dem Umzug in Mealie —, sondern dass die Warengruppen
 * überhaupt trennen. Ohne diese Regel wandert beim Pflegen schleichend alles
 * zu „Lidl und Rewe“ zurück, und die Chips filtern nichts mehr.
 */

it('nennt zu jeder Warengruppe Läden, aber nie alle', function () {
    $mehrdeutig = [];

    foreach (app(Katalog::class)->gruppen() as $gruppe) {
        if ($gruppe->laeden === [] || count($gruppe->laeden) === count(Laden::alle())) {
            $mehrdeutig[$gruppe->id] = array_map(fn (Laden $laden) => $laden->value, $gruppe->laeden);
        }
    }

    expect($mehrdeutig)->toBe([]);
});

it('deckt mit allen Läden zusammen jede Warengruppe ab', function () {
    $gruppen = app(Katalog::class)->gruppen();

    $abgedeckt = array_values(array_unique(array_merge(...array_map(
        fn (Laden $laden) => array_map(
            fn ($gruppe) => $gruppe->id,
            array_filter($gruppen, fn ($gruppe) => in_array($laden, $gruppe->laeden, strict: true)),
        ),
        Laden::alle(),
    ))));

    $alle = array_map(fn ($gruppe) => $gruppe->id, $gruppen);

    sort($alle);
    sort($abgedeckt);

    expect($abgedeckt)->toBe($alle);
});
