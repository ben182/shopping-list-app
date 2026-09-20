<?php

use App\Erscheinungsbild\Akzentfarbe;
use App\Erscheinungsbild\Farbwahl;
use App\Liste\EigeneListe;
use Native\Mobile\Testing\Native;
use Native\Mobile\UI\Theme;

it('benutzt Indigo als Primärfarbe in beiden Erscheinungsbildern', function () {
    // Werte aus der PRD, nicht aus der Konfiguration abgeleitet.
    expect(Theme::get('light.primary'))->toBe('#4F46E5')
        ->and(Theme::get('dark.primary'))->toBe('#818CF8')
        // Auf dem hellen Indigo des Dunkelmodus reicht Weiß nicht für 4,5:1.
        ->and(Theme::get('light.on-primary'))->toBe('#FFFFFF')
        ->and(Theme::get('dark.on-primary'))->toBe('#0F172A');
});

it('schickt zu jeder Farbe eine davon verschiedene Dark-Mode-Entsprechung mit', function (string $uri) {
    $paare = farbPaare(Native::visit($uri, platform: 'android')->tree());

    expect($paare)->not->toBeEmpty();

    foreach ($paare as $paar) {
        expect($paar['dunkel'])
            ->not->toBeNull("Farbe {$paar['hell']} hat keine Dark-Mode-Entsprechung — feste Farbe statt Theme-Klasse?")
            ->not->toBe($paar['hell']);
    }
})->with(['/', '/vorrat', '/wochenplan', '/einstellungen']);

it('färbt auch die Leiste nach „Alles abhaken“ in beiden Erscheinungsbildern', function () {
    app(EigeneListe::class)->hinzufuegen('tofu');

    $screen = Native::visit('/', platform: 'android')->press('alleAbhaken');

    // Ohne die Leiste im Baum prüfte der Test nur die Screens ohne sie.
    $leiste = knotenMitRef($screen, 'rueckgaengig-leiste');

    expect($leiste)->not->toBeNull();

    // Surface als Fläche, Outline als Rand — Werte aus der PRD, nicht aus
    // der Konfiguration abgeleitet.
    expect($leiste['style']['bg_color'])->toBe('#FFFFFF')
        ->and($leiste['props']['dark_bg_color'])->toBe('#1E293B')
        ->and($leiste['style']['border_color'])->toBe('#CBD5E1')
        ->and($leiste['props']['dark_border_color'])->toBe('#475569');

    foreach (farbPaare($screen->tree()) as $paar) {
        expect($paar['dunkel'])
            ->not->toBeNull("Farbe {$paar['hell']} hat keine Dark-Mode-Entsprechung — feste Farbe statt Theme-Klasse?")
            ->not->toBe($paar['hell']);
    }
});

/**
 * Kontrastverhältnis zweier Farben nach der WCAG-2-Formel — bewusst hier
 * ausgerechnet und nicht aus der App geholt, damit der Test der Palette
 * widersprechen kann.
 */
function kontrast(string $eine, string $andere): float
{
    $leuchtdichte = function (string $hex): float {
        $kanaele = [];

        foreach ([0, 2, 4] as $stelle) {
            $wert = hexdec(substr(ltrim($hex, '#'), $stelle, 2)) / 255;
            $kanaele[] = $wert <= 0.03928 ? $wert / 12.92 : (($wert + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $kanaele[0] + 0.7152 * $kanaele[1] + 0.0722 * $kanaele[2];
    };

    $hell = $leuchtdichte($eine);
    $dunkel = $leuchtdichte($andere);

    return (max($hell, $dunkel) + 0.05) / (min($hell, $dunkel) + 0.05);
}

it('lässt die Schrift auf jeder Akzentfarbe lesbar — hell wie dunkel', function (string $wert) {
    app(Farbwahl::class)->waehlen(Akzentfarbe::from($wert));

    expect(kontrast(Theme::get('light.primary'), Theme::get('light.on-primary')))
        ->toBeGreaterThanOrEqual(4.5)
        ->and(kontrast(Theme::get('dark.primary'), Theme::get('dark.on-primary')))
        ->toBeGreaterThanOrEqual(4.5);
})->with(['indigo', 'blau', 'gruen', 'orange', 'rosa', 'violett']);

it('schickt auch in jeder anderen Akzentfarbe zu jeder Farbe eine verschiedene Dunkel-Entsprechung mit', function (string $wert) {
    fakeSecureStore();

    app(Farbwahl::class)->waehlen(Akzentfarbe::from($wert));

    foreach (['/', '/vorrat', '/wochenplan', '/einstellungen'] as $uri) {
        $paare = farbPaare(Native::visit($uri, platform: 'android')->tree());

        expect($paare)->not->toBeEmpty();

        foreach ($paare as $paar) {
            expect($paar['dunkel'])
                ->not->toBeNull("Farbe {$paar['hell']} auf {$uri} hat keine Dark-Mode-Entsprechung — feste Farbe statt Theme-Klasse?")
                ->not->toBe($paar['hell']);
        }
    }
})->with(['indigo', 'blau', 'gruen', 'orange', 'rosa', 'violett']);
