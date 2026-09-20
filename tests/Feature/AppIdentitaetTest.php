<?php

// Werte aus der PRD. Sie landen erst beim Build im AndroidManifest bzw. in
// build.gradle — hier festgenagelt, damit ein versehentlicher Reset der
// Konfiguration nicht erst auf dem Homescreen auffällt.

it('heißt auf dem Homescreen Einkaufsliste', function () {
    expect(config('app.name'))->toBe('Einkaufsliste');
});

it('benutzt die Bundle-ID de.ben182.einkaufsliste', function () {
    expect(config('nativephp.app_id'))->toBe('de.ben182.einkaufsliste');
});

it('lässt nur Hochformat zu', function () {
    foreach (['android', 'iphone'] as $geraet) {
        expect(config("nativephp.orientation.{$geraet}"))->toBe([
            'portrait' => true,
            'upside_down' => false,
            'landscape_left' => false,
            'landscape_right' => false,
        ]);
    }
});
