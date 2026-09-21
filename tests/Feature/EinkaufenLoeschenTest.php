<?php

use App\Mealie\Sitzung;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

/*
 * Das Aufräumen nach dem Einkauf: was im Wagen lag, fliegt endgültig von der
 * Liste. Geprüft wird am Screen — der Knopf im Block „Abgehakt“, der Dialog
 * davor und der eine DELETE-Aufruf dahinter.
 */

/** Der Löschen-Knopf im Block „Abgehakt“ — `null`, solange er nicht dasteht. */
function loeschKnopf(TestableComponent $screen): ?array
{
    return knotenMitRef($screen, 'abgehakt-loeschen');
}

/**
 * Die IDs, die ein DELETE als Query mitgeschickt hat.
 *
 * Mealie will sie wiederholt als `ids=…`; `parse_str()` behielte davon nur
 * die letzte, die Query wird deshalb von Hand zerlegt.
 *
 * @return list<string>
 */
function geloeschteIds(): array
{
    return collect(Http::recorded())
        ->map(fn (array $paar) => $paar[0])
        ->filter(fn ($anfrage) => $anfrage->method() === 'DELETE')
        ->flatMap(fn ($anfrage) => array_map(
            fn (string $paar) => urldecode(substr($paar, strlen('ids='))),
            array_filter(
                explode('&', (string) parse_url($anfrage->url(), PHP_URL_QUERY)),
                fn (string $paar) => str_starts_with($paar, 'ids='),
            ),
        ))
        ->values()
        ->all();
}

it('zeigt den Löschen-Knopf erst im aufgeklappten Block', function () {
    mitMealie([mealieArtikel('1 Liter Hafermilch', label: 'Milchprodukte', abgehakt: true)]);

    $screen = Native::visit('/');

    expect(loeschKnopf($screen))->toBeNull();

    $screen->press('abgehakteUmklappen');

    expect(loeschKnopf($screen)['props']['label'])->toBe('Artikel löschen');
});

it('zählt die abgehakten Artikel auf dem Knopf mit', function () {
    mitMealie([
        mealieArtikel('1 Liter Hafermilch', label: 'Milchprodukte', abgehakt: true),
        mealieArtikel('250 g Margarine', label: 'Milchprodukte', abgehakt: true, position: 1),
    ]);

    $screen = Native::visit('/')->press('abgehakteUmklappen');

    expect(loeschKnopf($screen)['props']['label'])->toBe('2 Artikel löschen');
});

it('fragt vor dem Löschen nach', function () {
    mitMealie([
        mealieArtikel('1 Liter Hafermilch', label: 'Milchprodukte', abgehakt: true),
        mealieArtikel('250 g Margarine', label: 'Milchprodukte', abgehakt: true, position: 1),
    ]);

    Native::visit('/')
        ->press('abgehakteUmklappen')
        ->press('abgehakteLoeschenBestaetigen')
        ->assertNativeCalled('Dialog.Alert', fn (array $params) => $params['title'] === '2 Artikel löschen?');

    expect(geloeschteIds())->toBe([]);
});

it('löscht die abgehakten Artikel nach der Bestätigung in einem einzigen Aufruf', function () {
    mitMealie([
        mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse', id: 'brokkoli-1'),
        mealieArtikel('1 Liter Hafermilch', label: 'Milchprodukte', abgehakt: true, id: 'hafermilch-1', position: 1),
        mealieArtikel('250 g Margarine', label: 'Milchprodukte', abgehakt: true, id: 'margarine-1', position: 2),
    ]);

    $screen = Native::visit('/')
        ->press('abgehakteUmklappen')
        ->press('abgehakteLoeschenBestaetigen')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Löschen']);

    expect(abgehaktZeilen($screen))->toBe([]);
    // Der offene Artikel bleibt, wo er war.
    expect(listenAbschnitte($screen))
        ->toBe([['ueberschrift' => 'Obst & Gemüse', 'artikel' => ['1 Kopf Brokkoli']]]);

    expect(geloeschteIds())->toBe(['hafermilch-1', 'margarine-1']);
});

it('lässt die Artikel nach „Abbrechen“ stehen', function () {
    mitMealie([mealieArtikel('1 Liter Hafermilch', label: 'Milchprodukte', abgehakt: true, id: 'hafermilch-1')]);

    $screen = Native::visit('/')
        ->press('abgehakteUmklappen')
        ->press('abgehakteLoeschenBestaetigen')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Abbrechen']);

    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)', '1 Liter Hafermilch']);
    expect(geloeschteIds())->toBe([]);
});

it('vergisst die gelöschten Artikel auch im Cache', function () {
    mitMealie([mealieArtikel('1 Liter Hafermilch', label: 'Milchprodukte', abgehakt: true, id: 'hafermilch-1')]);

    Native::visit('/')
        ->press('abgehakteUmklappen')
        ->press('abgehakteLoeschenBestaetigen')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Löschen']);

    expect(app(Sitzung::class)->abgehakte())->toBe([]);
});

it('holt die Artikel zurück, wenn Mealie das Löschen ablehnt', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake(['*/api/households/shopping/items*' => Http::response([], 500)]);
    mealieAntwortet([mealieArtikel('1 Liter Hafermilch', label: 'Milchprodukte', abgehakt: true, id: 'hafermilch-1')]);

    $screen = Native::visit('/')
        ->press('abgehakteUmklappen')
        ->press('abgehakteLoeschenBestaetigen')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Löschen']);

    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)', '1 Liter Hafermilch']);

    $screen->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Mealie: Löschen fehlgeschlagen');
});

it('holt die Artikel auch nach einem Timeout zurück', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake(['*/api/households/shopping/items*' => fn () => throw new ConnectionException('Zeitüberschreitung')]);
    mealieAntwortet([mealieArtikel('1 Liter Hafermilch', label: 'Milchprodukte', abgehakt: true, id: 'hafermilch-1')]);

    $screen = Native::visit('/')
        ->press('abgehakteUmklappen')
        ->press('abgehakteLoeschenBestaetigen')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Löschen']);

    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)', '1 Liter Hafermilch']);
});

it('löscht nichts, solange das Banner steht', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    // Der erste Ladevorgang füllt die Liste, der zweite scheitert — danach
    // steht das Banner über einer gecachten Liste.
    // Ein offener Artikel muss dabei sein: ohne ihn zeichnet der Screen keine
    // Liste, und mit ihr fehlte das Pull-to-Refresh, über das der zweite
    // Ladevorgang läuft.
    Http::fake(['*/api/households/shopping/lists/*' => Http::sequence()
        ->push(['listItems' => [
            mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse'),
            mealieArtikel('1 Liter Hafermilch', label: 'Milchprodukte', abgehakt: true, position: 1),
        ], 'recipeReferences' => []])
        ->pushStatus(500)]);

    $screen = Native::visit('/')
        ->press('abgehakteUmklappen')
        ->press('neuLaden')
        ->press('abgehakteLoeschenBestaetigen');

    $screen->assertNativeNotCalled('Dialog.Alert')
        ->assertNativeCalled('Dialog.Toast', fn (array $params) => str_starts_with($params['message'], 'Offline:'));

    expect(abgehaktZeilen($screen))->toBe(['Abgehakt (1)', '1 Liter Hafermilch']);
});

it('sperrt den Knopf, solange das Banner steht', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    // Ein offener Artikel muss dabei sein: ohne ihn zeichnet der Screen keine
    // Liste, und mit ihr fehlte das Pull-to-Refresh, über das der zweite
    // Ladevorgang läuft.
    Http::fake(['*/api/households/shopping/lists/*' => Http::sequence()
        ->push(['listItems' => [
            mealieArtikel('1 Kopf Brokkoli', label: 'Gemüse'),
            mealieArtikel('1 Liter Hafermilch', label: 'Milchprodukte', abgehakt: true, position: 1),
        ], 'recipeReferences' => []])
        ->pushStatus(500)]);

    $screen = Native::visit('/')->press('abgehakteUmklappen')->press('neuLaden');

    expect(loeschKnopf($screen)['props']['disabled'])->toBeTrue();
});
