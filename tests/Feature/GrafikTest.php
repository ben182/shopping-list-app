<?php

// Dateinamen, Maße und Farben aus der PRD. NativePHP liest genau diese Pfade
// beim Build und überspringt sie kommentarlos, wenn sie fehlen, zu klein sind
// oder — beim Icon — auch nur ein durchsichtiges Pixel haben. Ohne Test fällt
// das erst auf dem Homescreen auf.

function splashDateien(): array
{
    return [
        'splash.png',
        'splash@2x.png',
        'splash@3x.png',
        'splash-dark.png',
        'splash-dark@2x.png',
        'splash-dark@3x.png',
    ];
}

/**
 * Farbe eines Pixels als Hex-Literal, damit sich Erwartungen aus der PRD
 * direkt hinschreiben lassen.
 */
function pixelFarbe(string $pfad, int $x, int $y): string
{
    $bild = imagecreatefrompng($pfad);
    $farbe = imagecolorat($bild, $x, $y);
    imagedestroy($bild);

    return sprintf('#%02X%02X%02X', ($farbe >> 16) & 0xFF, ($farbe >> 8) & 0xFF, $farbe & 0xFF);
}

it('legt ein quadratisches Icon von mindestens 1024 Pixeln bereit', function () {
    $masse = @getimagesize(public_path('icon.png'));

    expect($masse)->not->toBeFalse()
        ->and($masse[2])->toBe(IMAGETYPE_PNG)
        ->and($masse[0])->toBeGreaterThanOrEqual(1024)
        ->and($masse[1])->toBe($masse[0]);
});

it('zeigt das Icon weiß auf Indigo und ohne ein einziges durchsichtiges Pixel', function () {
    $bild = imagecreatefrompng(public_path('icon.png'));
    $breite = imagesx($bild);
    $hoehe = imagesy($bild);

    $durchsichtig = false;
    foreach ([[0, 0], [$breite - 1, 0], [0, $hoehe - 1], [$breite - 1, $hoehe - 1], [intdiv($breite, 2), intdiv($hoehe, 2)]] as [$x, $y]) {
        $durchsichtig = $durchsichtig || (imagecolorat($bild, $x, $y) >> 24) > 0;
    }
    imagedestroy($bild);

    expect($durchsichtig)->toBeFalse()
        ->and(pixelFarbe(public_path('icon.png'), 0, 0))->toBe('#4F46E5');
});

it('hält das Motiv im inneren Bereich von 66 % der Icon-Kante', function () {
    // Was außerhalb liegt, schneidet die Adaptive-Icon-Maske je nach Launcher
    // ab. Alles, was nicht die Indigo-Fläche ist, gehört zum Motiv.
    $bild = imagecreatefrompng(public_path('icon.png'));
    $kante = imagesx($bild);
    [$links, $oben, $rechts, $unten] = [$kante, $kante, -1, -1];

    for ($y = 0; $y < $kante; $y++) {
        for ($x = 0; $x < $kante; $x++) {
            if ((imagecolorat($bild, $x, $y) & 0xFFFFFF) === 0x4F46E5) {
                continue;
            }

            [$links, $oben, $rechts, $unten] = [min($links, $x), min($oben, $y), max($rechts, $x), max($unten, $y)];
        }
    }
    imagedestroy($bild);

    $rand = $kante * (1 - 0.66) / 2;

    expect($rechts)->toBeGreaterThan($links)
        ->and($links)->toBeGreaterThanOrEqual($rand)
        ->and($oben)->toBeGreaterThanOrEqual($rand)
        ->and($rechts)->toBeLessThanOrEqual($kante - $rand)
        ->and($unten)->toBeLessThanOrEqual($kante - $rand);
});

it('legt jedes Splash-Bild mindestens in 1280 × 1920 bereit', function (string $datei) {
    $masse = @getimagesize(public_path($datei));

    expect($masse)->not->toBeFalse()
        ->and($masse[2])->toBe(IMAGETYPE_PNG)
        ->and($masse[0])->toBeGreaterThanOrEqual(1280)
        ->and($masse[1])->toBeGreaterThanOrEqual(1920);
})->with(splashDateien());

// Die iOS-Varianten bleiben hier außen vor: Sie entstehen aus derselben Quelle
// mit denselben Farben, nur größer — und ein 3840 × 5760 großes PNG kostet GD
// 88 MB, was die Suite über ihr Speicherlimit hebt.
it('malt die Splash-Bilder in den Hintergrund des jeweiligen Modus', function (string $datei, string $hintergrund) {
    expect(pixelFarbe(public_path($datei), 0, 0))->toBe($hintergrund);
})->with([
    ['splash.png', '#F8FAFC'],
    ['splash-dark.png', '#0F172A'],
]);

it('zentriert Motiv und Schriftzug in den mittleren 60 % des Splash-Bildes', function (string $datei, int $hintergrund) {
    // NativePHP zeichnet das Bild bildschirmfüllend mit Crop; auf einem
    // schmalen Telefon bleibt davon nur die Mitte übrig. Die Mittelpunkte
    // prüfen mit: ohne das Motiv säße der Schriftzug allein in der unteren
    // Hälfte — innerhalb der 60 %, aber nicht mehr zentriert.
    $bild = imagecreatefrompng(public_path($datei));
    $breite = imagesx($bild);
    $hoehe = imagesy($bild);
    [$links, $oben, $rechts, $unten] = [$breite, $hoehe, -1, -1];

    for ($y = 0; $y < $hoehe; $y++) {
        for ($x = 0; $x < $breite; $x++) {
            if ((imagecolorat($bild, $x, $y) & 0xFFFFFF) === $hintergrund) {
                continue;
            }

            [$links, $oben, $rechts, $unten] = [min($links, $x), min($oben, $y), max($rechts, $x), max($unten, $y)];
        }
    }
    imagedestroy($bild);

    expect($rechts)->toBeGreaterThan($links)
        ->and($links)->toBeGreaterThanOrEqual($breite * 0.2)
        ->and($rechts)->toBeLessThanOrEqual($breite * 0.8)
        ->and($oben)->toBeGreaterThanOrEqual($hoehe * 0.2)
        ->and($unten)->toBeLessThanOrEqual($hoehe * 0.8)
        ->and(($links + $rechts) / 2)->toEqualWithDelta($breite / 2, $breite * 0.01)
        ->and(($oben + $unten) / 2)->toEqualWithDelta($hoehe / 2, $hoehe * 0.01);
})->with([
    ['splash.png', 0xF8FAFC],
    ['splash-dark.png', 0x0F172A],
]);

it('rendert alle Bilder aus den SVG-Quellen neu', function () {
    $ziel = sys_get_temp_dir().'/grafik-'.uniqid();

    $this->artisan('grafik', ['--ziel' => $ziel])->assertSuccessful();

    foreach (['icon.png', ...splashDateien()] as $datei) {
        expect(@getimagesize($ziel.'/'.$datei))->toBe(@getimagesize(public_path($datei)));
        unlink($ziel.'/'.$datei);
    }

    rmdir($ziel);
})->skip(! extension_loaded('imagick'), 'Imagick ist auf diesem Rechner nicht installiert.');
