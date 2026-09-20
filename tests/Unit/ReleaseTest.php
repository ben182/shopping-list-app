<?php

// Version und Version-Code stehen nur in der gitignoreten `.env`. Wird beim
// Veröffentlichen falsch hochgezählt oder eine Zeile überschrieben, fällt das
// erst auf dem Gerät auf — dort dann unumkehrbar.

use App\Release\AndroidTheme;
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

// Die Android-Theme-Dateien schreibt nur `native:install`. Ändert sich danach
// eine Farbe in `config/nativephp.php`, baut Gradle trotzdem die alte —
// Systemdialoge und Picker blieben schwarz statt indigo.

function androidProjektMit(string $hell, string $dunkel): string
{
    $wurzel = sys_get_temp_dir().'/'.uniqid('android');

    foreach (['values' => $hell, 'values-night' => $dunkel] as $ordner => $inhalt) {
        $verzeichnis = $wurzel.'/app/src/main/res/'.$ordner;
        mkdir($verzeichnis, 0o777, true);
        file_put_contents($verzeichnis.'/themes.xml', $inhalt);
    }

    return $wurzel;
}

function themeXml(string $primaer, string $aufPrimaer): string
{
    return <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <resources>
            <style name="Theme.AndroidPHP" parent="Theme.MaterialComponents.DayNight.DarkActionBar">
                <item name="colorPrimary">{$primaer}</item>
                <item name="colorPrimaryVariant">{$primaer}</item>
                <item name="colorOnPrimary">{$aufPrimaer}</item>
                <item name="colorAccent">{$primaer}</item>
                <item name="android:colorAccent">{$primaer}</item>
                <item name="android:windowDrawsSystemBarBackgrounds">true</item>
                <item name="android:statusBarColor">@android:color/transparent</item>
            </style>
        </resources>

        XML;
}

function geschriebenesTheme(string $wurzel, string $ordner): string
{
    return (string) file_get_contents($wurzel.'/app/src/main/res/'.$ordner.'/themes.xml');
}

it('trägt die konfigurierten Farben in beide Theme-Dateien ein', function () {
    $wurzel = androidProjektMit(
        themeXml('#FF000000', '#FFFFFFFF'),
        themeXml('#FFFFFFFF', '#FFFFFFFF'),
    );

    (new AndroidTheme($wurzel, '#4F46E5', '#818CF8', '#FFFFFF'))->anwenden();

    expect(geschriebenesTheme($wurzel, 'values'))->toBe(themeXml('#FF4F46E5', '#FFFFFFFF'))
        ->and(geschriebenesTheme($wurzel, 'values-night'))->toBe(themeXml('#FF818CF8', '#FFFFFFFF'));
});

it('nennt nur die Dateien, die sich geändert haben', function () {
    $wurzel = androidProjektMit(
        themeXml('#FF4F46E5', '#FFFFFFFF'),
        themeXml('#FFFFFFFF', '#FFFFFFFF'),
    );

    expect((new AndroidTheme($wurzel, '#4F46E5', '#818CF8', '#FFFFFF'))->anwenden())
        ->toBe([$wurzel.'/app/src/main/res/values-night/themes.xml']);
});

it('kommt ohne installiertes Android-Projekt zurecht', function () {
    expect((new AndroidTheme(sys_get_temp_dir().'/gibt-es-nicht', '#4F46E5', '#818CF8', '#FFFFFF'))->anwenden())
        ->toBe([]);
});

it('weist unbrauchbare Farben ab, statt schwarz zu bauen', function (string $farbe) {
    (new AndroidTheme(androidProjektMit(themeXml('#FF000000', '#FFFFFFFF'), themeXml('#FFFFFFFF', '#FFFFFFFF')), $farbe, '#818CF8', '#FFFFFF'))->anwenden();
})->with(['4F46E5', '#4F46E', 'indigo', ''])->throws(InvalidArgumentException::class);
