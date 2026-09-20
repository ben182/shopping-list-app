<?php

use App\Liste\EigeneListe;
use App\Mealie\Token;
use Ben182\AppLifecycle\Events\AppForegrounded;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;

/*
 * Geprüft wird immer am Screen: unten ein gefakter Secure Storage und ein
 * gefaktes Mealie, oben der Wire-Tree. Dazwischen liegt alles, was die
 * Geschichte beschreibt — Laden, Zuordnen, Sortieren, Zeichnen.
 */

/**
 * Ein Artikel der Mealie-Liste in der Form, die Mealie wirklich liefert
 * (nachgesehen an der echten Instanz, nicht aus dem Code abgeleitet).
 *
 * @param  list<string>  $rezeptIds
 * @return array<string, mixed>
 */
function mealieArtikel(
    string $display,
    ?string $label = null,
    bool $abgehakt = false,
    int $position = 0,
    string $erstelltAm = '2026-09-12T15:37:15.316035Z',
    array $rezeptIds = [],
    ?string $id = null,
): array {
    return [
        'id' => $id ?? 'artikel-'.md5($display),
        'display' => $display,
        'checked' => $abgehakt,
        'position' => $position,
        'createdAt' => $erstelltAm,
        'label' => $label === null ? null : ['id' => 'label-'.md5($label), 'name' => $label],
        'recipeReferences' => array_map(
            fn (string $rezeptId) => ['recipeId' => $rezeptId, 'recipeQuantity' => 1.0],
            $rezeptIds,
        ),
    ];
}

/**
 * Lässt Mealie mit dieser Liste antworten.
 *
 * @param  list<array<string, mixed>>  $artikel
 * @param  array<string, string>  $rezepte  Rezept-ID => Rezeptname
 */
function mealieAntwortet(array $artikel, array $rezepte = []): void
{
    Http::fake([
        '*/api/households/shopping/lists/*' => Http::response([
            'id' => '00000000-0000-4000-8000-000000000000',
            'name' => 'Einkaufsliste',
            'listItems' => $artikel,
            'recipeReferences' => array_map(
                fn (string $rezeptId, string $name) => [
                    'recipeId' => $rezeptId,
                    'recipe' => ['id' => $rezeptId, 'name' => $name, 'slug' => str($name)->slug()->value()],
                ],
                array_keys($rezepte),
                $rezepte,
            ),
        ]),
    ]);
}

/**
 * Lässt Mealie auf aufeinanderfolgende Aufrufe verschieden antworten — ein
 * zweites `Http::fake()` täte das nicht, es legt seine Regel nur hinter die
 * erste und die trifft weiter zuerst.
 *
 * @param  list<array<string, mixed>>  ...$antworten
 */
function mealieAntwortetNacheinander(array ...$antworten): void
{
    $sequenz = Http::fakeSequence('*/api/households/shopping/lists/*');

    foreach ($antworten as $artikel) {
        $sequenz->push(['listItems' => $artikel, 'recipeReferences' => []]);
    }
}

/**
 * Der Normalfall dieser Geschichte: Token hinterlegt, Mealie antwortet, der
 * Netzaufruf läuft im Test inline.
 *
 * @param  list<array<string, mixed>>  $artikel
 * @param  array<string, string>  $rezepte
 */
function mitMealie(array $artikel, array $rezepte = []): void
{
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    mealieAntwortet($artikel, $rezepte);
}

/**
 * Setzt eigene Artikel auf die Liste — Vorbedingung, nicht Prüfgegenstand.
 */
function eigeneArtikel(string ...$artikelIds): void
{
    foreach ($artikelIds as $artikelId) {
        app(EigeneListe::class)->hinzufuegen($artikelId);
    }
}

it('lädt beim Öffnen des Tabs die konfigurierte Mealie-Liste und zeigt die offenen Artikel', function () {
    mitMealie([
        mealieArtikel('400 g mehligkochende Kartoffeln', label: 'Obst & Gemüse'),
        mealieArtikel('2 Dosen Kichererbsen', label: 'Konserven'),
        mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true),
    ]);

    $abschnitte = listenAbschnitte(Native::visit('/'));

    expect($abschnitte)->toBe([
        ['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['400 g mehligkochende Kartoffeln']],
        ['ueberschrift' => 'Lebensmittel', 'artikel' => ['2 Dosen Kichererbsen']],
    ]);

    Http::assertSent(fn ($anfrage) => $anfrage->url() === 'https://mealie.example.test/api/households/shopping/lists/00000000-0000-4000-8000-000000000000'
        && $anfrage->hasHeader('Authorization', 'Bearer mealie-geheim-123'));
});

it('ordnet ein Label der Gruppe zu, die genauso heißt', function () {
    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Obst & Gemüse')]);

    expect(listenAbschnitte(Native::visit('/')))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);
});

it('ordnet ein Label über die Alias-Tabelle zu', function (string $label, string $gruppe) {
    mitMealie([mealieArtikel('Irgendwas', label: $label)]);

    expect(listenAbschnitte(Native::visit('/')))
        ->toBe([['ueberschrift' => $gruppe, 'artikel' => ['Irgendwas']]]);
})->with([
    // Stichproben aus Anhang B der PRD, über alle Zielgruppen verteilt.
    'Gemüse' => ['Gemüse', 'Obst & Gemüse'],
    'Backwaren' => ['Backwaren', 'Brot & Backwaren'],
    'Milchprodukte' => ['Milchprodukte', 'Kühlregal'],
    'Tiefkühlware' => ['Tiefkühlware', 'Tiefkühl'],
    'Würzmittel' => ['Würzmittel', 'Lebensmittel'],
    'Alkohol' => ['Alkohol', 'Getränke'],
]);

it('macht aus einem unbekannten Label eine eigene Gruppe', function () {
    mitMealie([mealieArtikel('1 Packung Katzenstreu', label: 'Tierbedarf')]);

    expect(listenAbschnitte(Native::visit('/')))
        ->toBe([['ueberschrift' => 'Tierbedarf', 'artikel' => ['1 Packung Katzenstreu']]]);
});

it('sammelt Artikel ohne Label unter „Sonstiges“', function () {
    mitMealie([mealieArtikel('1 Bund Petersilie')]);

    expect(listenAbschnitte(Native::visit('/')))
        ->toBe([['ueberschrift' => 'Sonstiges', 'artikel' => ['1 Bund Petersilie']]]);
});

it('stellt die Katalog-Gruppen voran und sortiert die übrigen alphabetisch', function () {
    mitMealie([
        mealieArtikel('Zahnseide', label: 'Drogerie'),
        mealieArtikel('Katzenstreu', label: 'Tierbedarf'),
        mealieArtikel('Motoröl', label: 'Öl & Co'),
        mealieArtikel('Schrauben', label: 'Baumarkt'),
        mealieArtikel('Tomaten', label: 'Gemüse'),
    ]);

    expect(array_column(listenAbschnitte(Native::visit('/')), 'ueberschrift'))
        // Katalogreihenfolge für Obst & Gemüse und Drogerie, danach die
        // Label-Gruppen alphabetisch — „Öl & Co“ zwischen B und T.
        ->toBe(['Obst & Gemüse', 'Drogerie', 'Baumarkt', 'Öl & Co', 'Tierbedarf']);
});

it('stellt in jeder Gruppe die eigenen Artikel vor die aus Mealie', function () {
    eigeneArtikel('bananen', 'aepfel');

    mitMealie([
        mealieArtikel('2 Zucchini', label: 'Gemüse', position: 1),
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', position: 0),
    ]);

    expect(listenAbschnitte(Native::visit('/')))
        ->toBe([[
            'ueberschrift' => 'Obst & Gemüse',
            // Katalogreihenfolge in Anhang A: Äpfel vor Bananen.
            'artikel' => ['Äpfel', 'Bananen', '1 Kopf Brokkoli', '2 Zucchini'],
        ]]);
});

it('behält bei gleicher Position die Reihenfolge nach Erstellzeitpunkt', function () {
    mitMealie([
        mealieArtikel('Später erfasst', label: 'Gemüse', erstelltAm: '2026-09-12T16:00:00.000000Z'),
        mealieArtikel('Zuerst erfasst', label: 'Gemüse', erstelltAm: '2026-09-12T15:00:00.000000Z'),
    ]);

    expect(listenAbschnitte(Native::visit('/'))[0]['artikel'])
        ->toBe(['Zuerst erfasst', 'Später erfasst']);
});

it('zeichnet eine Mealie-Zeile mit leerer Checkbox, Rezeptname und Besteck-Icon', function () {
    mitMealie(
        [mealieArtikel('400 g mehligkochende Kartoffeln', label: 'Gemüse', rezeptIds: ['rezept-1'])],
        ['rezept-1' => 'Vegane Brokkolisuppe'],
    );

    Native::visit('/', platform: 'android')
        ->assertElement('list_item', fn (array $node) => ($node['props']['headline'] ?? null) === '400 g mehligkochende Kartoffeln'
            && ($node['props']['supporting'] ?? null) === 'Vegane Brokkolisuppe'
            && ($node['props']['leading_type'] ?? null) === 'checkbox'
            && ($node['props']['leading_checked'] ?? null) === false
            && ($node['props']['trailing_icon'] ?? null) === 'restaurant'
            && ($node['props']['trailing_a11y_label'] ?? null) === 'aus Mealie');
});

it('trennt mehrere Rezepte einer Zeile durch einen Mittelpunkt', function () {
    mitMealie(
        [mealieArtikel('200 g Tomaten', label: 'Gemüse', rezeptIds: ['rezept-1', 'rezept-2'])],
        ['rezept-1' => 'Vegane Brokkolisuppe', 'rezept-2' => 'Pasta Arrabiata'],
    );

    Native::visit('/')->assertElement('list_item', fn (array $node) => ($node['props']['supporting'] ?? null) === 'Vegane Brokkolisuppe · Pasta Arrabiata');
});

it('lädt einen Rezeptnamen nach, der nicht in der Listen-Antwort steht', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    Http::fake([
        '*/api/households/shopping/lists/*' => Http::response([
            'listItems' => [mealieArtikel('200 g Tomaten', label: 'Gemüse', rezeptIds: ['rezept-9'])],
            'recipeReferences' => [],
        ]),
        '*/api/recipes/rezept-9' => Http::response(['id' => 'rezept-9', 'name' => 'Pasta Arrabiata']),
    ]);

    Native::visit('/')->assertElement('list_item', fn (array $node) => ($node['props']['supporting'] ?? null) === 'Pasta Arrabiata');
});

it('lässt eine Zeile ohne Rezeptbezug ohne Untertitel', function () {
    mitMealie([mealieArtikel('1 Bund Petersilie', label: 'Gemüse')]);

    Native::visit('/')->assertElement('list_item', fn (array $node) => ($node['props']['supporting'] ?? '') === '');
});

it('gibt eigenen Zeilen kein Trailing-Icon', function () {
    eigeneArtikel('tofu');

    mitMealie([]);

    Native::visit('/', platform: 'android')
        ->assertElement('list_item', fn (array $node) => ($node['props']['headline'] ?? null) === 'Tofu'
            && ! isset($node['props']['trailing_icon']))
        ->assertMissingElement('list_item', fn (array $node) => ($node['props']['trailing_a11y_label'] ?? null) === 'aus Mealie');
});

it('zählt im Untertitel eigene und offene Mealie-Artikel zusammen', function () {
    eigeneArtikel('tofu', 'salz');

    mitMealie([
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse'),
        mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true),
    ]);

    expect(navUntertitel(Native::visit('/')))->toBe('3 Artikel');
});

it('zeigt den Leerzustand erst, wenn weder eigene noch offene Mealie-Artikel da sind', function () {
    mitMealie([mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true)]);

    Native::visit('/')
        ->assertSee('Liste ist leer.')
        ->assertMissingElement('list_item');
});

it('zeigt keinen Leerzustand, solange ein Mealie-Artikel offen ist', function () {
    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    Native::visit('/')->assertDontSee('Liste ist leer.');
});

it('weist ohne hinterlegtes Token auf die Einstellungen hin und ruft Mealie nicht auf', function () {
    AsyncTask::fake();
    fakeSecureStore();
    mealieAntwortet([]);

    eigeneArtikel('tofu');

    $screen = Native::visit('/', platform: 'android');

    $screen->assertSee('Mealie nicht verbunden')
        ->assertSee('Einstellungen')
        ->assertElement('icon', fn (array $node) => ($node['props']['name'] ?? null) === 'info');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Kühlregal', 'artikel' => ['Tofu']]]);

    Http::assertNothingSent();
});

it('öffnet aus der Hinweiszeile heraus die Einstellungen', function () {
    AsyncTask::fake();
    fakeSecureStore();
    mealieAntwortet([]);

    Native::visit('/')
        ->tap('mealie-einstellungen')
        ->assertNavigatedTo('/einstellungen');
});

it('schweigt bei einem Lesefehler des Keystores, statt zum Verbinden aufzufordern', function () {
    AsyncTask::fake();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['status' => 'unavailable', 'code' => 'INTERACTION_NOT_ALLOWED']);
    mealieAntwortet([]);

    Native::visit('/')->assertDontSee('Mealie nicht verbunden');

    Http::assertNothingSent();
});

it('zeigt die eigenen Artikel auch dann, wenn Mealie nicht antwortet', function () {
    eigeneArtikel('tofu');

    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake(fn () => throw new ConnectionException('Zeitüberschreitung'));

    $screen = Native::visit('/');

    expect(listenAbschnitte($screen))->toBe([['ueberschrift' => 'Kühlregal', 'artikel' => ['Tofu']]]);
    expect($screen->get('mealieLaedt'))->toBeFalse();
});

it('zeigt nur beim ersten Ladevorgang einer Sitzung die Zeile „Mealie wird geladen…“', function () {
    // Ein eigener Artikel, damit die Liste (und mit ihr Pull-to-Refresh) da ist.
    eigeneArtikel('tofu');

    AsyncTask::fake();
    fakeSecureStore();
    mealieAntwortet([]);

    // Ohne Token lädt der Mount nichts — der erste Ladevorgang der Sitzung
    // steht also noch aus, wenn der Screen schon da ist.
    $screen = Native::visit('/');

    app(Token::class)->speichern('mealie-geheim-123');

    $beimErsten = null;
    $beimZweiten = null;

    Http::fake(function () use ($screen, &$beimErsten, &$beimZweiten) {
        $beimErsten ??= $screen->get('mealieLaedt');
        $beimZweiten = $screen->get('mealieLaedt');

        return Http::response(['listItems' => [], 'recipeReferences' => []]);
    });

    $screen->press('neuLaden');

    expect($beimErsten)->toBeTrue();

    $screen->assertDontSee('Mealie wird geladen…');

    $beimZweiten = null;
    $screen->press('neuLaden');

    expect($beimZweiten)->toBeFalse();
});

it('zeichnet die Ladezeile mit Spinner und Text', function () {
    mitMealie([]);

    Native::visit('/')
        ->set('mealieLaedt', true)
        ->assertSee('Mealie wird geladen…')
        ->assertElement('activity_indicator');
});

it('lädt die Liste bei Pull-to-Refresh neu', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    mealieAntwortetNacheinander(
        [mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')],
        [mealieArtikel('2 Zucchini', label: 'Gemüse')],
    );

    $screen = Native::visit('/')->assertElement('list', fn (array $node) => isset($node['props']['on_refresh']));

    $screen->press('neuLaden');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['2 Zucchini']]]);
});

it('lädt die Liste neu, wenn die App in den Vordergrund zurückkehrt', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    mealieAntwortetNacheinander(
        [mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')],
        [mealieArtikel('2 Zucchini', label: 'Gemüse')],
    );

    $screen = Native::visit('/');

    $screen->emitNative(AppForegrounded::class);

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['2 Zucchini']]]);
});

it('lädt Mealie, sobald in den Einstellungen ein Token gespeichert wurde', function () {
    AsyncTask::fake();
    fakeSecureStore();
    mealieAntwortet([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $einkaufen = Native::visit('/')->assertSee('Mealie nicht verbunden');

    $einstellungen = $einkaufen->press('oeffneEinstellungen')->follow();

    $einstellungen->input('mealie-token', 'mealie-geheim-123')
        ->press('speichern')
        ->assertSee('Token hinterlegt');

    $zurueck = $einstellungen->goBack();

    expect(listenAbschnitte($zurueck))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);

    $zurueck->assertDontSee('Mealie nicht verbunden');
});

it('beschriftet die Liste mit Mealie-Zeilen und die Hinweiszeile für Screenreader', function () {
    eigeneArtikel('tofu');

    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', rezeptIds: ['rezept-1'])], ['rezept-1' => 'Suppe']);

    Native::visit('/', platform: 'android')->assertAccessible();

    AsyncTask::clearFake();
    fakeSecureStore();

    Native::visit('/', platform: 'android')->assertAccessible();
});
