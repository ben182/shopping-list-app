<?php

use App\Mealie\Sitzung as Mealiesitzung;
use App\Wochenplan\Cache as Wochenplancache;
use App\Wochenplan\Sitzung as Wochenplansitzung;
use Ben182\AppLifecycle\Events\AppForegrounded;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

/*
 * Geprüft wird am Screen: unten ein gefakter Secure Storage und ein gefaktes
 * Mealie, oben der Wire-Tree. Die erwarteten Kalenderwochen und Datumsspannen
 * stammen aus dem Kalender, nicht aus Carbon-Aufrufen im Test — sonst rechnete
 * der Test dasselbe wie der Code und könnte ihm nie widersprechen.
 */

/** Der Text der Wochen-Navigation, so wie ihn das Gerät zeichnen würde. */
function wochenText(TestableComponent $screen): ?string
{
    $knoten = knotenMitRef($screen, 'woche-text');

    return $knoten['children'][0]['props']['text'] ?? null;
}

it('öffnet mit der Kalenderwoche des heutigen Tages', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    expect(wochenText(Native::visit('/wochenplan')))->toBe('KW 39 · 21.09.–27.09.');
});

it('beschriftet die Pfeile für Screenreader', function () {
    $screen = Native::visit('/wochenplan');

    expect(knotenMitRef($screen, 'woche-zurueck')['props']['a11y_label'] ?? null)->toBe('Vorherige Woche')
        ->and(knotenMitRef($screen, 'woche-vor')['props']['a11y_label'] ?? null)->toBe('Nächste Woche');
});

it('blättert mit den Pfeilen eine Woche zurück und wieder vor', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    $screen = Native::visit('/wochenplan')->press('vorherigeWoche');

    expect(wochenText($screen))->toBe('KW 38 · 14.09.–20.09.');

    expect(wochenText($screen->press('naechsteWoche')->press('naechsteWoche')))
        ->toBe('KW 40 · 28.09.–04.10.');
});

it('springt bei einem Tap auf den Wochen-Text zur aktuellen Woche zurück', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    $screen = Native::visit('/wochenplan')
        ->press('vorherigeWoche')
        ->press('vorherigeWoche')
        ->press('aktuelleWoche');

    expect(wochenText($screen))->toBe('KW 39 · 21.09.–27.09.');
});

/**
 * Ein Wochenplan-Eintrag in der Form, die Mealie wirklich liefert.
 *
 * @return array<string, mixed>
 */
function mealplanEintrag(
    string $datum,
    string $typ,
    ?string $rezept = null,
    string $titel = '',
    string $text = '',
    ?string $bild = 'min-original.webp',
    ?string $id = null,
): array {
    $rezeptId = $rezept === null ? null : 'rezept-'.md5($rezept);

    return [
        'id' => $id ?? 'eintrag-'.md5($datum.$typ.$rezept.$titel),
        'date' => $datum,
        'entryType' => $typ,
        'title' => $titel,
        'text' => $text,
        'recipeId' => $rezeptId,
        'recipe' => $rezept === null ? null : [
            'id' => $rezeptId,
            'name' => $rezept,
            'slug' => str($rezept)->slug()->value(),
            'image' => $bild,
        ],
    ];
}

/**
 * Der Normalfall: Token hinterlegt, Mealie antwortet mit diesen Einträgen,
 * der Netzaufruf läuft im Test inline.
 *
 * @param  list<array<string, mixed>>  $eintraege
 */
function mitWochenplan(array $eintraege = []): void
{
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    Http::fake([
        '*/api/households/mealplans*' => Http::response([
            'page' => 1,
            'per_page' => 100,
            'total' => count($eintraege),
            'items' => $eintraege,
        ]),
    ]);
}

/**
 * Der Inhalt der Wochenplan-Liste in Render-Reihenfolge — Überschriften und
 * Zeilen gemischt, genau so, wie das Gerät sie zeichnen würde.
 *
 * @return list<array{typ: string, text: ?string, farbe: ?string, knoten: array<string, mixed>}>
 */
function wochenplanInhalt(TestableComponent $screen): array
{
    $inhalt = [];

    $walk = function (array $node) use (&$walk, &$inhalt): void {
        if (($node['type'] ?? null) === 'list') {
            foreach ($node['children'] ?? [] as $kind) {
                $text = $kind['props']['text'] ?? $kind['props']['headline'] ?? null;

                // Der Abstandhalter am Listenende trägt keinen Text und ist
                // kein Inhalt — er gehört nicht in diese Aufzählung.
                if ($text === null) {
                    continue;
                }

                $inhalt[] = [
                    'typ' => $kind['type'] ?? '',
                    'text' => $text,
                    'farbe' => $kind['props']['color'] ?? $kind['props']['headline_color'] ?? null,
                    'knoten' => $kind,
                ];
            }

            return;
        }

        foreach ($node['children'] ?? [] as $kind) {
            $walk($kind);
        }
    };

    $walk($screen->tree());

    return $inhalt;
}

/**
 * Die Zeile mit dieser Headline — der Knoten, nicht nur seine Props: `on_press`
 * steht auf Knotenebene.
 *
 * @return array<string, mixed>|null
 */
function wochenplanZeile(TestableComponent $screen, string $headline): ?array
{
    foreach (wochenplanInhalt($screen) as $zeile) {
        if ($zeile['typ'] === 'list_item' && $zeile['text'] === $headline) {
            return $zeile['knoten'];
        }
    }

    return null;
}

/** Nur die Tagesüberschriften, in Render-Reihenfolge. */
function tagesUeberschriften(TestableComponent $screen): array
{
    return array_values(array_map(
        fn (array $zeile) => $zeile['text'],
        array_filter(wochenplanInhalt($screen), fn (array $zeile) => $zeile['typ'] === 'text'),
    ));
}

it('zeigt für jeden der sieben Tage eine Überschrift und hebt den heutigen hervor', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan();

    $screen = Native::visit('/wochenplan');

    expect(tagesUeberschriften($screen))->toBe([
        'Montag, 21.09.',
        'Dienstag, 22.09.',
        'Mittwoch, 23.09. · Heute',
        'Donnerstag, 24.09.',
        'Freitag, 25.09.',
        'Samstag, 26.09.',
        'Sonntag, 27.09.',
    ]);

    $ueberschriften = array_values(array_filter(wochenplanInhalt($screen), fn (array $z) => $z['typ'] === 'text'));

    // Indigo aus der PRD, nicht aus der Konfiguration gelesen.
    expect($ueberschriften[2]['farbe'])->toBe('#4F46E5')
        ->and($ueberschriften[0]['farbe'])->not->toBe('#4F46E5');
});

it('färbt das Datum des heutigen Tags in der gewählten Akzentfarbe', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan();

    // Erst in den Einstellungen tippen, dann hierher zurück — genau der Weg,
    // den der Nutzer nimmt.
    Native::visit('/einstellungen')->press('akzentfarbe-orange');

    $ueberschriften = array_values(array_filter(
        wochenplanInhalt(Native::visit('/wochenplan')),
        fn (array $z) => $z['typ'] === 'text',
    ));

    // Orange aus der PRD-Palette, nicht aus dem Enum gelesen.
    expect($ueberschriften[2]['farbe'])->toBe('#C2410C');
});

it('fragt Mealie nach genau der gewählten Woche', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan();

    Native::visit('/wochenplan')->press('vorherigeWoche');

    Http::assertSent(fn ($anfrage) => str_contains($anfrage->url(), 'start_date=2026-09-21')
        && str_contains($anfrage->url(), 'end_date=2026-09-27')
        && str_contains($anfrage->url(), 'perPage=100'));

    Http::assertSent(fn ($anfrage) => str_contains($anfrage->url(), 'start_date=2026-09-14')
        && str_contains($anfrage->url(), 'end_date=2026-09-20'));
});

it('zeigt „Nichts geplant“ unter einem Tag ohne Einträge', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan([mealplanEintrag('2026-09-21', 'dinner', 'Lasagne')]);

    $inhalt = wochenplanInhalt(Native::visit('/wochenplan'));

    expect($inhalt[0]['text'])->toBe('Montag, 21.09.')
        ->and($inhalt[1]['text'])->toBe('Lasagne')
        ->and($inhalt[2]['text'])->toBe('Dienstag, 22.09.')
        ->and($inhalt[3]['text'])->toBe('Nichts geplant')
        ->and($inhalt[3]['typ'])->toBe('list_item')
        ->and($inhalt[3]['farbe'])->not->toBeNull();
});

/*
 * 56 dp ist die Höhe einer Listenzeile aus der PRD, nicht aus dem Code
 * abgelesen — der Sonntag soll beim Durchscrollen frei über der Tab-Leiste
 * stehen.
 */
it('lässt unter dem Sonntag eine Zeilenhöhe Luft', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan([mealplanEintrag('2026-09-27', 'dinner', 'Lasagne')]);

    $screen = Native::visit('/wochenplan');
    $luft = listenLuft($screen);

    expect(wochenplanInhalt($screen)[13]['text'] ?? null)->toBe('Lasagne')
        ->and($luft)->not->toBeNull()
        ->and($luft['layout']['height'] ?? null)->toBe(56.0)
        // Ohne eigene Fläche und ohne Inhalt: der Hintergrund steht durch.
        ->and($luft['style'] ?? [])->toBe([])
        ->and($luft['children'] ?? [])->toBe([]);
});

it('zeichnet einen Rezept-Eintrag mit deutschem Mahlzeitentyp, Rezeptname und Rezeptbild', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan([mealplanEintrag('2026-09-23', 'lunch', 'Kürbissuppe')]);

    $zeile = wochenplanZeile(Native::visit('/wochenplan'), 'Kürbissuppe');

    expect($zeile['props']['overline'] ?? null)->toBe('Mittag')
        ->and($zeile['props']['headline'] ?? null)->toBe('Kürbissuppe')
        ->and($zeile['props']['leading_type'] ?? null)->toBe('image')
        ->and($zeile['props']['leading_value'] ?? null)
        ->toBe('https://mealie.example.test/api/media/recipes/rezept-'.md5('Kürbissuppe').'/images/min-original.webp');
});

it('übersetzt jeden Mahlzeitentyp ins Deutsche', function (string $typ, string $label) {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan([mealplanEintrag('2026-09-23', $typ, 'Rezept X')]);

    $zeile = wochenplanZeile(Native::visit('/wochenplan'), 'Rezept X');

    expect($zeile['props']['overline'] ?? null)->toBe($label);
})->with([
    ['breakfast', 'Frühstück'],
    ['lunch', 'Mittag'],
    ['dinner', 'Abend'],
    ['side', 'Beilage'],
    ['snack', 'Snack'],
    ['drink', 'Getränk'],
    ['dessert', 'Dessert'],
]);

it('setzt statt eines fehlenden Rezeptbildes ein Platzhalter-Icon', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan([mealplanEintrag('2026-09-23', 'dinner', 'Ofengemüse', bild: null)]);

    $zeile = wochenplanZeile(Native::visit('/wochenplan', platform: 'android'), 'Ofengemüse');

    expect($zeile['props']['leading_type'] ?? null)->toBe('icon')
        ->and($zeile['props']['leading_value'] ?? null)->toBe('restaurant');
});

it('zeigt einen Eintrag ohne Rezept mit Titel und Text und ohne Bild', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan([mealplanEintrag('2026-09-23', 'dinner', titel: 'Essen gehen', text: 'Bei Luigi um 19 Uhr')]);

    $zeile = wochenplanZeile(Native::visit('/wochenplan'), 'Essen gehen');

    expect($zeile['props']['overline'] ?? null)->toBe('Abend')
        ->and($zeile['props']['headline'] ?? null)->toBe('Essen gehen')
        ->and($zeile['props']['supporting'] ?? null)->toBe('Bei Luigi um 19 Uhr')
        ->and($zeile['props']['leading_type'] ?? null)->toBeNull()
        ->and($zeile['on_press'] ?? null)->toBeNull();
});

it('sortiert die Einträge eines Tages nach Mahlzeitentyp', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan([
        mealplanEintrag('2026-09-23', 'dessert', 'Tiramisu'),
        mealplanEintrag('2026-09-23', 'drink', 'Limonade'),
        mealplanEintrag('2026-09-23', 'snack', 'Nüsse'),
        mealplanEintrag('2026-09-23', 'side', 'Salat'),
        mealplanEintrag('2026-09-23', 'dinner', 'Lasagne'),
        mealplanEintrag('2026-09-23', 'lunch', 'Suppe'),
        mealplanEintrag('2026-09-23', 'breakfast', 'Müsli'),
    ]);

    $zeilen = array_values(array_map(
        fn (array $zeile) => $zeile['text'],
        array_filter(wochenplanInhalt(Native::visit('/wochenplan')), fn (array $z) => $z['typ'] === 'list_item'),
    ));

    // Der Mittwoch ist der dritte Tag; vor ihm stehen zwei leere Tage.
    expect(array_slice($zeilen, 2, 7))
        ->toBe(['Müsli', 'Suppe', 'Lasagne', 'Salat', 'Nüsse', 'Limonade', 'Tiramisu']);
});

it('öffnet die Rezeptseite im System-Browser', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan([mealplanEintrag('2026-09-23', 'dinner', 'Rote Linsen Dal')]);

    Native::visit('/wochenplan')
        ->tap('Rote Linsen Dal')
        ->assertNativeCalled(
            'Browser.Open',
            fn (array $params) => $params['url'] === 'https://mealie.example.test/g/home/r/rote-linsen-dal',
        );
});

it('zeigt ohne Token einen Leerzustand mit dem Weg zu den Einstellungen', function () {
    AsyncTask::fake();
    fakeSecureStore();
    Http::fake(['*/api/households/mealplans*' => Http::response(['items' => []])]);

    $screen = Native::visit('/wochenplan', platform: 'android');

    expect(tagesUeberschriften($screen))->toBe([])
        ->and(knotenMitRef($screen, 'wochenplan-einstellungen')['props']['label'] ?? null)->toBe('Zu den Einstellungen')
        ->and(knotenMitRef($screen, 'listenende'))->toBeNull();

    $screen->assertElement('text', fn (array $node) => ($node['props']['text'] ?? null) === 'Mealie nicht verbunden')
        ->assertElement('icon', fn (array $node) => ($node['props']['name'] ?? null) === 'calendar_month');

    Http::assertNothingSent();

    $screen->tap('Zu den Einstellungen')->assertNavigatedTo('/einstellungen');
});

it('zeigt beim Laden einer Woche ohne Daten einen zentrierten Spinner statt der Tage', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan([mealplanEintrag('2026-09-23', 'dinner', 'Lasagne')]);

    $screen = Native::visit('/wochenplan');

    // Die geladene Woche bleibt beim Neuladen stehen, statt zu blinken.
    $screen->set('laedt', true);

    expect(tagesUeberschriften($screen))->toHaveCount(7)
        ->and(knotenMitRef($screen, 'wochenplan-spinner'))->toBeNull();

    // Eine Woche, von der die App noch nichts weiß, zeigt dagegen den Spinner.
    $screen->set('montag', '2026-10-05');

    expect(tagesUeberschriften($screen))->toBe([])
        ->and(knotenMitRef($screen, 'wochenplan-spinner')['props']['a11y_label'] ?? null)
        ->toBe('Wochenplan wird geladen');
});

it('lädt die Woche beim Zurückkehren der App in den Vordergrund und per Pull-to-Refresh neu', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplan();

    $screen = Native::visit('/wochenplan');

    Http::assertSentCount(1);

    $screen->emitNative(AppForegrounded::class);

    Http::assertSentCount(2);

    $screen->press('neuLaden');

    Http::assertSentCount(3);
});

/*
 * Ab hier: Wochenplan-Cache und Fehlerzustand (EKL-012).
 */

/** Alles, was die App vom Wochenplan im Arbeitsspeicher hält, vergessen — der App-Neustart. */
function wochenplanNeuStarten(): void
{
    app()->forgetInstance(Wochenplansitzung::class);
}

/**
 * Mealie antwortet je Woche mit den Einträgen unter ihrem Montag — bis der
 * zurückgegebene Schalter auf Ausfall gestellt wird: `$ausfall()` für den
 * Ausfall, `$ausfall(false)` zurück.
 *
 * @param  array<string, list<array<string, mixed>>>  $wochen  Montag (`Y-m-d`) => Einträge
 * @param  ?int  $status  HTTP-Status des Ausfalls; ohne Status ein Netzfehler
 */
function wochenplanAntwortetDannNicht(array $wochen, ?int $status = null): Closure
{
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    $ausfall = false;

    Http::fake(function (Request $anfrage) use ($wochen, $status, &$ausfall) {
        if ($ausfall) {
            if ($status === null) {
                throw new ConnectionException('Zeitüberschreitung');
            }

            return Http::response([], $status);
        }

        parse_str((string) parse_url($anfrage->url(), PHP_URL_QUERY), $abfrage);

        return Http::response(['items' => $wochen[$abfrage['start_date'] ?? ''] ?? []]);
    });

    return function (bool $aus = true) use (&$ausfall): void {
        $ausfall = $aus;
    };
}

it('zeigt nach einem App-Neustart die gecachte Woche, obwohl Mealie nicht mehr antwortet', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    $ausfall = wochenplanAntwortetDannNicht([
        '2026-09-21' => [mealplanEintrag('2026-09-23', 'dinner', 'Lasagne')],
    ]);

    Native::visit('/wochenplan');

    wochenplanNeuStarten();
    $ausfall();

    expect(wochenplanZeile(Native::visit('/wochenplan'), 'Lasagne'))->not->toBeNull();
});

it('hält je Woche einen eigenen Stand und zeigt ihn beim Wochenwechsel sofort', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    $ausfall = wochenplanAntwortetDannNicht([
        '2026-09-21' => [mealplanEintrag('2026-09-23', 'dinner', 'Lasagne')],
        '2026-09-28' => [mealplanEintrag('2026-09-30', 'dinner', 'Chili sin Carne')],
    ]);

    Native::visit('/wochenplan')->press('naechsteWoche');

    wochenplanNeuStarten();
    $ausfall();

    $screen = Native::visit('/wochenplan');

    expect(wochenplanZeile($screen, 'Lasagne'))->not->toBeNull()
        ->and(wochenplanZeile($screen, 'Chili sin Carne'))->toBeNull();

    $screen->press('naechsteWoche');

    expect(wochenplanZeile($screen, 'Chili sin Carne'))->not->toBeNull()
        ->and(wochenplanZeile($screen, 'Lasagne'))->toBeNull()
        ->and(knotenMitRef($screen, 'wochenplan-spinner'))->toBeNull();
});

it('ersetzt beim erneuten Laden derselben Woche den gespeicherten Stand', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    $aufruf = 0;
    $ausfall = false;

    Http::fake(function () use (&$aufruf, &$ausfall) {
        if ($ausfall) {
            throw new ConnectionException('Zeitüberschreitung');
        }

        $aufruf++;

        return Http::response(['items' => [
            mealplanEintrag('2026-09-23', 'dinner', $aufruf === 1 ? 'Lasagne' : 'Chili sin Carne'),
        ]]);
    });

    Native::visit('/wochenplan')->press('neuLaden');

    wochenplanNeuStarten();
    $ausfall = true;

    $screen = Native::visit('/wochenplan');

    expect(wochenplanZeile($screen, 'Chili sin Carne'))->not->toBeNull()
        ->and(wochenplanZeile($screen, 'Lasagne'))->toBeNull();
});

it('ersetzt die gezeigten Einträge, sobald ein Neuladen gelingt', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    $aufruf = 0;

    Http::fake(function () use (&$aufruf) {
        $aufruf++;

        return Http::response(['items' => [
            mealplanEintrag('2026-09-23', 'dinner', $aufruf === 1 ? 'Lasagne' : 'Ofengemüse'),
        ]]);
    });

    $screen = Native::visit('/wochenplan')->press('neuLaden');

    expect(wochenplanZeile($screen, 'Ofengemüse'))->not->toBeNull()
        ->and(wochenplanZeile($screen, 'Lasagne'))->toBeNull();
});

/** Alle `ref`s des Baums in Render-Reihenfolge — so steht „unter“ im Wire-Tree. */
function refReihenfolge(TestableComponent $screen): array
{
    $refs = [];

    $walk = function (array $node) use (&$walk, &$refs): void {
        if (isset($node['ref'])) {
            $refs[] = $node['ref'];
        }

        foreach ($node['children'] ?? [] as $kind) {
            $walk($kind);
        }
    };

    $walk($screen->tree());

    return $refs;
}

it('zeigt bei einem Netzfehler das Banner mit dem Stand und behält die gecachten Einträge', function () {
    CarbonImmutable::setTestNow('2026-09-23 14:05:00');

    $ausfall = wochenplanAntwortetDannNicht([
        '2026-09-21' => [mealplanEintrag('2026-09-23', 'dinner', 'Lasagne')],
    ]);

    $screen = Native::visit('/wochenplan');

    $ausfall();
    $screen->press('neuLaden');

    $screen->assertSee('Mealie nicht erreichbar · Stand 14:05')
        ->assertSee('Erneut versuchen');

    expect(wochenplanZeile($screen, 'Lasagne'))->not->toBeNull();
});

it('nennt im Banner auch das Datum, wenn der Stand von einem anderen Tag ist', function () {
    CarbonImmutable::setTestNow('2026-09-22 18:30:00');

    $ausfall = wochenplanAntwortetDannNicht([
        '2026-09-21' => [mealplanEintrag('2026-09-23', 'dinner', 'Lasagne')],
    ]);

    $screen = Native::visit('/wochenplan');

    CarbonImmutable::setTestNow('2026-09-23 08:00:00');
    $ausfall();
    $screen->press('neuLaden');

    $screen->assertSee('Mealie nicht erreichbar · Stand 22.09. 18:30');
});

it('hängt das Banner unter die Wochen-Navigation und zeichnet es mit Warn-Icon', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    $ausfall = wochenplanAntwortetDannNicht([
        '2026-09-21' => [mealplanEintrag('2026-09-23', 'dinner', 'Lasagne')],
    ]);

    $screen = Native::visit('/wochenplan', platform: 'android');

    $ausfall();
    $screen->press('neuLaden');

    $refs = refReihenfolge($screen);

    expect(array_search('woche-vor', $refs, true))
        ->toBeLessThan(array_search('wochenplan-banner-aktion', $refs, true));

    $screen->assertElement('icon', fn (array $node) => ($node['props']['name'] ?? null) === 'warning'
        && ($node['props']['a11y_label'] ?? null) === 'Warnung');

    expect(knotenMitRef($screen, 'wochenplan-banner-aktion')['props']['label'] ?? null)
        ->toBe('Erneut versuchen');
});

it('lädt aus dem Banner heraus neu und nimmt es weg, sobald Mealie wieder antwortet', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    $ausfall = wochenplanAntwortetDannNicht([
        '2026-09-21' => [mealplanEintrag('2026-09-23', 'dinner', 'Lasagne')],
    ]);

    $screen = Native::visit('/wochenplan');

    $ausfall();
    $screen->press('neuLaden');
    $screen->assertSee('Mealie nicht erreichbar');

    $ausfall(false);
    $screen->tap('wochenplan-banner-aktion');

    $screen->assertDontSee('Mealie nicht erreichbar');
    expect(wochenplanZeile($screen, 'Lasagne'))->not->toBeNull();
});

it('meldet bei HTTP 401 ein ungültiges Token und führt aus dem Banner in die Einstellungen', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    $ausfall = wochenplanAntwortetDannNicht([
        '2026-09-21' => [mealplanEintrag('2026-09-23', 'dinner', 'Lasagne')],
    ], status: 401);

    $screen = Native::visit('/wochenplan');

    $ausfall();
    $screen->press('neuLaden');

    $screen->assertSee('Mealie-Token ungültig')
        ->assertDontSee('Stand')
        ->tap('wochenplan-banner-aktion')
        ->assertNavigatedTo('/einstellungen');
});

it('zeigt für eine Woche ohne Cache das Banner und darunter einen Leerzustand', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake(fn () => throw new ConnectionException('Zeitüberschreitung'));

    $screen = Native::visit('/wochenplan', platform: 'android');

    $screen->assertSee('Mealie nicht erreichbar')
        ->assertDontSee('Stand')
        ->assertSee('Wochenplan konnte nicht geladen werden');

    expect(tagesUeberschriften($screen))->toBe([])
        ->and(knotenMitRef($screen, 'wochenplan-fehler-icon')['props']['name'] ?? null)->toBe('warning')
        ->and(knotenMitRef($screen, 'listenende'))->toBeNull();

    $refs = refReihenfolge($screen);

    expect(array_search('wochenplan-banner-aktion', $refs, true))
        ->toBeLessThan(array_search('wochenplan-fehler-icon', $refs, true));
});

it('behält beim Wochenwechsel die gecachte Woche und zeigt nur der leeren den Fehler-Leerzustand', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');

    $ausfall = wochenplanAntwortetDannNicht([
        '2026-09-21' => [mealplanEintrag('2026-09-23', 'dinner', 'Lasagne')],
    ]);

    $screen = Native::visit('/wochenplan');

    $ausfall();
    $screen->press('naechsteWoche');

    $screen->assertSee('Wochenplan konnte nicht geladen werden');

    $screen->press('vorherigeWoche');

    $screen->assertDontSee('Wochenplan konnte nicht geladen werden');
    expect(wochenplanZeile($screen, 'Lasagne'))->not->toBeNull();
});

/*
 * Ein Durchlauf über eine abgelegte Mealie-Antwort: dieselbe Datei, die eine
 * echte Instanz für diese Woche liefern würde, einmal quer durch Tage,
 * Mahlzeitentypen und Sortierung.
 */

/** Wie `mitWochenplan()`, nur kommen die Einträge aus einer JSON-Fixture. */
function mitWochenplanFixture(string $dateiname): void
{
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    Http::fake(['*/api/households/mealplans*' => Http::response(jsonFixture($dateiname))]);
}

it('baut aus einer abgelegten Mealie-Antwort die ganze Woche', function () {
    CarbonImmutable::setTestNow('2026-09-23 10:00:00');
    mitWochenplanFixture('mealie-wochenplan.json');

    $screen = Native::visit('/wochenplan');

    expect(tagesUeberschriften($screen))->toBe([
        'Montag, 21.09.',
        'Dienstag, 22.09.',
        'Mittwoch, 23.09. · Heute',
        'Donnerstag, 24.09.',
        'Freitag, 25.09.',
        'Samstag, 26.09.',
        'Sonntag, 27.09.',
    ]);

    // Die Fixture führt den Mittwoch in Mealies Reihenfolge (Dessert, Mittag,
    // Frühstück); auf dem Schirm steht er nach Mahlzeitentyp sortiert. Die
    // vier Tage ohne Eintrag bekommen je eine Zeile „Nichts geplant“.
    expect(array_column(wochenplanInhalt($screen), 'text'))->toBe([
        'Montag, 21.09.', 'Lasagne',
        'Dienstag, 22.09.', 'Nichts geplant',
        'Mittwoch, 23.09. · Heute', 'Müsli mit Beeren', 'Kürbissuppe', 'Tiramisu',
        'Donnerstag, 24.09.', 'Nichts geplant',
        'Freitag, 25.09.', 'Nichts geplant',
        'Samstag, 26.09.', 'Nichts geplant',
        'Sonntag, 27.09.', 'Essen gehen',
    ]);

    expect(wochenplanZeile($screen, 'Müsli mit Beeren')['props']['overline'] ?? null)->toBe('Frühstück')
        ->and(wochenplanZeile($screen, 'Kürbissuppe')['props']['overline'] ?? null)->toBe('Mittag')
        ->and(wochenplanZeile($screen, 'Tiramisu')['props']['overline'] ?? null)->toBe('Dessert')
        ->and(wochenplanZeile($screen, 'Lasagne')['props']['overline'] ?? null)->toBe('Abend')
        ->and(wochenplanZeile($screen, 'Essen gehen')['props']['supporting'] ?? null)->toBe('Bei Luigi um 19 Uhr');
});

/*
 * Ab hier: das Aufräumen des Wochenplan-Caches (FEIN-006).
 */

/**
 * Eine Woche in den Zwischenspeicher legen, ohne sie zu laden — ein Stand,
 * den der Nutzer irgendwann einmal aufgeschlagen hat. Über den Screen ist so
 * ein Cache nicht herzustellen: jedes Laden räumt ja gerade auf.
 */
function wochenplanImCache(string $montag, string $datum, string $rezept): void
{
    app(Wochenplancache::class)->speichern($montag, [[
        'id' => 'gecacht-'.md5($rezept),
        'datum' => $datum,
        'typ' => 'dinner',
        'rezeptName' => $rezept,
        'rezeptSlug' => 'gecacht-'.md5($rezept),
        'rezeptId' => null,
        'hatBild' => false,
        'titel' => '',
        'text' => '',
    ]]);
}

/** Vom Montag der aktuellen Kalenderwoche aus blättern; negativ heißt zurück. */
function wochenplanBlaettern(TestableComponent $screen, int $wochen): TestableComponent
{
    $screen->press('aktuelleWoche');

    for ($schritt = 0; $schritt < abs($wochen); $schritt++) {
        $screen->press($wochen < 0 ? 'vorherigeWoche' : 'naechsteWoche');
    }

    return $screen;
}

it('löscht beim Laden die Wochen, die mehr als vier Wochen von der aktuellen entfernt liegen', function () {
    // Heute ist der 20.09.2026 (KW 38, Montag der 14.09.).
    CarbonImmutable::setTestNow('2026-09-20 10:00:00');

    wochenplanImCache('2026-07-20', '2026-07-22', 'Sommersalat');
    wochenplanImCache('2026-08-17', '2026-08-19', 'Ratatouille');
    wochenplanImCache('2026-09-21', '2026-09-23', 'Kürbissuppe');
    wochenplanImCache('2026-11-02', '2026-11-04', 'Grünkohl');

    $ausfall = wochenplanAntwortetDannNicht([
        '2026-09-14' => [mealplanEintrag('2026-09-16', 'dinner', 'Lasagne')],
    ]);

    Native::visit('/wochenplan');

    wochenplanNeuStarten();
    $ausfall();

    $screen = Native::visit('/wochenplan');

    // Acht Wochen zurück (20.07.) und sieben nach vorn (02.11.): gelöscht.
    expect(wochenplanZeile(wochenplanBlaettern($screen, -8), 'Sommersalat'))->toBeNull();
    $screen->assertSee('Wochenplan konnte nicht geladen werden');

    expect(wochenplanZeile(wochenplanBlaettern($screen, 7), 'Grünkohl'))->toBeNull();
    $screen->assertSee('Wochenplan konnte nicht geladen werden');

    // Vier Wochen zurück (17.08.) und eine nach vorn (21.09.): geblieben.
    expect(wochenplanZeile(wochenplanBlaettern($screen, -4), 'Ratatouille'))->not->toBeNull()
        ->and(wochenplanZeile(wochenplanBlaettern($screen, 1), 'Kürbissuppe'))->not->toBeNull();
});

it('behält die gerade geladene Woche, auch wenn sie weit außerhalb des Fensters liegt', function () {
    CarbonImmutable::setTestNow('2026-09-20 10:00:00');

    $ausfall = wochenplanAntwortetDannNicht([
        '2026-10-19' => [mealplanEintrag('2026-10-21', 'dinner', 'Kartoffelgratin')],
        '2026-11-02' => [mealplanEintrag('2026-11-04', 'dinner', 'Grünkohl')],
    ]);

    // Sieben Wochen nach vorn geblättert (02.11.); unterwegs wird jede Woche
    // geladen und gecacht, darunter der 19.10.
    wochenplanBlaettern(Native::visit('/wochenplan'), 7);

    wochenplanNeuStarten();
    $ausfall();

    $screen = Native::visit('/wochenplan');

    expect(wochenplanZeile(wochenplanBlaettern($screen, 7), 'Grünkohl'))->not->toBeNull();

    // Der 19.10. lag beim letzten Laden außerhalb des Fensters und war nicht
    // die aufgeschlagene Woche.
    expect(wochenplanZeile(wochenplanBlaettern($screen, 5), 'Kartoffelgratin'))->toBeNull();
    $screen->assertSee('Wochenplan konnte nicht geladen werden');
});

it('räumt nach einem fehlgeschlagenen Laden nichts auf', function () {
    CarbonImmutable::setTestNow('2026-09-20 10:00:00');

    wochenplanImCache('2026-07-20', '2026-07-22', 'Sommersalat');

    $ausfall = wochenplanAntwortetDannNicht(['2026-09-14' => []]);
    $ausfall();

    Native::visit('/wochenplan');

    wochenplanNeuStarten();

    expect(wochenplanZeile(wochenplanBlaettern(Native::visit('/wochenplan'), -8), 'Sommersalat'))
        ->not->toBeNull();
});

it('lässt beim Aufräumen die gecachte Einkaufsliste in derselben Tabelle in Ruhe', function () {
    CarbonImmutable::setTestNow('2026-09-20 10:00:00');

    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    $ausfall = false;

    Http::fake(function (Request $anfrage) use (&$ausfall) {
        if ($ausfall) {
            throw new ConnectionException('Zeitüberschreitung');
        }

        return str_contains($anfrage->url(), '/mealplans')
            ? Http::response(['items' => []])
            : Http::response(jsonFixture('mealie-einkaufsliste.json'));
    });

    Native::visit('/');
    Native::visit('/wochenplan');

    app()->forgetInstance(Mealiesitzung::class);
    wochenplanNeuStarten();
    $ausfall = true;

    Native::visit('/')->assertSee('1 Glas Kimchi');
});
