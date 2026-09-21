<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;

/*
 * Der Artikel von Hand: Wattepads, Blumenerde, Geschenkpapier. Geprüft wird
 * am Screen — was er anbietet, was er an Mealie schickt und was danach auf
 * der Einkaufsliste steht.
 */

/**
 * Die Labels, die Mealies Label-Endpunkt in diesen Tests kennt. „Drogerie“
 * heißt wie eine Katalog-Gruppe, „Gemüse“ landet über einen Alias unter
 * „Obst & Gemüse“, „Lieblingsregal“ gehört zu keiner.
 *
 * @return list<array{id: string, name: string}>
 */
function mealieLabels(): array
{
    return [
        ['id' => 'label-gemuese', 'name' => 'Gemüse'],
        ['id' => 'label-drogerie', 'name' => 'Drogerie'],
        ['id' => 'label-lieblingsregal', 'name' => 'Lieblingsregal'],
    ];
}

/**
 * Token, Labels und eine Mealie-Instanz, die jede Neuanlage annimmt, den
 * angelegten Artikel zurückgibt und ihn behält — so wie die echte. Das
 * Behalten ist wichtig: der Einkaufen-Screen lädt beim Zurückkommen neu und
 * sähe den Artikel sonst nie.
 *
 * @param  list<array{id: string, name: string}>  $labels
 */
function mitLabels(array $labels = [], int $anlegenStatus = 200, bool $labelsErreichbar = true): void
{
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    $liste = [];

    Http::fake(function ($anfrage) use (&$liste, $labels, $anlegenStatus, $labelsErreichbar) {
        if (str_contains($anfrage->url(), '/api/groups/labels')) {
            return $labelsErreichbar
                ? Http::response(['items' => $labels])
                : Http::response([], 500);
        }

        if (str_contains($anfrage->url(), '/shopping/items')) {
            $labelId = $anfrage['labelId'] ?? null;
            $namen = array_column($labels, 'name', 'id');

            $angelegt = [
                'id' => 'neu-in-mealie',
                'display' => (string) ($anfrage['note'] ?? ''),
                'note' => (string) ($anfrage['note'] ?? ''),
                'food' => null,
                'labelId' => $labelId,
                'label' => $labelId === null ? null : ['id' => $labelId, 'name' => $namen[$labelId] ?? ''],
                'checked' => false,
                'position' => count($liste),
                'extras' => $anfrage['extras'] ?? [],
                'recipeReferences' => [],
            ];

            if ($anlegenStatus < 300) {
                $liste[] = $angelegt;
            }

            return Http::response(['createdItems' => [$angelegt]], $anlegenStatus);
        }

        return Http::response(['listItems' => $liste, 'recipeReferences' => []]);
    });
}

/**
 * Beschriftung und Zustand der Warengruppen-Chips in Render-Reihenfolge.
 *
 * @return list<array{label: string, aktiv: bool}>
 */
function gruppenChips(TestableComponent $screen): array
{
    return array_values(array_map(
        fn (array $node) => [
            'label' => $node['props']['label'] ?? '',
            'aktiv' => (bool) ($node['props']['value'] ?? false),
        ],
        array_filter(
            knotenMitRefPraefix($screen, 'gruppe-'),
            fn (array $node) => ($node['type'] ?? null) === 'chip',
        ),
    ));
}

/** Die Nutzlast des einen POST an die Einkaufsliste. */
function angelegterArtikel(): array
{
    return collect(Http::recorded())
        ->map(fn (array $paar) => $paar[0])
        ->filter(fn ($anfrage) => $anfrage->method() === 'POST')
        ->map(fn ($anfrage) => $anfrage->data())
        ->first() ?? [];
}

it('ist über das Plus in der Top-Bar des Einkaufen-Screens zu erreichen', function () {
    mitMealie([]);

    Native::visit('/')
        ->press('oeffneHinzufuegen')
        ->assertNavigatedTo('/artikel-hinzufuegen');
});

it('zeigt den Screen als gepushten mit Titel und Zurück-Navigation', function () {
    mitLabels(mealieLabels());

    Native::visit('/artikel-hinzufuegen')
        ->assertNavTitle('Artikel hinzufügen')
        ->assertTabBarHidden()
        ->assertSee('Auf die Liste');
});

it('bietet die Katalog-Gruppen an, für die Mealie ein Label kennt — „Sonstiges“ zuerst und aktiv', function () {
    mitLabels(mealieLabels());

    expect(gruppenChips(Native::visit('/artikel-hinzufuegen')))->toBe([
        ['label' => 'Sonstiges', 'aktiv' => true],
        ['label' => 'Obst & Gemüse', 'aktiv' => false],
        ['label' => 'Drogerie', 'aktiv' => false],
    ]);
});

it('lässt ein Label ohne Katalog-Gruppe weg', function () {
    mitLabels(mealieLabels());

    expect(array_column(gruppenChips(Native::visit('/artikel-hinzufuegen')), 'label'))
        ->not->toContain('Lieblingsregal');
});

it('bleibt bei „Sonstiges“, wenn die Warengruppen nicht zu holen sind', function () {
    mitLabels(labelsErreichbar: false);

    $screen = Native::visit('/artikel-hinzufuegen');

    expect(gruppenChips($screen))->toBe([['label' => 'Sonstiges', 'aktiv' => true]]);

    $screen->assertSee('Die Warengruppen aus Mealie sind gerade nicht zu haben — der Artikel landet unter „Sonstiges“.');
});

it('bietet jeden Laden als Chip an, „Überall“ zuerst und aktiv', function () {
    mitLabels(mealieLabels());

    expect(ladenChips(Native::visit('/artikel-hinzufuegen')))->toBe([
        ['label' => 'Überall', 'aktiv' => true],
        ['label' => 'Lidl', 'aktiv' => false],
        ['label' => 'Rewe', 'aktiv' => false],
        ['label' => 'Getränkemarkt', 'aktiv' => false],
        ['label' => 'dm', 'aktiv' => false],
        ['label' => 'Rossmann', 'aktiv' => false],
        ['label' => 'Budni', 'aktiv' => false],
    ]);
});

it('legt den getippten Artikel mit Warengruppe und Läden in Mealie an', function () {
    mitLabels(mealieLabels());

    Native::visit('/artikel-hinzufuegen')
        ->input('artikel-name', 'Wattepads')
        ->toggle('gruppe-label-drogerie', true)
        ->toggle('laden-dm', true)
        ->toggle('laden-rossmann', true)
        ->press('hinzufuegen');

    expect(angelegterArtikel())->toMatchArray([
        'note' => 'Wattepads',
        'isFood' => false,
        'checked' => false,
        'labelId' => 'label-drogerie',
        'extras' => ['laeden' => 'dm,rossmann'],
    ]);
});

it('legt einen Artikel ohne Warengruppe und ohne Laden an', function () {
    mitLabels(mealieLabels());

    Native::visit('/artikel-hinzufuegen')
        ->input('artikel-name', 'Blumenerde')
        ->press('hinzufuegen');

    $artikel = angelegterArtikel();

    expect($artikel)->toMatchArray(['note' => 'Blumenerde', 'isFood' => false])
        ->and($artikel)->not->toHaveKey('labelId')
        ->and($artikel)->not->toHaveKey('extras');
});

it('nimmt einen zweiten Tap auf den aktiven Laden-Chip wieder heraus', function () {
    mitLabels(mealieLabels());

    $screen = Native::visit('/artikel-hinzufuegen')
        ->input('artikel-name', 'Wattepads')
        ->toggle('laden-dm', true)
        ->toggle('laden-dm', false);

    expect(ladenChips($screen)[0])->toBe(['label' => 'Überall', 'aktiv' => true]);

    $screen->press('hinzufuegen');

    expect(angelegterArtikel())->not->toHaveKey('extras');
});

it('führt der Chip „Überall“ auf keine Ladenauswahl zurück', function () {
    mitLabels(mealieLabels());

    Native::visit('/artikel-hinzufuegen')
        ->input('artikel-name', 'Wattepads')
        ->toggle('laden-dm', true)
        ->toggle('laden-ueberall', true)
        ->press('hinzufuegen');

    expect(angelegterArtikel())->not->toHaveKey('extras');
});

it('geht nach dem Anlegen zurück und stellt den Artikel auf die Einkaufsliste', function () {
    mitLabels(mealieLabels());

    $hinzufuegen = Native::visit('/')->press('oeffneHinzufuegen')->follow();

    $hinzufuegen->input('artikel-name', 'Wattepads')
        ->toggle('gruppe-label-drogerie', true)
        ->press('hinzufuegen');

    expect(listenAbschnitte($hinzufuegen->goBack()))
        ->toBe([['ueberschrift' => 'Drogerie', 'artikel' => ['Wattepads']]]);
});

it('legt ohne Namen nichts an', function () {
    mitLabels(mealieLabels());

    Native::visit('/artikel-hinzufuegen')
        ->input('artikel-name', '   ')
        ->press('hinzufuegen')
        ->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Bitte einen Artikel eingeben');

    expect(angelegterArtikel())->toBe([]);
});

it('sperrt den Knopf, solange nichts im Feld steht', function () {
    mitLabels(mealieLabels());

    $screen = Native::visit('/artikel-hinzufuegen');

    expect(knotenMitRef($screen, 'artikel-anlegen')['props']['disabled'])->toBeTrue();

    $screen->input('artikel-name', 'Wattepads');

    expect(knotenMitRef($screen, 'artikel-anlegen')['props']['disabled'] ?? false)->toBeFalse();
});

it('meldet es per Toast, wenn Mealie die Neuanlage ablehnt, und bleibt stehen', function () {
    mitLabels(mealieLabels(), anlegenStatus: 500);

    $screen = Native::visit('/artikel-hinzufuegen')
        ->input('artikel-name', 'Wattepads')
        ->press('hinzufuegen');

    $screen->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Mealie: Hinzufügen fehlgeschlagen');

    // Das Getippte steht noch im Feld — ein zweiter Versuch kostet keine
    // neue Eingabe.
    expect(knotenMitRef($screen, 'artikel-name')['props']['value'])->toBe('Wattepads');
});

it('meldet auch einen Timeout per Toast', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');
    Http::fake([
        '*/api/groups/labels*' => Http::response(['items' => mealieLabels()]),
        '*/api/households/shopping/items*' => fn () => throw new ConnectionException('Zeitüberschreitung'),
    ]);

    Native::visit('/artikel-hinzufuegen')
        ->input('artikel-name', 'Wattepads')
        ->press('hinzufuegen')
        ->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Mealie: Hinzufügen fehlgeschlagen');
});

it('zeigt ohne Token statt der Felder den Weg zu den Einstellungen', function () {
    fakeSecureStore();

    $screen = Native::visit('/artikel-hinzufuegen');

    $screen->assertSee('Mealie nicht verbunden.')
        ->assertSee('Hinterlege in den Einstellungen ein Token, dann lassen sich hier Artikel anlegen.');

    expect(knotenMitRef($screen, 'artikel-anlegen'))->toBeNull();
});
