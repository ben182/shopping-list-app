<?php

// Version und Version-Code stehen nur in der gitignoreten `.env`. Wird beim
// Veröffentlichen falsch hochgezählt oder eine Zeile überschrieben, fällt das
// erst auf dem Gerät auf — dort dann unumkehrbar.

use App\Release\EnvDatei;
use App\Release\Stufe;
use App\Release\Version;

function tempEnv(string $inhalt): string
{
    $pfad = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($pfad, $inhalt);

    return $pfad;
}

it('erhöht die Version je Stufe', function (string $aktuell, Stufe $stufe, string $erwartet) {
    expect((string) Version::ausString($aktuell)->erhoehen($stufe))->toBe($erwartet);
})->with([
    ['1.2.3', Stufe::Patch, '1.2.4'],
    ['1.2.3', Stufe::Minor, '1.3.0'],
    ['1.2.3', Stufe::Major, '2.0.0'],
    ['0.9.9', Stufe::Minor, '0.10.0'],
]);

it('liest Versionen mit und ohne v davor', function () {
    expect((string) Version::ausString('v1.0.0'))->toBe('1.0.0')
        ->and(Version::ausString('1.0.0')->tag())->toBe('v1.0.0');
});

it('weist unbrauchbare Versionen ab', function (string $wert) {
    Version::ausString($wert);
})->with(['1.0', 'latest', '1.0.0-beta', ''])->throws(InvalidArgumentException::class);

it('ersetzt vorhandene Zeilen der .env und lässt den Rest stehen', function () {
    $pfad = tempEnv("APP_NAME=Einkaufsliste\nNATIVEPHP_APP_VERSION=1.0.0\nNATIVEPHP_APP_VERSION_CODE=7\n");

    (new EnvDatei($pfad))->schreiben([
        'NATIVEPHP_APP_VERSION' => '1.0.1',
        'NATIVEPHP_APP_VERSION_CODE' => 8,
    ]);

    expect(file_get_contents($pfad))
        ->toBe("APP_NAME=Einkaufsliste\nNATIVEPHP_APP_VERSION=1.0.1\nNATIVEPHP_APP_VERSION_CODE=8\n");
});

it('hängt fehlende Schlüssel an, statt sie zu verlieren', function () {
    $pfad = tempEnv("APP_NAME=Einkaufsliste\n");

    (new EnvDatei($pfad))->schreiben(['NATIVEPHP_APP_VERSION' => '1.0.0']);

    expect(file_get_contents($pfad))->toBe("APP_NAME=Einkaufsliste\nNATIVEPHP_APP_VERSION=1.0.0\n");
});

it('meldet leere Werte als fehlend', function () {
    $env = new EnvDatei(tempEnv("NATIVEPHP_APP_VERSION=\nANDROID_KEY_ALIAS=\"upload\"\n"));

    expect($env->lesen('NATIVEPHP_APP_VERSION'))->toBeNull()
        ->and($env->lesen('ANDROID_KEY_ALIAS'))->toBe('upload')
        ->and($env->lesen('GIBT_ES_NICHT'))->toBeNull();
});

it('nennt alle gesetzten Schlüssel der .env', function () {
    $env = new EnvDatei(tempEnv("APP_NAME=Einkaufsliste\n\n# Kommentar\nMEALIE_URL=https://beispiel.test\nLEER=\n"));

    expect($env->schluessel())->toBe(['APP_NAME', 'MEALIE_URL', 'LEER']);
});
