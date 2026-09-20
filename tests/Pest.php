<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\TestableComponent;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

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
