<?php

use App\Einkaufen\Uebersicht;
use App\Einkaufen\Zeile;
use App\Liste\EigeneListe;
use App\Mealie\Sitzung;
use App\Mealie\Token;
use Ben182\AppLifecycle\Events\AppForegrounded;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

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
function mitMealie(array $artikel, array $rezepte = [], int $aenderungsStatus = 200): void
{
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    // Zuerst die Artikel-Route: ein späteres `Http::fake()` legt seine Regel
    // nur dahinter, und die Muster hier überschneiden sich nicht.
    Http::fake(['*/api/households/shopping/items/*' => Http::response([], $aenderungsStatus)]);

    mealieAntwortet($artikel, $rezepte);
}

/**
 * Die Zeilen, die direkt in der Liste stehen statt in einem Abschnitt — der
 * Block „Abgehakt“ am Ende, samt seiner Überschriftszeile.
 *
 * @return list<array<string, mixed>>
 */
function abgehaktBlock(TestableComponent $screen): array
{
    $liste = null;

    $walk = function (array $node) use (&$walk, &$liste): void {
        if (($node['type'] ?? null) === 'list') {
            $liste ??= $node;

            return;
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk($screen->tree());

    return array_values(array_filter(
        $liste['children'] ?? [],
        fn (array $knoten) => ($knoten['type'] ?? null) === 'list_item',
    ));
}

/**
 * Die Headlines des Blocks „Abgehakt“ in Render-Reihenfolge.
 *
 * @return list<string>
 */
function abgehaktZeilen(TestableComponent $screen): array
{
    return array_map(
        fn (array $knoten) => $knoten['props']['headline'] ?? '',
        abgehaktBlock($screen),
    );
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

    $screen = Native::visit('/')->assertSee('Liste ist leer.');

    // Der Artikel steht nur noch im eingeklappten Abschnitt „Abgehakt“ —
    // sonst käme man nie wieder an ihn heran.
    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)']);
    $screen->assertDontSee('1 Liter Milch');
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

it('nimmt einen angetippten Mealie-Artikel sofort aus seiner Gruppe und hakt ihn in Mealie ab', function () {
    mitMealie([
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', id: 'brokkoli-1'),
        mealieArtikel('2 Zucchini', label: 'Gemüse', id: 'zucchini-1'),
    ]);

    $screen = Native::visit('/');

    expect(navUntertitel($screen))->toBe('2 Artikel');

    $screen->tap('1 Kopf Brokkoli');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['2 Zucchini']]]);
    expect(navUntertitel($screen))->toBe('1 Artikel');

    Http::assertSent(fn ($anfrage) => $anfrage->method() === 'PUT'
        && $anfrage->url() === 'https://mealie.example.test/api/households/shopping/items/brokkoli-1'
        && $anfrage['checked'] === true
        // „restliche Felder unverändert“: Mealies eigene Darstellung geht zurück.
        && $anfrage['display'] === '1 Kopf Brokkoli'
        && $anfrage['createdAt'] === '2026-09-12T15:37:15.316035Z');
});

it('sammelt abgehakte Mealie-Artikel eingeklappt am Ende der Liste', function () {
    mitMealie([
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse'),
        mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true),
        mealieArtikel('250 g Butter', label: 'Milchprodukte', abgehakt: true),
    ]);

    $screen = Native::visit('/');

    // Eingeklappt: nur die Überschrift, keine Zeilen.
    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (2)']);

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);
});

it('lässt den Abschnitt „Abgehakt“ weg, solange nichts abgehakt ist', function () {
    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    expect(abgehaktZeilen(Native::visit('/')))->toBe([]);
});

it('klappt den Abschnitt „Abgehakt“ auf und wieder zu', function () {
    mitMealie([
        mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true, position: 1),
        mealieArtikel('250 g Butter', label: 'Milchprodukte', abgehakt: true, position: 0),
    ]);

    $screen = Native::visit('/', platform: 'android');

    expect(knotenMitRef($screen, 'abgehakt-kopf')['props']['trailing_icon'])->toBe('expand_more');

    $screen->tap('Abgehakt (2)');

    // Ohne Gruppierung und in Mealies Reihenfolge (Position 0 vor Position 1).
    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (2)', '250 g Butter', '1 Liter Milch']);
    expect(knotenMitRef($screen, 'abgehakt-kopf')['props']['trailing_icon'])->toBe('expand_less');

    $screen->tap('Abgehakt (2)');

    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (2)']);
});

it('zeichnet eine abgehakte Zeile mit gesetztem Haken und gedämpfter Schrift', function () {
    mitMealie([mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true)]);

    $screen = Native::visit('/')->tap('Abgehakt (1)');

    $zeile = abgehaktBlock($screen)[1]['props'];

    expect($zeile['leading_type'])->toBe('checkbox');
    expect($zeile['leading_checked'])->toBeTrue();
    // Der gedämpfte Theme-Wert der hellen Darstellung aus config/native-ui.php.
    expect($zeile['headline_color'])->toBe('#475569');
});

it('behält den Auf-/Zu-Zustand des Abschnitts über einen Tab-Wechsel hinweg', function () {
    mitMealie([mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true)]);

    Native::visit('/')->tap('Abgehakt (1)');

    // Ein zweiter Besuch mountet den Screen neu — wie der Wechsel zurück auf
    // den Einkaufen-Tab.
    expect(abgehaktZeilen(Native::visit('/')))->toBe(['Abgehakt (1)', '1 Liter Milch']);
});

it('holt eine angetippte abgehakte Zeile zurück in ihre Gruppe und meldet es Mealie', function () {
    mitMealie([mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true, id: 'milch-1')]);

    $screen = Native::visit('/')->tap('Abgehakt (1)')->tap('1 Liter Milch');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Kühlregal', 'artikel' => ['1 Liter Milch']]]);
    expect(abgehaktZeilen($screen))->toBe([]);

    Http::assertSent(fn ($anfrage) => $anfrage->method() === 'PUT'
        && $anfrage->url() === 'https://mealie.example.test/api/households/shopping/items/milch-1'
        && $anfrage['checked'] === false);
});

it('holt einen Artikel zurück und meldet es per Toast, wenn Mealie das Abhaken ablehnt', function () {
    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')], aenderungsStatus: 500);

    $screen = Native::visit('/')->tap('1 Kopf Brokkoli');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);
    expect(abgehaktZeilen($screen))->toBe([]);

    $screen->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Mealie: Änderung fehlgeschlagen');
});

it('hakt einen Artikel wieder ab, wenn das Zurückholen in einen Timeout läuft', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake(['*/api/households/shopping/items/*' => fn () => throw new ConnectionException('Zeitüberschreitung')]);
    mealieAntwortet([mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true)]);

    $screen = Native::visit('/')->tap('Abgehakt (1)')->tap('1 Liter Milch');

    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)', '1 Liter Milch']);
    expect(listenAbschnitte($screen))->toBe([]);

    $screen->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Mealie: Änderung fehlgeschlagen');
});

it('hält eigene Artikel aus dem Abschnitt „Abgehakt“ heraus und schickt sie weiter in den Vorrat', function () {
    eigeneArtikel('tofu');

    mitMealie([mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true)]);

    $screen = Native::visit('/')->tap('Abgehakt (1)')->tap('Tofu');

    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)', '1 Liter Milch']);

    $vorrat = collect(listenAbschnitte(Native::visit('/vorrat')))
        ->firstWhere('ueberschrift', 'Kühlregal')['artikel'];

    expect($vorrat)->toContain('Tofu');
});

/*
 * Ab hier: Cache und Fehlerzustand (EKL-009).
 */

/**
 * Lässt Mealie antworten oder ausfallen, je nachdem, wie der zurückgegebene
 * Schalter steht — `$ausfall()` für den Ausfall, `$ausfall(false)` zurück.
 * Ein zweites `Http::fake()` täte das nicht: es legt seine Regel nur hinter
 * die erste, und die trifft weiter zuerst.
 *
 * @param  list<array<string, mixed>>  $artikel
 * @param  ?int  $status  HTTP-Status des Ausfalls; ohne Status ein Netzfehler
 */
function mealieAntwortetDannNicht(array $artikel, ?int $status = null): Closure
{
    $ausfall = false;

    Http::fake(function () use ($artikel, $status, &$ausfall) {
        if (! $ausfall) {
            return Http::response(['listItems' => $artikel, 'recipeReferences' => []]);
        }

        if ($status === null) {
            throw new ConnectionException('Zeitüberschreitung');
        }

        return Http::response([], $status);
    });

    return function (bool $aus = true) use (&$ausfall): void {
        $ausfall = $aus;
    };
}

/**
 * Wie `mitMealie()`, nur mit einem Ausfall in der Hinterhand.
 *
 * @param  list<array<string, mixed>>  $artikel
 */
function mitMealieAusfall(array $artikel, ?int $status = null): Closure
{
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    return mealieAntwortetDannNicht($artikel, $status);
}

/** Alles, was die App im Arbeitsspeicher hält, vergessen — der App-Neustart. */
function appNeuStarten(): void
{
    app()->forgetInstance(Sitzung::class);
}

it('zeigt nach einem App-Neustart die gecachten Mealie-Artikel, obwohl Mealie nicht mehr antwortet', function () {
    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    Native::visit('/');

    appNeuStarten();
    $ausfall();

    expect(listenAbschnitte(Native::visit('/')))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);
});

it('zeigt bei einem Netzfehler das Banner mit dem Stand des letzten Ladens und behält die Artikel', function () {
    CarbonImmutable::setTestNow('2026-09-20 14:05:00');

    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/');

    $ausfall();
    $screen->press('neuLaden');

    $screen->assertSee('Mealie nicht erreichbar · Stand 14:05')
        ->assertSee('Erneut versuchen');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);
});

it('nennt im Banner auch das Datum, wenn der Stand von einem anderen Tag ist', function () {
    CarbonImmutable::setTestNow('2026-09-19 18:30:00');

    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/');

    CarbonImmutable::setTestNow('2026-09-20 08:00:00');
    $ausfall();
    $screen->press('neuLaden');

    $screen->assertSee('Mealie nicht erreichbar · Stand 19.09. 18:30');
});

it('zeigt dasselbe Banner, wenn Mealie mit einem Serverfehler antwortet', function () {
    CarbonImmutable::setTestNow('2026-09-20 09:07:00');

    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')], status: 500);

    $screen = Native::visit('/');

    $ausfall();
    $screen->press('neuLaden');

    $screen->assertSee('Mealie nicht erreichbar · Stand 09:07');
});

it('zeichnet das Banner mit Warn-Icon und Text-Button', function () {
    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/', platform: 'android');

    $ausfall();
    $screen->press('neuLaden');

    $screen->assertElement('icon', fn (array $node) => ($node['props']['name'] ?? null) === 'warning'
        && ($node['props']['a11y_label'] ?? null) === 'Warnung');

    expect(knotenMitRef($screen, 'mealie-banner-aktion')['props']['label'] ?? null)
        ->toBe('Erneut versuchen');
});

it('meldet bei HTTP 401 ein ungültiges Token und führt aus dem Banner in die Einstellungen', function () {
    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')], status: 401);

    $screen = Native::visit('/');

    $ausfall();
    $screen->press('neuLaden');

    $screen->assertSee('Mealie-Token ungültig')
        ->assertDontSee('Stand')
        ->tap('mealie-banner-aktion')
        ->assertNavigatedTo('/einstellungen');
});

it('sperrt die Mealie-Zeilen, solange das Banner steht, und sagt beim Tap warum', function () {
    $ausfall = mitMealieAusfall([
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', id: 'brokkoli-1'),
        mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true, id: 'milch-1'),
    ]);

    $screen = Native::visit('/')->tap('Abgehakt (1)');

    $ausfall();
    $screen->press('neuLaden');

    expect(knotenMitRef($screen, 'mealie-brokkoli-1')['props']['disabled'] ?? null)->toBeTrue();
    expect(knotenMitRef($screen, 'abgehakt-milch-1')['props']['disabled'] ?? null)->toBeTrue();

    $screen->press("mealieUmschalten('brokkoli-1')");

    $screen->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Offline: Mealie-Artikel können gerade nicht geändert werden');

    // Der Artikel steht noch da, wo er stand, und Mealie hat nichts gehört.
    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);

    Http::assertNotSent(fn ($anfrage) => $anfrage->method() === 'PUT');
});

it('lässt eigene Artikel bedienbar, während das Banner steht', function () {
    eigeneArtikel('tofu');

    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', id: 'brokkoli-1')]);

    $screen = Native::visit('/');

    $ausfall();
    $screen->press('neuLaden');

    expect(knotenMitRef($screen, 'einkaufen-tofu')['props']['disabled'] ?? false)->toBeFalse();

    $screen->tap('Tofu');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);
});

it('nimmt das Banner wieder weg, sobald ein Neuladen gelingt', function () {
    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', id: 'brokkoli-1')]);

    $screen = Native::visit('/');

    $ausfall();
    $screen->press('neuLaden');
    $screen->assertSee('Mealie nicht erreichbar');

    $ausfall(false);
    $screen->press('neuLaden');

    $screen->assertDontSee('Mealie nicht erreichbar');
    expect(knotenMitRef($screen, 'mealie-brokkoli-1')['props']['disabled'] ?? false)->toBeFalse();
});

it('nimmt das Banner auch weg, wenn die App in den Vordergrund zurückkehrt und Mealie wieder da ist', function () {
    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/');

    $ausfall();
    $screen->press('neuLaden');
    $screen->assertSee('Mealie nicht erreichbar');

    $ausfall(false);
    $screen->emitNative(AppForegrounded::class);

    $screen->assertDontSee('Mealie nicht erreichbar');
});

it('zeigt ohne Cache nur das Banner und darunter den Leerzustand', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake(fn () => throw new ConnectionException('Zeitüberschreitung'));

    $screen = Native::visit('/');

    $screen->assertSee('Mealie nicht erreichbar')
        ->assertDontSee('Stand')
        ->assertSee('Liste ist leer.');

    expect(listenAbschnitte($screen))->toBe([]);
});

it('zeigt ohne Cache das Banner über den eigenen Artikeln', function () {
    eigeneArtikel('tofu');

    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake(fn () => throw new ConnectionException('Zeitüberschreitung'));

    $screen = Native::visit('/');

    $screen->assertSee('Mealie nicht erreichbar')
        ->assertDontSee('Liste ist leer.');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Kühlregal', 'artikel' => ['Tofu']]]);
});

it('vergisst den Cache, sobald kein Token mehr hinterlegt ist', function () {
    eigeneArtikel('tofu');

    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    Native::visit('/');

    app(Token::class)->loeschen();
    appNeuStarten();

    $screen = Native::visit('/');

    $screen->assertSee('Mealie nicht verbunden')
        ->assertDontSee('1 Kopf Brokkoli');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Kühlregal', 'artikel' => ['Tofu']]]);
});

it('hat die gecachten Artikel schon auf dem Schirm, während die neue Antwort noch unterwegs ist', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    $aufruf = 0;
    $waehrendDesZweitenLadens = null;

    Http::fake(function () use (&$aufruf, &$waehrendDesZweitenLadens) {
        $aufruf++;

        if ($aufruf === 2) {
            // Was der Screen in diesem Moment zeichnen würde: der Cache,
            // noch vor der Antwort, die gleich zurückkommt.
            $waehrendDesZweitenLadens = array_map(
                fn (Zeile $zeile) => $zeile->text,
                app(Uebersicht::class)->abschnitte()[0]->zeilen ?? [],
            );
        }

        return Http::response([
            'listItems' => [$aufruf === 1
                ? mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')
                : mealieArtikel('2 Zucchini', label: 'Gemüse')],
            'recipeReferences' => [],
        ]);
    });

    Native::visit('/');

    appNeuStarten();

    $screen = Native::visit('/');

    expect($waehrendDesZweitenLadens)->toBe(['1 Kopf Brokkoli']);
    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['2 Zucchini']]]);
});
