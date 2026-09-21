<?php

use App\Mealie\Sitzung as Einkaufssitzung;
use App\Vorrat\Sitzung;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

/*
 * Der Vorrat kommt aus Mealie: eine zweite Einkaufsliste, die nicht
 * eingekauft, sondern gepflegt wird. Geprüft wird deshalb am Screen, mit
 * gefaktem Secure Storage und gefaktem Mealie darunter.
 */

const VORRAT_LISTE = '00000000-0000-4000-8000-000000000001';
const EINKAUFS_LISTE = '00000000-0000-4000-8000-000000000000';

/**
 * Ein Artikel der Vorratsliste in der Form, die Mealie wirklich liefert.
 *
 * Die App legt den Vorrat mit Menge 0 an — `display` ist dann nur der Name.
 * Artikel ohne Mealie-Lebensmittel (Toilettenpapier) tragen ihren Namen in
 * der Notiz, genau wie beim Tippen in Mealie.
 *
 * @return array<string, mixed>
 */
function vorratArtikel(
    string $name,
    ?string $label = null,
    int $position = 0,
    ?string $id = null,
    ?string $lebensmittel = null,
    string $laeden = '',
): array {
    $lebensmittelId = $lebensmittel === null ? null : 'food-'.md5($lebensmittel);

    return [
        'id' => $id ?? 'vorrat-'.md5($name),
        'display' => $name,
        'note' => $lebensmittel === null ? $name : '',
        'quantity' => 0.0,
        'foodId' => $lebensmittelId,
        'food' => $lebensmittelId === null ? null : ['id' => $lebensmittelId, 'name' => $lebensmittel],
        'labelId' => $label === null ? null : 'label-'.md5($label),
        'label' => $label === null ? null : ['id' => 'label-'.md5($label), 'name' => $label],
        'checked' => false,
        'position' => $position,
        'createdAt' => '2026-09-12T15:37:15.316035Z',
        'extras' => $laeden === '' ? [] : ['laeden' => $laeden],
        'recipeReferences' => [],
    ];
}

/**
 * Der Normalfall: Token hinterlegt, Mealie antwortet auf beide Listen, der
 * Netzaufruf läuft im Test inline.
 *
 * @param  list<array<string, mixed>>  $vorrat
 * @param  list<array<string, mixed>>  $einkauf
 */
function mitVorrat(array $vorrat, array $einkauf = [], int $anlegenStatus = 200): void
{
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    // Mealie kennt die Labels seiner Artikel; der Fake muss den Namen zum
    // mitgeschickten `labelId` deshalb wiederfinden können.
    $labelNamen = [];

    foreach ($vorrat as $eintrag) {
        if (isset($eintrag['label']['id'])) {
            $labelNamen[$eintrag['label']['id']] = $eintrag['label']['name'];
        }
    }

    Http::fake(function ($anfrage) use ($vorrat, &$einkauf, $anlegenStatus, $labelNamen) {
        // Das Abhaken und Zurückholen einer vorhandenen Zeile: Mealie
        // antwortet mit dem Artikel, nicht mit einer Neuanlage.
        if (str_contains($anfrage->url(), '/shopping/items') && $anfrage->method() !== 'POST') {
            // Mealie merkt sich den Haken: ein danach geladener Vorrat muss
            // den Artikel wieder zeigen.
            $geaendert = $anfrage->data();
            $geaendert = isset($geaendert['id']) ? [$geaendert] : $geaendert;

            foreach ($geaendert as $artikel) {
                foreach ($einkauf as $i => $vorhanden) {
                    if (($vorhanden['id'] ?? null) === ($artikel['id'] ?? null)) {
                        $einkauf[$i]['checked'] = $artikel['checked'] ?? false;
                    }
                }
            }

            return Http::response([]);
        }

        if (str_contains($anfrage->url(), '/shopping/items')) {
            $labelId = $anfrage['labelId'] ?? null;

            $angelegt = [
                'id' => 'neu-in-mealie',
                'display' => (string) ($anfrage['note'] ?? ''),
                'note' => (string) ($anfrage['note'] ?? ''),
                'foodId' => $anfrage['foodId'] ?? null,
                'labelId' => $labelId,
                'label' => $labelId === null ? null : ['id' => $labelId, 'name' => $labelNamen[$labelId] ?? ''],
                'checked' => false,
                'position' => count($einkauf),
                'extras' => $anfrage['extras'] ?? [],
                'recipeReferences' => [],
            ];

            // Mealie behält den Artikel — der nächste Ladevorgang muss ihn
            // wiederfinden, sonst stünde er nach einem Tab-Wechsel wieder im
            // Vorrat.
            if ($anlegenStatus < 300) {
                $einkauf[] = $angelegt;
            }

            return Http::response(['createdItems' => [$angelegt]], $anlegenStatus);
        }

        return Http::response([
            'listItems' => str_contains($anfrage->url(), VORRAT_LISTE) ? $vorrat : $einkauf,
            'recipeReferences' => [],
        ]);
    });
}

/**
 * Die Artikel aller Abschnitte in Render-Reihenfolge, ohne Überschriften.
 *
 * @return list<string>
 */
function vorratZeilen(TestableComponent $screen): array
{
    return array_merge(...array_column(listenAbschnitte($screen), 'artikel')) ?: [];
}

it('zeigt die Mealie-Vorratsliste in ihrer Reihenfolge, nach Warengruppen gegliedert', function () {
    mitVorrat([
        vorratArtikel('Äpfel', label: 'Obst & Gemüse', position: 0),
        vorratArtikel('Bananen', label: 'Obst & Gemüse', position: 1),
        vorratArtikel('Toilettenpapier', label: 'Haushalt', position: 2),
    ]);

    expect(listenAbschnitte(Native::visit('/vorrat')))->toBe([
        ['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['Äpfel', 'Bananen']],
        ['ueberschrift' => 'Haushalt', 'artikel' => ['Toilettenpapier']],
    ]);
});

it('ordnet die Gruppen nach Katalog, nicht nach Mealies Reihenfolge', function () {
    mitVorrat([
        vorratArtikel('Toilettenpapier', label: 'Haushalt', position: 0),
        vorratArtikel('Äpfel', label: 'Obst & Gemüse', position: 1),
    ]);

    expect(array_column(listenAbschnitte(Native::visit('/vorrat')), 'ueberschrift'))
        ->toBe(['Obst & Gemüse', 'Haushalt']);
});

it('faltet ein Mealie-Label über die Alias-Tabelle auf seine Warengruppe', function () {
    mitVorrat([vorratArtikel('Sojajoghurt', label: 'Milchprodukte')]);

    expect(listenAbschnitte(Native::visit('/vorrat')))
        ->toBe([['ueberschrift' => 'Kühlregal', 'artikel' => ['Sojajoghurt']]]);
});

it('gibt einem unbekannten Label eine eigene Überschrift, statt den Artikel zu verstecken', function () {
    mitVorrat([
        vorratArtikel('Äpfel', label: 'Obst & Gemüse', position: 0),
        vorratArtikel('Blumenerde', label: 'Garten', position: 1),
    ]);

    expect(array_column(listenAbschnitte(Native::visit('/vorrat')), 'ueberschrift'))
        ->toBe(['Obst & Gemüse', 'Garten']);
});

it('zeigt jeden Artikel als tappbare Zeile mit Plus-Icon', function () {
    mitVorrat([vorratArtikel('Tofu', label: 'Kühlregal', lebensmittel: 'Tofu')]);

    Native::visit('/vorrat', platform: 'android')
        ->assertElement('list_item', fn (array $node) => ($node['props']['headline'] ?? null) === 'Tofu'
            && ($node['props']['trailing_icon'] ?? null) === 'add'
            && ($node['on_press'] ?? null) !== null);
});

it('blendet einen Artikel aus, der über sein Lebensmittel schon auf der Einkaufsliste steht', function () {
    mitVorrat(
        [vorratArtikel('Tofu', label: 'Kühlregal', lebensmittel: 'Tofu')],
        [mealieArtikel('200 g Tofu', label: 'Fleischprodukte', lebensmittel: 'Tofu')],
    );

    expect(vorratZeilen(Native::visit('/vorrat')))->toBe([]);
});

it('blendet einen Artikel ohne Lebensmittel über seinen Namen aus', function () {
    mitVorrat(
        [vorratArtikel('Toilettenpapier', label: 'Haushalt')],
        [mealieArtikel('toilettenpapier')],
    );

    expect(vorratZeilen(Native::visit('/vorrat')))->toBe([]);
});

it('zeigt einen Artikel wieder im Vorrat, sobald er auf der Einkaufsliste abgehakt ist', function () {
    mitVorrat(
        [vorratArtikel('Tofu', label: 'Kühlregal', lebensmittel: 'Tofu')],
        [mealieArtikel('200 g Tofu', label: 'Fleischprodukte', lebensmittel: 'Tofu', abgehakt: true)],
    );

    expect(vorratZeilen(Native::visit('/vorrat')))->toBe(['Tofu']);
});

it('legt einen angetippten Artikel in Mealies Einkaufsliste an', function () {
    mitVorrat([vorratArtikel('Tofu', label: 'Kühlregal', lebensmittel: 'Tofu', laeden: 'rewe')]);

    Native::visit('/vorrat')->tap('Tofu');

    Http::assertSent(fn ($anfrage) => $anfrage->method() === 'POST'
        && $anfrage->url() === 'https://mealie.example.test/api/households/shopping/items'
        && $anfrage['shoppingListId'] === EINKAUFS_LISTE
        && $anfrage['foodId'] === 'food-'.md5('Tofu')
        && $anfrage['labelId'] === 'label-'.md5('Kühlregal')
        && $anfrage['extras'] === ['laeden' => 'rewe']
        && $anfrage['checked'] === false);
});

it('schickt einen Artikel ohne Lebensmittel als Notiz, damit er nicht namenlos ankommt', function () {
    mitVorrat([vorratArtikel('Toilettenpapier', label: 'Haushalt')]);

    Native::visit('/vorrat')->tap('Toilettenpapier');

    Http::assertSent(fn ($anfrage) => $anfrage->method() === 'POST'
        && $anfrage['note'] === 'Toilettenpapier'
        && $anfrage['isFood'] === false);
});

it('nimmt einen angetippten Artikel sofort aus dem Vorrat und zählt ihn auf der Liste mit', function () {
    mitVorrat([
        vorratArtikel('Äpfel', label: 'Obst & Gemüse', position: 0),
        vorratArtikel('Bananen', label: 'Obst & Gemüse', position: 1),
    ]);

    $screen = Native::visit('/vorrat')->tap('Äpfel');

    expect(vorratZeilen($screen))->toBe(['Bananen']);
    expect(navUntertitel($screen))->toBe('1 auf der Liste');
});

it('holt einen Artikel zurück und meldet es per Toast, wenn Mealie ihn nicht annimmt', function () {
    mitVorrat([vorratArtikel('Äpfel', label: 'Obst & Gemüse')], anlegenStatus: 500);

    $screen = Native::visit('/vorrat')->tap('Äpfel');

    expect(vorratZeilen($screen))->toBe(['Äpfel']);

    $screen->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Mealie: Hinzufügen fehlgeschlagen');
});

it('öffnet eine schon abgehakte Zeile wieder, statt eine zweite für dasselbe anzulegen', function () {
    mitVorrat(
        [vorratArtikel('Tofu', label: 'Kühlregal', lebensmittel: 'Tofu')],
        [mealieArtikel('200 g Tofu', label: 'Fleischprodukte', lebensmittel: 'Tofu', abgehakt: true, id: 'tofu-1')],
    );

    Native::visit('/vorrat')->tap('Tofu');

    Http::assertSent(fn ($anfrage) => $anfrage->method() === 'PUT'
        && $anfrage->url() === 'https://mealie.example.test/api/households/shopping/items/tofu-1'
        && $anfrage['checked'] === false);

    Http::assertNotSent(fn ($anfrage) => $anfrage->method() === 'POST');
});

it('zeigt nur, was es im gewählten Laden gibt — geerbt von der Warengruppe', function () {
    mitVorrat([
        vorratArtikel('Äpfel', label: 'Obst & Gemüse', position: 0),
        vorratArtikel('Bier', label: 'Getränke', position: 1),
    ]);

    $screen = Native::visit('/vorrat')->toggle('laden-getraenkemarkt', true);

    expect(vorratZeilen($screen))->toBe(['Bier']);
});

it('lässt die Ausnahme am Artikel die Läden seiner Warengruppe überschreiben', function () {
    mitVorrat([
        vorratArtikel('Sojajoghurt', label: 'Kühlregal', position: 0),
        vorratArtikel('Tofu', label: 'Kühlregal', position: 1, laeden: 'rewe'),
    ]);

    $screen = Native::visit('/vorrat')->toggle('laden-lidl', true);

    expect(vorratZeilen($screen))->toBe(['Sojajoghurt']);
});

it('zeigt einen Artikel in jedem Laden, wenn weder er noch seine Gruppe etwas sagt', function () {
    mitVorrat([vorratArtikel('Blumenerde', label: 'Garten')]);

    expect(vorratZeilen(Native::visit('/vorrat')->toggle('laden-rewe', true)))->toBe(['Blumenerde']);
});

it('filtert die Liste beim Tippen auf die Artikel, deren Name den Text enthält', function () {
    mitVorrat([
        vorratArtikel('Äpfel', label: 'Obst & Gemüse', position: 0),
        vorratArtikel('Bananen', label: 'Obst & Gemüse', position: 1),
    ]);

    $screen = Native::visit('/vorrat')->input('vorrat-suche', 'nan');

    expect(vorratZeilen($screen))->toBe(['Bananen']);
});

it('sucht ohne Rücksicht auf Groß-/Kleinschreibung, auch bei Umlauten', function () {
    mitVorrat([vorratArtikel('Äpfel', label: 'Obst & Gemüse')]);

    expect(vorratZeilen(Native::visit('/vorrat')->input('vorrat-suche', 'äPF')))->toBe(['Äpfel']);
});

it('ignoriert Leerzeichen am Anfang und Ende der Eingabe', function () {
    mitVorrat([vorratArtikel('Äpfel', label: 'Obst & Gemüse')]);

    expect(vorratZeilen(Native::visit('/vorrat')->input('vorrat-suche', '  äpfel  ')))->toBe(['Äpfel']);
});

it('zeigt ohne Treffer einen Leerzustand mit der Eingabe in Anführungszeichen', function () {
    mitVorrat([vorratArtikel('Äpfel', label: 'Obst & Gemüse')]);

    Native::visit('/vorrat')
        ->input('vorrat-suche', 'Trüffel')
        ->assertSee('Keine Treffer für „Trüffel“.');
});

it('leert mit dem Löschen-Button die Suche und zeigt wieder den ganzen Vorrat', function () {
    mitVorrat([
        vorratArtikel('Äpfel', label: 'Obst & Gemüse', position: 0),
        vorratArtikel('Bananen', label: 'Obst & Gemüse', position: 1),
    ]);

    $screen = Native::visit('/vorrat')->input('vorrat-suche', 'nan');

    expect(vorratZeilen($screen->tap('vorrat-suche-leeren')))->toBe(['Äpfel', 'Bananen']);
});

it('zeigt den Vorrat nach einem App-Neustart aus dem Cache, bevor Mealie antwortet', function () {
    mitVorrat([vorratArtikel('Äpfel', label: 'Obst & Gemüse')]);

    Native::visit('/vorrat');

    app()->forgetInstance(Sitzung::class);
    app()->forgetInstance(Einkaufssitzung::class);

    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake(fn () => throw new ConnectionException('Zeitüberschreitung'));

    expect(vorratZeilen(Native::visit('/vorrat')))->toBe(['Äpfel']);
});

it('stellt ein Banner über den Vorrat, wenn Mealie nicht antwortet', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake(fn () => throw new ConnectionException('Zeitüberschreitung'));

    Native::visit('/vorrat')->assertSee('Mealie nicht erreichbar');
});

it('lädt den Vorrat gar nicht erst, solange kein Token hinterlegt ist', function () {
    AsyncTask::fake();
    fakeSecureStore();
    Http::fake();

    Native::visit('/vorrat')->assertSee('Mealie nicht verbunden');

    Http::assertNothingSent();
});

it('sagt beim leeren Vorrat, wo er gepflegt wird', function () {
    mitVorrat([]);

    Native::visit('/vorrat')->assertSee('Er steht in Mealie in der Liste „Vorrat“ — dort wird er gepflegt.');
});

it('zeigt im Untertitel den Hinweis, solange die Einkaufsliste leer ist', function () {
    mitVorrat([vorratArtikel('Äpfel', label: 'Obst & Gemüse')]);

    expect(navUntertitel(Native::visit('/vorrat')))->toBe('Tippe auf einen Artikel zum Hinzufügen');
});

it('lässt am Listenende eine Zeilenhöhe Luft', function () {
    mitVorrat([vorratArtikel('Äpfel', label: 'Obst & Gemüse')]);

    $luft = listenLuft(Native::visit('/vorrat'));

    expect($luft)->not->toBeNull();
    expect($luft['layout']['height'] ?? null)->toBe(56.0);
});
