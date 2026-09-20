<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\FakeBridge;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Beide Fakes sind statischer Zustand im Paket und überleben sonst den Test,
 * in dem sie gesetzt wurden.
 */
afterEach(function (): void {
    FakeBridge::disable();
    AsyncTask::clearFake();
});

/**
 * Labels der nativen Tab-Leiste in Render-Reihenfolge.
 *
 * `assertHasTab()` prüft nur Existenz; für „genau diese drei Tabs in dieser
 * Reihenfolge“ braucht es die Liste. Sie kommt aus dem publizierten Wire-Tree,
 * also aus dem, was das Gerät tatsächlich zeichnen würde.
 *
 * @return list<string>
 */
function tabLabels(TestableComponent $screen): array
{
    $labels = [];

    $walk = function (array $node) use (&$walk, &$labels): void {
        if (($node['type'] ?? null) === 'bottom_nav_item' && isset($node['props']['label'])) {
            $labels[] = $node['props']['label'];
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk($screen->tree());

    return $labels;
}

/**
 * Alle Farbpaare, die der Baum ans Gerät schickt: pro Knoten die helle Farbe
 * und ihre dunkle Entsprechung — Hintergründe aus `style.bg_color` /
 * `props.dark_bg_color`, Text- und Icon-Farben aus `props.color` /
 * `props.dark_color`. Fehlt die dunkle Hälfte, hat jemand eine feste Farbe
 * statt einer Theme-Klasse benutzt.
 *
 * @return list<array{hell: string, dunkel: ?string}>
 */
function farbPaare(array $tree): array
{
    $paare = [];

    $walk = function (array $node) use (&$walk, &$paare): void {
        $hintergrund = $node['style']['bg_color'] ?? null;
        if ($hintergrund !== null) {
            $paare[] = ['hell' => $hintergrund, 'dunkel' => $node['props']['dark_bg_color'] ?? null];
        }

        $vordergrund = $node['props']['color'] ?? null;
        if ($vordergrund !== null) {
            $paare[] = ['hell' => $vordergrund, 'dunkel' => $node['props']['dark_color'] ?? null];
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk($tree);

    return $paare;
}

/**
 * Die Abschnitte der gerenderten Liste in Render-Reihenfolge: pro Abschnitt
 * seine Überschrift und die Headlines seiner Zeilen. Das ist genau das, was
 * das Gerät zeichnen würde — Reihenfolge inklusive.
 *
 * @return list<array{ueberschrift: ?string, artikel: list<string>}>
 */
function listenAbschnitte(TestableComponent $screen): array
{
    $abschnitte = [];

    $walk = function (array $node) use (&$walk, &$abschnitte): void {
        if (($node['type'] ?? null) === 'list_section') {
            $abschnitte[] = [
                'ueberschrift' => $node['props']['header'] ?? null,
                'artikel' => array_values(array_map(
                    fn (array $zeile) => $zeile['props']['headline'] ?? '',
                    array_filter($node['children'] ?? [], fn (array $zeile) => ($zeile['type'] ?? null) === 'list_item'),
                )),
            ];

            return;
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk($screen->tree());

    return $abschnitte;
}

/**
 * Die Typen aller Knoten eines Teilbaums, den Wurzelknoten eingeschlossen.
 *
 * @return list<string>
 */
function knotenTypen(array $node): array
{
    $typen = [];

    $walk = function (array $node) use (&$walk, &$typen): void {
        $typen[] = $node['type'] ?? '';

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk($node);

    return $typen;
}

/**
 * Tippt die Checkbox vorn an einer Listenzeile an — nicht die Zeile. Das
 * Gerät schickt dafür ein Checkbox-Event an `on_leading_change`; `check()`
 * des Harness sucht nur `on_change` und fände die Zeile deshalb nicht.
 */
function checkboxAntippen(TestableComponent $screen, string $ref, bool $wert = true): TestableComponent
{
    return $screen->fireEvent(
        $ref,
        TestableComponent::EVENT_CHECKBOX_CHANGE,
        ['value' => $wert],
        ['on_leading_change'],
    );
}

/** Der Untertitel, den die Top-Bar ans Gerät schickt. */
function navUntertitel(TestableComponent $screen): ?string
{
    $untertitel = null;

    $walk = function (array $node) use (&$walk, &$untertitel): void {
        $untertitel ??= $node['props']['nav_subtitle'] ?? null;

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk($screen->tree());

    return $untertitel;
}

/**
 * Der erste Knoten des Baums mit diesem `ref`. `ref` steht auf Knotenebene,
 * nicht in `props` — deshalb findet ihn kein `assertElement()`-Matcher über
 * die Props.
 *
 * @return array<string, mixed>|null
 */
function knotenMitRef(TestableComponent $screen, string $ref): ?array
{
    $treffer = null;

    $walk = function (array $node) use (&$walk, &$treffer, $ref): void {
        if (($node['ref'] ?? null) === $ref) {
            $treffer ??= $node;

            return;
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk($screen->tree());

    return $treffer;
}

/**
 * Ein Secure Storage, der sich merkt, was er bekommen hat: Schreiben füllt
 * ihn, Lesen gibt zurück, was zuletzt geschrieben wurde, Löschen leert ihn.
 * Ein `respondTo()` mit festem Array könnte das nicht — der Screen liest den
 * Status nach jedem Schreiben neu, und genau dieser zweite Blick ist das,
 * was geprüft werden soll.
 */
function fakeSecureStore(?string $anfangswert = null): FakeBridge
{
    $gespeichert = $anfangswert;

    return Native::fakeBridge()
        // Bewusst keine Pfeilfunktion: die bindet `$gespeichert` per Wert und
        // sähe damit für immer den Anfangswert.
        ->respondTo('SecureStorage.Get', function () use (&$gespeichert): array {
            return $gespeichert === null
                ? ['status' => 'not_found', 'value' => '']
                : ['status' => 'found', 'value' => $gespeichert];
        })
        ->respondTo('SecureStorage.Set', function (array $params) use (&$gespeichert): array {
            $gespeichert = $params['value'];

            return ['success' => true];
        })
        ->respondTo('SecureStorage.Delete', function () use (&$gespeichert): array {
            $gespeichert = null;

            return ['success' => true];
        });
}

/**
 * Der Inhalt einer JSON-Fixture aus `tests/Fixtures` als Array — eine echte
 * Mealie-Antwort, abgelegt statt im Test zusammengebaut. So prüft der Test
 * gegen das, was der Server wirklich schickt, und nicht gegen die Vorstellung,
 * die der Code davon hat.
 *
 * @return array<string, mixed>
 */
function jsonFixture(string $dateiname): array
{
    return json_decode(
        (string) file_get_contents(__DIR__.'/Fixtures/'.$dateiname),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );
}
