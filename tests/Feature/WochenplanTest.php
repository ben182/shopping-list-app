<?php

use Ben182\AppLifecycle\Events\AppForegrounded;
use Carbon\CarbonImmutable;
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
                $inhalt[] = [
                    'typ' => $kind['type'] ?? '',
                    'text' => $kind['props']['text'] ?? $kind['props']['headline'] ?? null,
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
        ->and(knotenMitRef($screen, 'wochenplan-einstellungen')['props']['label'] ?? null)->toBe('Zu den Einstellungen');

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
