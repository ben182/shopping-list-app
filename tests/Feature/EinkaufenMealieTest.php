<?php

use App\Einkaufen\Uebersicht;
use App\Einkaufen\Zeile;
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
 * `display` schreibt Mealie selbst zusammen: Menge, Einheit, Lebensmittel
 * und zum Schluss die Notiz. Wer eine Notiz mitgibt, hängt sie deshalb auch
 * hinten an `display` an — genau darauf trifft die App.
 *
 * `$laeden` ist die Ladenausnahme in `extras`, kommagetrennt wie in Mealie.
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
    ?string $lebensmittel = null,
    string $notiz = '',
    string $laeden = '',
): array {
    return [
        'id' => $id ?? 'artikel-'.md5($display),
        'display' => $display,
        'note' => $notiz,
        'food' => $lebensmittel === null ? null : [
            'id' => 'food-'.md5($lebensmittel),
            'name' => $lebensmittel,
            'pluralName' => null,
        ],
        'checked' => $abgehakt,
        'position' => $position,
        'createdAt' => $erstelltAm,
        'label' => $label === null ? null : ['id' => 'label-'.md5($label), 'name' => $label],
        // Die Ladenausnahme, die ein Artikel aus dem Vorrat mitbringt.
        'extras' => $laeden === '' ? [] : ['laeden' => $laeden],
        'recipeReferences' => array_map(
            fn (string $rezeptId) => ['recipeId' => $rezeptId, 'recipeQuantity' => 1.0],
            $rezeptIds,
        ),
    ];
}

/**
 * Die Zeilen des Abschnitts „Verknüpfte Rezepte“ — leer, wenn es ihn nicht
 * gibt.
 *
 * @return list<string>
 */
function rezeptBlock(TestableComponent $screen): array
{
    foreach (listenAbschnitte($screen) as $abschnitt) {
        if (str_starts_with((string) $abschnitt['ueberschrift'], 'Verknüpfte Rezepte')) {
            return $abschnitt['artikel'];
        }
    }

    return [];
}

/**
 * Die Überschrift des Rezeptblocks — `null`, wenn er fehlt.
 */
function rezeptBlockUeberschrift(TestableComponent $screen): ?string
{
    foreach (listenAbschnitte($screen) as $abschnitt) {
        if (str_starts_with((string) $abschnitt['ueberschrift'], 'Verknüpfte Rezepte')) {
            return $abschnitt['ueberschrift'];
        }
    }

    return null;
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

    // Zuerst die Artikel-Routen: ein späteres `Http::fake()` legt seine Regel
    // nur dahinter, und die Muster hier überschneiden sich nicht. Das erste
    // ist das Löschen, das seine IDs als Query trägt — ein ungefaktes Muster
    // ginge wirklich ins Netz. Dann die einzelne Änderung und zuletzt das
    // Bulk-Update: ohne Schrägstrich am Ende und deshalb ein eigenes Muster,
    // das die Regel davor nicht mitnimmt.
    Http::fake([
        '*/api/households/shopping/items?*' => Http::response([], $aenderungsStatus),
        '*/api/households/shopping/items/*' => Http::response([], $aenderungsStatus),
        '*/api/households/shopping/items' => Http::response([], $aenderungsStatus),
    ]);

    mealieAntwortet([...vorgemerkteArtikel(), ...$artikel], $rezepte);
}

/**
 * Die Zeilen des angepinnten Blocks „Abgehakt“ über der Tab-Leiste, samt
 * seiner Überschriftszeile — in Render-Reihenfolge, Kopf zuerst.
 *
 * @return list<array<string, mixed>>
 */
function abgehaktBlock(TestableComponent $screen): array
{
    $block = knotenMitRef($screen, 'abgehakt-block');

    if ($block === null) {
        return [];
    }

    $zeilen = [];

    $walk = function (array $node) use (&$walk, &$zeilen): void {
        if (($node['type'] ?? null) === 'list_item') {
            $zeilen[] = $node;
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk($block);

    return $zeilen;
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
 * Artikel, die aus dem Vorrat auf die Einkaufsliste gewandert sind.
 *
 * Seit der Vorrat in Mealie liegt, unterscheiden sie sich dort in nichts von
 * denen, die ein Rezept mitgebracht hat: derselbe Listeneintrag, dieselbe
 * Zeile. Die Funktion merkt sie vor; `mitMealie()` stellt sie der Liste
 * voran — die Tests rufen beides in dieser Reihenfolge auf.
 *
 * @var array<string, array{string, string}> Katalog-ID => [Name, Mealie-Label]
 */
const AUS_DEM_VORRAT = [
    'tofu' => ['Tofu', 'Fleischprodukte'],
    'salz' => ['Salz', 'Gewürze'],
    'bananen' => ['Bananen', 'Obst & Gemüse'],
    'aepfel' => ['Äpfel', 'Obst & Gemüse'],
];

function eigeneArtikel(string ...$artikelIds): void
{
    $GLOBALS['vorratsartikel'] = array_map(
        fn (string $artikelId, int $position) => mealieArtikel(
            AUS_DEM_VORRAT[$artikelId][0],
            label: AUS_DEM_VORRAT[$artikelId][1],
            position: $position,
            id: 'eigen-'.$artikelId,
        ),
        $artikelIds,
        array_keys($artikelIds),
    );
}

/**
 * Die vorgemerkten Vorratsartikel — und räumt die Vormerkung ab, damit sie
 * nicht in den nächsten Test durchschlägt.
 *
 * @return list<array<string, mixed>>
 */
function vorgemerkteArtikel(): array
{
    $artikel = $GLOBALS['vorratsartikel'] ?? [];

    $GLOBALS['vorratsartikel'] = [];

    return $artikel;
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

it('ordnet die Artikel einer Gruppe nach Mealies Reihenfolge, nicht nach ihrem Namen', function () {
    eigeneArtikel('bananen', 'aepfel');

    mitMealie([
        mealieArtikel('2 Zucchini', label: 'Gemüse', position: 3),
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', position: 2),
    ]);

    // `position` entscheidet — die beiden aus dem Vorrat stehen davor, weil
    // Mealie sie so zurückgibt, nicht weil die App sie vorzieht.
    expect(listenAbschnitte(Native::visit('/')))
        ->toBe([[
            'ueberschrift' => 'Obst & Gemüse',
            'artikel' => ['Bananen', 'Äpfel', '1 Kopf Brokkoli', '2 Zucchini'],
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

it('zeichnet eine Rezeptzeile mit leerer Checkbox, Notiz und Besteck-Icon', function () {
    mitMealie([mealieArtikel(
        '1 handvoll Koriander frisch, gehackt',
        label: 'Gemüse',
        lebensmittel: 'Koriander',
        notiz: 'frisch, gehackt',
        rezeptIds: ['rezept-1'],
    )], ['rezept-1' => 'Currysuppe']);

    Native::visit('/', platform: 'android')
        ->assertElement('list_item', fn (array $node) => ($node['props']['headline'] ?? null) === '1 handvoll Koriander'
            && ($node['props']['supporting'] ?? null) === 'frisch, gehackt'
            && ($node['props']['leading_type'] ?? null) === 'checkbox'
            && ($node['props']['leading_checked'] ?? null) === false
            && ($node['props']['trailing_icon'] ?? null) === 'restaurant'
            && ($node['props']['trailing_a11y_label'] ?? null) === 'aus einem Rezept');
});

it('schneidet nur die Notiz ab und lässt Mealies Schreibweise der Menge stehen', function () {
    mitMealie([mealieArtikel(
        '¹/₂ TL Cayennepfeffer gestrichen',
        label: 'Gewürze',
        lebensmittel: 'Cayennepfeffer',
        notiz: 'gestrichen',
    )]);

    expect(listenAbschnitte(Native::visit('/'))[0]['artikel'])->toBe(['¹/₂ TL Cayennepfeffer']);
});

it('lässt einen Artikel ohne Lebensmittel ganz stehen — sein Text steht komplett in der Notiz', function () {
    mitMealie([mealieArtikel('2 Handtücher', label: 'Gemüse', notiz: 'Handtücher')]);

    Native::visit('/')->assertElement('list_item', fn (array $node) => ($node['props']['headline'] ?? null) === '2 Handtücher'
        && ($node['props']['supporting'] ?? '') === '');
});

it('lässt eine Zeile ohne Notiz ohne Untertitel', function () {
    mitMealie([mealieArtikel('1 Bund Petersilie', label: 'Gemüse', lebensmittel: 'Petersilie')]);

    Native::visit('/')->assertElement('list_item', fn (array $node) => ($node['props']['supporting'] ?? '') === '');
});

it('führt die Rezepte der Liste in einem eigenen Abschnitt unter den Warengruppen auf', function () {
    mitMealie(
        [
            mealieArtikel('200 g Tomaten', label: 'Gemüse', rezeptIds: ['rezept-1']),
            mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', rezeptIds: ['rezept-2']),
        ],
        ['rezept-1' => 'Vegane Brokkolisuppe', 'rezept-2' => 'Pasta Arrabiata'],
    );

    $abschnitte = listenAbschnitte(Native::visit('/'));

    expect($abschnitte)->toBe([
        ['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['200 g Tomaten', '1 Kopf Brokkoli']],
        ['ueberschrift' => 'Verknüpfte Rezepte (2)', 'artikel' => ['Vegane Brokkolisuppe', 'Pasta Arrabiata']],
    ]);
});

it('nennt ein Rezept nur einmal, egal an wie vielen Artikeln es hängt', function () {
    mitMealie(
        [
            mealieArtikel('200 g Tomaten', label: 'Gemüse', rezeptIds: ['rezept-1', 'rezept-2']),
            mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', rezeptIds: ['rezept-1']),
        ],
        ['rezept-1' => 'Vegane Brokkolisuppe', 'rezept-2' => 'Pasta Arrabiata'],
    );

    expect(rezeptBlock(Native::visit('/')))->toBe(['Vegane Brokkolisuppe', 'Pasta Arrabiata']);
});

it('behält ein Rezept im Block, dessen Artikel schon abgehakt sind', function () {
    mitMealie(
        [
            mealieArtikel('200 g Tomaten', label: 'Gemüse'),
            mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', abgehakt: true, rezeptIds: ['rezept-1']),
        ],
        ['rezept-1' => 'Vegane Brokkolisuppe'],
    );

    expect(rezeptBlock(Native::visit('/')))->toBe(['Vegane Brokkolisuppe']);
});

it('lässt den Rezeptblock weg, solange kein Artikel an einem Rezept hängt', function () {
    mitMealie([mealieArtikel('1 Bund Petersilie', label: 'Gemüse')]);

    expect(rezeptBlockUeberschrift(Native::visit('/')))->toBeNull();
});

it('zeichnet die Rezeptzeilen mit Besteck-Icon und ohne Checkbox', function () {
    mitMealie(
        [mealieArtikel('200 g Tomaten', label: 'Gemüse', rezeptIds: ['rezept-1'])],
        ['rezept-1' => 'Vegane Brokkolisuppe'],
    );

    $zeile = knotenMitRef(Native::visit('/', platform: 'android'), 'rezept-'.md5('Vegane Brokkolisuppe'));

    expect($zeile['props']['headline'] ?? null)->toBe('Vegane Brokkolisuppe');
    expect($zeile['props']['leading_icon'] ?? null)->toBe('restaurant');
    expect($zeile['props']['leading_type'] ?? null)->not->toBe('checkbox');
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

    expect(rezeptBlock(Native::visit('/')))->toBe(['Pasta Arrabiata']);
});

it('gibt einer Zeile ohne Rezept kein Trailing-Icon', function () {
    eigeneArtikel('tofu');

    mitMealie([]);

    Native::visit('/', platform: 'android')
        ->assertElement('list_item', fn (array $node) => ($node['props']['headline'] ?? null) === 'Tofu'
            && ! isset($node['props']['trailing_icon']))
        ->assertMissingElement('list_item', fn (array $node) => ($node['props']['trailing_a11y_label'] ?? null) === 'aus einem Rezept');
});

it('setzt das Besteck-Icon an die Zeilen, die ein Rezept mitgebracht hat', function () {
    mitMealie(
        [mealieArtikel('2 Zucchini', label: 'Gemüse', rezeptIds: ['rezept-1'])],
        ['rezept-1' => 'Zucchinipfanne'],
    );

    Native::visit('/', platform: 'android')
        ->assertElement('list_item', fn (array $node) => ($node['props']['headline'] ?? null) === '2 Zucchini'
            && ($node['props']['trailing_a11y_label'] ?? null) === 'aus einem Rezept');
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

    $screen = Native::visit('/', platform: 'android');

    $screen->assertSee('Mealie nicht verbunden')
        ->assertSee('Einstellungen')
        ->assertElement('icon', fn (array $node) => ($node['props']['name'] ?? null) === 'info');

    // Ohne Token bleibt der Screen leer: die Liste liegt vollständig in
    // Mealie, es gibt nichts mehr, was das Gerät für sich hätte.
    expect(listenAbschnitte($screen))->toBe([]);

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

it('lässt die Ladezeile fallen, wenn Mealie nicht antwortet', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake(fn () => throw new ConnectionException('Zeitüberschreitung'));

    $screen = Native::visit('/');

    expect($screen->get('mealieLaedt'))->toBeFalse();
    $screen->assertSee('Mealie nicht erreichbar');
});

it('zeigt nur beim ersten Ladevorgang einer Sitzung die Zeile „Mealie wird geladen…“', function () {
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

    $screen->call('neuLaden');

    expect($beimErsten)->toBeTrue();

    $screen->assertDontSee('Mealie wird geladen…');

    $beimZweiten = null;
    $screen->call('neuLaden');

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

it('hängt den Block „Abgehakt“ unter die scrollende Liste statt hinein', function () {
    mitMealie([
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse'),
        mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true),
    ]);

    $screen = Native::visit('/')->tap('Abgehakt (1)');

    // Läge der Block in der Liste, scrollte er mit ihr weg — und die letzte
    // Zeile der Gruppe darüber ginge optisch in seine Überschrift über.
    $block = knotenMitRef($screen, 'abgehakt-block');

    expect($block)->not->toBeNull();
    expect(knotenTypen($block))->not->toContain('list_section');
    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)', '1 Liter Milch']);

    // Die Artikel der Liste bleiben, wo sie waren.
    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);
});

/*
 * Mit dem Block „Abgehakt“ unter der Liste ist unten schon eine Naht: dort
 * genügt die schmale Luft von 24 dp, mit der die Liste seit jeher endet.
 */
it('bleibt mit dem Block „Abgehakt“ bei der schmalen Luft am Listenende', function () {
    mitMealie([
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse'),
        mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true),
    ]);

    $luft = listenLuft(Native::visit('/'));

    expect($luft)->not->toBeNull();
    expect($luft['layout']['height'] ?? null)->toBe(24.0);
});

it('lässt am Listenende eine Zeilenhöhe Luft, sobald der Block „Abgehakt“ fehlt', function () {
    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/');

    expect(abgehaktZeilen($screen))->toBe([]);
    expect(listenLuft($screen)['layout']['height'] ?? null)->toBe(56.0);
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

it('hakt einen Mealie-Artikel auch dann ab, wenn nur seine Checkbox getroffen wird', function () {
    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', id: 'brokkoli-1')]);

    $screen = checkboxAntippen(Native::visit('/'), 'mealie-brokkoli-1');

    expect(listenAbschnitte($screen))->toBe([]);
    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)']);

    Http::assertSent(fn ($anfrage) => $anfrage->method() === 'PUT'
        && $anfrage->url() === 'https://mealie.example.test/api/households/shopping/items/brokkoli-1'
        && $anfrage['checked'] === true);
});

it('holt einen abgehakten Artikel über seine Checkbox zurück in seine Gruppe', function () {
    mitMealie([mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true, id: 'milch-1')]);

    $screen = checkboxAntippen(Native::visit('/')->tap('Abgehakt (1)'), 'abgehakt-milch-1', false);

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Kühlregal', 'artikel' => ['1 Liter Milch']]]);
    expect(abgehaktZeilen($screen))->toBe([]);

    Http::assertSent(fn ($anfrage) => $anfrage->method() === 'PUT'
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

it('nimmt einen abgehakten Artikel in den Block auf und zeigt ihn wieder im Vorrat', function () {
    mitVorrat(
        [vorratArtikel('Tofu', label: 'Kühlregal', lebensmittel: 'Tofu')],
        [mealieArtikel('200 g Tofu', label: 'Fleischprodukte', lebensmittel: 'Tofu', id: 'tofu-1')],
    );

    $screen = Native::visit('/')->tap('200 g Tofu');

    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)']);

    // Was im Wagen liegt, gehört wieder in den Vorrat — sonst stünde er nach
    // einem Einkauf leer da, bis jemand in Mealie aufräumt.
    expect(vorratZeilen(Native::visit('/vorrat')))->toBe(['Tofu']);
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
    $artikel = [...vorgemerkteArtikel(), ...$artikel];
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

it('sperrt jede Zeile, während das Banner steht — es gibt keine mehr, die ohne Mealie auskäme', function () {
    eigeneArtikel('tofu');

    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', id: 'brokkoli-1')]);

    $screen = Native::visit('/');

    $ausfall();
    $screen->press('neuLaden');

    expect(knotenMitRef($screen, 'mealie-eigen-tofu')['props']['disabled'] ?? false)->toBeTrue();

    $screen->tap('Tofu');

    // Der Stand aus dem Cache steht weiter da, unverändert.
    expect(listenAbschnitte($screen))->toBe([
        ['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']],
        ['ueberschrift' => 'Kühlregal', 'artikel' => ['Tofu']],
    ]);
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

it('stellt das Banner über die gecachte Liste, statt sie wegzunehmen', function () {
    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/');

    $ausfall();
    $screen->press('neuLaden');

    $screen->assertSee('Mealie nicht erreichbar')
        ->assertDontSee('Liste ist leer.');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);
});

it('vergisst den Cache, sobald kein Token mehr hinterlegt ist', function () {
    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    Native::visit('/');

    app(Token::class)->loeschen();
    appNeuStarten();

    $screen = Native::visit('/');

    $screen->assertSee('Mealie nicht verbunden')
        ->assertDontSee('1 Kopf Brokkoli');

    expect(listenAbschnitte($screen))->toBe([]);
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

/*
 * Ab hier: „Alles abhaken“ inklusive Mealie (EKL-010).
 */

it('zählt in der Leiste eigene und Mealie-Artikel zusammen', function () {
    eigeneArtikel('tofu', 'salz');

    mitMealie([
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse'),
        mealieArtikel('2 Zucchini', label: 'Gemüse'),
        mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true),
    ]);

    $screen = Native::visit('/')->press('alleAbhaken');

    $screen->assertNativeNotCalled('Dialog.Alert');

    expect(texteIn(rueckgaengigLeiste($screen) ?? []))->toContain('4 Artikel abgehakt');
});

it('zählt in der Leiste nur die Artikel, die wirklich abgehakt wurden', function () {
    eigeneArtikel('tofu', 'salz');

    mitMealie([mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true)]);

    $screen = Native::visit('/')->press('alleAbhaken');

    expect(texteIn(rueckgaengigLeiste($screen) ?? []))->toContain('2 Artikel abgehakt');
});

it('zählt in der Leiste auch einen einzelnen Mealie-Artikel', function () {
    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/')->press('alleAbhaken');

    expect(texteIn(rueckgaengigLeiste($screen) ?? []))->toContain('1 Artikel abgehakt');
});

it('hakt alle offenen Artikel in einem einzigen Aufruf ab', function () {
    mitMealie([
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', id: 'brokkoli-1'),
        mealieArtikel('2 Zucchini', label: 'Gemüse', id: 'zucchini-1', position: 1),
        mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true, id: 'milch-1', position: 2),
    ]);

    $screen = Native::visit('/')->press('alleAbhaken');

    expect(listenAbschnitte($screen))->toBe([]);
    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (3)']);

    Http::assertSent(fn ($anfrage) => $anfrage->method() === 'PUT'
        && $anfrage->url() === 'https://mealie.example.test/api/households/shopping/items'
        && collect($anfrage->data())->pluck('id')->all() === ['brokkoli-1', 'zucchini-1']
        && collect($anfrage->data())->pluck('checked')->all() === [true, true]
        // „restliche Felder unverändert“: Mealies eigene Darstellung geht zurück.
        && $anfrage->data()[0]['display'] === '1 Kopf Brokkoli'
        && $anfrage->data()[0]['createdAt'] === '2026-09-12T15:37:15.316035Z');
});

it('stellt die Leiste über den Block „Abgehakt“', function () {
    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/')->press('alleAbhaken');

    $refs = array_map(fn (array $knoten) => $knoten['ref'], knotenMitRefPraefix($screen, ''));

    expect(array_search('rueckgaengig-leiste', $refs, strict: true))
        ->toBeLessThan(array_search('abgehakt-block', $refs, strict: true));
});

it('holt mit „Rückgängig“ die Artikel per einem Bulk-Update zurück auf die Liste', function () {
    mitMealie([
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', id: 'brokkoli-1'),
        mealieArtikel('2 Zucchini', label: 'Gemüse', id: 'zucchini-1', position: 1),
    ]);

    $screen = Native::visit('/')->press('alleAbhaken')->press('rueckgaengigMachen');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli', '2 Zucchini']]]);
    expect(abgehaktZeilen($screen))->toBe([]);
    expect(rueckgaengigLeiste($screen))->toBeNull();

    $zurueckgeholt = collect(Http::recorded())
        ->map(fn (array $paar) => $paar[0])
        ->filter(fn ($anfrage) => $anfrage->method() === 'PUT' && collect($anfrage->data())->pluck('checked')->all() === [false, false]);

    expect($zurueckgeholt)->toHaveCount(1)
        ->and(collect($zurueckgeholt->first()->data())->pluck('id')->all())->toBe(['brokkoli-1', 'zucchini-1']);
});

it('meldet es per Toast, wenn das Bulk-Update beim Rückgängigmachen scheitert', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    // Das Abhaken darf durchgehen, erst das Zurückholen scheitert.
    Http::fake(['*/api/households/shopping/items' => Http::sequence()
        ->push([], 200)
        ->push([], 500)]);
    mealieAntwortet([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/')->press('alleAbhaken')->press('rueckgaengigMachen');

    expect(listenAbschnitte($screen))->toBe([]);
    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)']);

    $screen->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Mealie: Zurückholen fehlgeschlagen');
});

it('holt die Artikel zurück und räumt die Leiste ab, wenn das Bulk-Update scheitert', function () {
    mitMealie([
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse'),
        mealieArtikel('2 Zucchini', label: 'Gemüse', position: 1),
    ], aenderungsStatus: 500);

    $screen = Native::visit('/')->press('alleAbhaken');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli', '2 Zucchini']]]);
    expect(abgehaktZeilen($screen))->toBe([]);

    $screen->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Mealie: Abhaken fehlgeschlagen');

    // Es ist nichts passiert, was sich zurücknehmen ließe — die Artikel
    // stehen ja wieder offen da, und mit dem leeren Vorgang geht die Leiste.
    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('holt die Mealie-Artikel auch zurück, wenn das Bulk-Update in einen Timeout läuft', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake(['*/api/households/shopping/items' => fn () => throw new ConnectionException('Zeitüberschreitung')]);
    mealieAntwortet([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/')->press('alleAbhaken');

    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);

    $screen->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Mealie: Abhaken fehlgeschlagen');

    // Nichts abgehakt, nichts zurückzunehmen.
    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('rührt im Fehlerzustand nichts an: ohne Mealie lässt sich nichts abhaken', function () {
    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/');

    $ausfall();

    $screen->press('neuLaden')->press('alleAbhaken');

    expect(rueckgaengigLeiste($screen))->toBeNull();
    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);

    Http::assertNotSent(fn ($anfrage) => $anfrage->method() === 'PUT');
});

it('tut nichts, wenn im Fehlerzustand nur Mealie-Artikel offen sind', function () {
    $ausfall = mitMealieAusfall([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    $screen = Native::visit('/');

    $ausfall();

    $screen->press('neuLaden')->press('alleAbhaken');

    expect(rueckgaengigLeiste($screen))->toBeNull();
    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);
});

it('hat ohne hinterlegtes Token nichts abzuhaken', function () {
    AsyncTask::fake();
    fakeSecureStore();

    $screen = Native::visit('/')->call('alleAbhaken');

    expect(rueckgaengigLeiste($screen))->toBeNull();

    $screen->assertSee('Liste ist leer.');
});

it('räumt die Leiste beim Tipp auf den Kopf des Abgehakt-Blocks ab', function () {
    eigeneArtikel('tofu');

    mitMealie([mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true)]);

    $screen = Native::visit('/')->press('alleAbhaken')->press('abgehakteUmklappen');

    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('räumt die Leiste beim Tipp auf eine Checkbox im Abgehakt-Block ab', function () {
    eigeneArtikel('tofu');

    mitMealie([mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true, id: 'milch-1')]);

    $screen = Native::visit('/')->press('abgehakteUmklappen')->press('alleAbhaken');

    expect(rueckgaengigLeiste($screen))->not->toBeNull();

    checkboxAntippen($screen, 'abgehakt-milch-1', false);

    expect(rueckgaengigLeiste($screen))->toBeNull();
});

it('zeigt die Action „Alles abhaken“ auch dann, wenn nur ein Mealie-Artikel offen ist', function () {
    mitMealie([mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse')]);

    Native::visit('/')->assertElement('top_bar_action', fn (array $node) => ($node['props']['a11y_label'] ?? null) === 'Alles abhaken');
});

it('zeigt die Action „Alles abhaken“ nicht, wenn nur abgehakte Mealie-Artikel übrig sind', function () {
    mitMealie([mealieArtikel('1 Liter Milch', label: 'Milchprodukte', abgehakt: true)]);

    Native::visit('/')->assertMissingElement('top_bar_action', fn (array $node) => ($node['props']['a11y_label'] ?? null) === 'Alles abhaken');
});

/*
 * Ein Durchlauf über eine abgelegte Mealie-Antwort: dieselbe Datei, die eine
 * echte Instanz liefern würde, einmal quer durch Zuordnung, Reihenfolge und
 * Abgehakt-Block.
 */

/**
 * Wie `mitMealie()`, nur antwortet Mealie mit dem Inhalt einer JSON-Fixture
 * statt mit einer im Test gebauten Liste.
 */
function mitMealieFixture(string $dateiname): void
{
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    Http::fake([
        '*/api/households/shopping/items/*' => Http::response([]),
        '*/api/households/shopping/items' => Http::response([]),
        '*/api/households/shopping/lists/*' => Http::response(jsonFixture($dateiname)),
    ]);
}

it('verteilt eine abgelegte Mealie-Antwort auf Katalog-, Alias- und eigene Gruppen', function () {
    mitMealieFixture('mealie-einkaufsliste.json');

    $screen = Native::visit('/');

    // „Haushalt“ heißt wie eine Katalog-Gruppe und verschmilzt mit ihr,
    // „Tiefkühlware“ landet über die Alias-Tabelle in „Tiefkühl“ — beide an
    // ihrer Katalogposition (Anhang A: … Tiefkühl … Haushalt …). Dahinter die
    // Gruppen, die es nur wegen Mealie gibt, alphabetisch, und ganz zum
    // Schluss die Rezepte hinter der Liste.
    expect(listenAbschnitte($screen))->toBe([
        ['ueberschrift' => 'Tiefkühl', 'artikel' => ['1 Packung Erbsen']],
        ['ueberschrift' => 'Haushalt', 'artikel' => ['2 Rollen Küchenpapier']],
        ['ueberschrift' => 'Asia-Laden', 'artikel' => ['1 Glas Kimchi']],
        ['ueberschrift' => 'Sonstiges', 'artikel' => ['3 Feuerzeuge']],
        ['ueberschrift' => 'Verknüpfte Rezepte (1)', 'artikel' => ['Erbsensuppe']],
    ]);

    expect(navUntertitel($screen))->toBe('4 Artikel');

    // Der abgehakte Artikel taucht in keiner Gruppe auf, sondern nur im
    // eingeklappten Block am Ende.
    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)']);

    $screen->tap('Abgehakt (1)');

    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)', '1 Liter Hafermilch']);
});

it('erbt für Mealie-Artikel die Läden der Warengruppe, unter der sie stehen', function () {
    mitMealie([
        mealieArtikel('1 Liter Hafermilch', label: 'Kühlregal'),
        mealieArtikel('6 Flaschen Wasser', label: 'Getränke'),
    ]);

    $screen = Native::visit('/');

    expect(listenAbschnitte($screen))->toBe([
        ['ueberschrift' => 'Kühlregal', 'artikel' => ['1 Liter Hafermilch']],
        ['ueberschrift' => 'Getränke', 'artikel' => ['6 Flaschen Wasser']],
    ]);

    // Mealie kennt keine Läden — die Zeile erbt sie von ihrer Warengruppe:
    // Kühlregal gibt es in beiden Supermärkten, Getränke nur im Getränkemarkt.
    expect(listenAbschnitte($screen->toggle('laden-lidl', true)))
        ->toBe([['ueberschrift' => 'Kühlregal', 'artikel' => ['1 Liter Hafermilch']]]);

    expect(listenAbschnitte($screen->toggle('laden-getraenkemarkt', true)))
        ->toBe([['ueberschrift' => 'Getränke', 'artikel' => ['6 Flaschen Wasser']]]);
});

it('lässt einen Mealie-Artikel in einer eigenen Gruppe in jedem Filter stehen', function () {
    mitMealie([
        mealieArtikel('1 Glas Kimchi', label: 'Asia-Laden'),
        mealieArtikel('3 Feuerzeuge'),
    ]);

    // Weder „Asia-Laden“ noch „Sonstiges“ stehen im Katalog — es gibt keine
    // Läden zu erben, also bleiben beide Zeilen stehen. Ein übersehener
    // Artikel wiegt schwerer als eine Zeile zu viel.
    expect(listenAbschnitte(Native::visit('/')->toggle('laden-getraenkemarkt', true)))->toBe([
        ['ueberschrift' => 'Asia-Laden', 'artikel' => ['1 Glas Kimchi']],
        ['ueberschrift' => 'Sonstiges', 'artikel' => ['3 Feuerzeuge']],
    ]);
});

it('lässt den Block „Abgehakt“ vom Ladenfilter unberührt', function () {
    mitMealie([
        mealieArtikel('6 Flaschen Wasser', label: 'Getränke'),
        mealieArtikel('1 Liter Hafermilch', label: 'Kühlregal', abgehakt: true),
    ]);

    // Was im Wagen liegt, gehört vollständig in den Block — auch das, was
    // der gewählte Laden gar nicht führt.
    $screen = Native::visit('/')->toggle('laden-getraenkemarkt', true)->tap('Abgehakt (1)');

    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)', '1 Liter Hafermilch']);
});
