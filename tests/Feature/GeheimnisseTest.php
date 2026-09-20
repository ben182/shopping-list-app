<?php

// Die README verspricht, dass Keystore, Passwörter und das Mealie-Token nie
// im Repo und nie im Bundle landen. Diese Zusagen hängen an einzelnen Zeilen
// in `.gitignore` und `config/nativephp.php` — hier festgenagelt, damit ein
// versehentliches Entfernen nicht erst im Push oder in der APK auffällt.

function istGitIgnoriert(string $pfad): bool
{
    exec('git -C '.escapeshellarg(base_path()).' check-ignore -q '.escapeshellarg($pfad), $_, $code);

    return $code === 0;
}

it('hält Keystore und .env aus der Versionsverwaltung heraus', function (string $pfad) {
    expect(istGitIgnoriert($pfad))->toBeTrue();
})->with([
    'credentials/app-keystore.jks',
    'credentials/upload-certificate.pem',
    'app-keystore.jks',
    'irgendwas.keystore',
    '.env',
]);

it('räumt die Signierungs-Variablen vor dem Bundeln aus der .env', function (string $muster) {
    expect(config('nativephp.cleanup_env_keys'))->toContain($muster);
})->with([
    'ANDROID_KEYSTORE_*',
    'ANDROID_KEY_PASSWORD',
]);

it('kennt kein Mealie-Token in der Konfiguration', function () {
    expect(config('mealie'))->not->toHaveKey('token')
        ->and(file_get_contents(base_path('.env.example')))->not->toContain('MEALIE_TOKEN');
});
