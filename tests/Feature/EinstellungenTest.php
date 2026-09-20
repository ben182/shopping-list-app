<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

/*
 * Der Screen wird immer über die Route besucht, mit einem gefakten Secure
 * Storage darunter. Der Seam ist damit derselbe, den das Gerät sieht: der
 * Wire-Tree oben, die Bridge-Aufrufe unten.
 */

it('zeigt Titel und Mealie-URL', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')
        ->assertNavTitle('Einstellungen')
        ->assertSee('https://mealie.example.test');
});

it('meldet beim Öffnen, dass kein Token hinterlegt ist', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')->assertSee('Kein Token hinterlegt');
});

it('meldet beim Öffnen ein hinterlegtes Token, ohne es zu zeigen', function () {
    fakeSecureStore('mealie-geheim-123');

    Native::visit('/einstellungen')
        ->assertSee('Token hinterlegt')
        ->assertDontSee('mealie-geheim-123');
});

it('unterscheidet ein gesperrtes Gerät von einem fehlenden Token', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['status' => 'unavailable', 'code' => 'INTERACTION_NOT_ALLOWED']);

    Native::visit('/einstellungen')
        ->assertSee('Gerät gesperrt, Token nicht lesbar')
        ->assertDontSee('Kein Token hinterlegt');
});

it('nennt bei einem Lesefehler den Code', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['status' => 'error', 'code' => 'READ_FAILED', 'message' => 'Keystore kaputt']);

    Native::visit('/einstellungen')->assertSee('Fehler beim Lesen: READ_FAILED');
});

it('meldet ohne native Bridge einen Lesefehler, statt abzustürzen', function () {
    Native::visit('/einstellungen')->assertSee('Fehler beim Lesen: BRIDGE_UNAVAILABLE');
});

it('maskiert das Token-Eingabefeld', function () {
    fakeSecureStore();

    $feld = knotenMitRef(Native::visit('/einstellungen'), 'mealie-token');

    expect($feld['props']['label'])->toBe('API-Token')
        ->and($feld['props']['secure'])->toBeTrue();
});

it('speichert nichts, solange das Feld leer ist', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')
        ->press('speichern')
        ->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Bitte Token eingeben')
        ->assertNativeNotCalled('SecureStorage.Set')
        ->assertSee('Kein Token hinterlegt');
});

it('hält reine Leerzeichen für kein Token', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')
        ->input('mealie-token', '   ')
        ->press('speichern')
        ->assertNativeNotCalled('SecureStorage.Set');
});

it('legt das eingegebene Token in den Secure Storage', function () {
    fakeSecureStore();

    Native::visit('/einstellungen')
        ->input('mealie-token', 'mealie-geheim-123')
        ->press('speichern')
        ->assertNativeCalled('SecureStorage.Set', fn (array $params) => $params['key'] === 'einkaufsliste.mealie-token'
            && $params['value'] === 'mealie-geheim-123'
            && $params['accessibility'] === 'after_first_unlock')
        ->assertNativeCalled('Dialog.Toast', fn (array $params) => $params['message'] === 'Token gespeichert')
        ->assertSee('Token hinterlegt')
        ->assertDontSee('mealie-geheim-123')
        ->assertSet('eingabe', '');
});

it('bietet das Löschen als destruktiven Knopf an', function () {
    fakeSecureStore('mealie-geheim-123');

    $knopf = knotenMitRef(Native::visit('/einstellungen'), 'token-loeschen');

    expect($knopf['props']['label'])->toBe('Token löschen')
        ->and($knopf['props']['variant'])->toBe('destructive');
});

it('fragt vor dem Löschen des Tokens nach', function () {
    fakeSecureStore('mealie-geheim-123');

    Native::visit('/einstellungen')
        ->press('loeschenBestaetigen')
        ->assertNativeCalled('Dialog.Alert', fn (array $params) => $params['title'] === 'Token löschen?'
            && collect($params['buttons'])->map(fn ($knopf) => is_array($knopf) ? $knopf['label'] : $knopf)->all() === ['Abbrechen', 'Löschen'])
        ->assertNativeNotCalled('SecureStorage.Delete');
});

it('entfernt das Token nach der Bestätigung aus dem Secure Storage', function () {
    fakeSecureStore('mealie-geheim-123');

    Native::visit('/einstellungen')
        ->press('loeschenBestaetigen')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Löschen'])
        ->assertNativeCalled('SecureStorage.Delete', fn (array $params) => $params['key'] === 'einkaufsliste.mealie-token')
        ->assertSee('Kein Token hinterlegt');
});

it('lässt das Token nach „Abbrechen“ stehen', function () {
    fakeSecureStore('mealie-geheim-123');

    Native::visit('/einstellungen')
        ->press('loeschenBestaetigen')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Abbrechen'])
        ->assertNativeNotCalled('SecureStorage.Delete')
        ->assertSee('Token hinterlegt');
});

it('lässt „Verbindung testen“ ohne hinterlegtes Token nicht zu', function () {
    fakeSecureStore();

    $knopf = knotenMitRef(Native::visit('/einstellungen'), 'verbindung-testen');

    expect($knopf['props']['label'])->toBe('Verbindung testen')
        ->and($knopf['props']['disabled'])->toBeTrue();
});

it('gibt „Verbindung testen“ frei, sobald ein Token hinterlegt ist', function () {
    fakeSecureStore('mealie-geheim-123');

    $knopf = knotenMitRef(Native::visit('/einstellungen'), 'verbindung-testen');

    expect($knopf['props']['disabled'] ?? false)->toBeFalse();
});

it('meldet nach erfolgreichem Test Nutzernamen und Mealie-Version', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    Http::fake([
        'mealie.example.test/api/users/self' => Http::response(['username' => 'ben']),
        'mealie.example.test/api/app/about' => Http::response(['version' => 'v3.27.0']),
    ]);

    Native::visit('/einstellungen')
        ->press('verbindungTesten')
        ->assertSee('Verbunden: ben, Mealie v3.27.0');

    Http::assertSent(fn (Request $anfrage) => $anfrage->hasHeader('Authorization', 'Bearer mealie-geheim-123'));
});

it('nennt ein abgelehntes Token beim Namen', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-abgelaufen');

    Http::fake(['*' => Http::response([], 401)]);

    Native::visit('/einstellungen')
        ->press('verbindungTesten')
        ->assertSee('Token ungültig');
});

it('meldet einen Netzwerkfehler als „Mealie nicht erreichbar“', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    Http::fake(fn () => throw new ConnectionException('Zeitüberschreitung'));

    Native::visit('/einstellungen')
        ->press('verbindungTesten')
        ->assertSee('Mealie nicht erreichbar');
});

it('zeigt am Knopf einen Ladezustand, solange der Test läuft', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-geheim-123');

    $screen = Native::visit('/einstellungen');

    $laeuftWaehrendDesAufrufs = null;

    Http::fake(function () use ($screen, &$laeuftWaehrendDesAufrufs) {
        $laeuftWaehrendDesAufrufs ??= $screen->get('testLaeuft');

        return Http::response(['username' => 'ben', 'version' => 'v3.27.0']);
    });

    $screen->press('verbindungTesten');

    // Der Screen rendert erst nach dem Handler wieder; den Zustand mitten im
    // Aufruf zeigt darum kein Baum, sondern nur die Komponente selbst.
    expect($laeuftWaehrendDesAufrufs)->toBeTrue()
        ->and($screen->get('testLaeuft'))->toBeFalse()
        ->and(knotenMitRef($screen, 'verbindung-testen')['props']['loading'] ?? false)->toBeFalse();

    // Und dieser Zustand ist es, den der Knopf als Ladezustand trägt.
    $screen->set('testLaeuft', true);

    expect(knotenMitRef($screen, 'verbindung-testen')['props']['loading'])->toBeTrue();
});

it('findet ein gespeichertes Token beim nächsten Öffnen des Screens wieder', function () {
    // Derselbe Storage über beide Besuche hinweg — auf dem Gerät übersteht der
    // Keystore den App-Neustart, hier übersteht der Fake den Screen-Wechsel.
    fakeSecureStore();

    Native::visit('/einstellungen')
        ->input('mealie-token', 'mealie-geheim-123')
        ->press('speichern');

    Native::visit('/einstellungen')
        ->assertSee('Token hinterlegt')
        ->assertDontSee('mealie-geheim-123')
        ->assertSet('eingabe', '');
});

it('vergisst das Ergebnis des Verbindungstests, sobald ein neues Token gespeichert wird', function () {
    AsyncTask::fake();
    fakeSecureStore('mealie-alt');

    Http::fake(['*' => Http::response([], 401)]);

    Native::visit('/einstellungen')
        ->press('verbindungTesten')
        ->assertSee('Token ungültig')
        ->input('mealie-token', 'mealie-neu')
        ->press('speichern')
        ->assertDontSee('Token ungültig');
});

it('bleibt für den Screenreader bedienbar', function () {
    fakeSecureStore('mealie-geheim-123');

    Native::visit('/einstellungen')->assertAccessible();
});
