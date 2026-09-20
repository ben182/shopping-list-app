<?php

namespace App\NativeComponents;

use App\Mealie\Sitzung;
use App\Mealie\Token;
use App\Mealie\Verbindung;
use App\Wochenplan\Sitzung as Wochenplansitzung;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\SecureStorageStatus;

class Einstellungen extends NativeComponent
{
    /** Was im Token-Feld steht. Nach dem Speichern wieder leer. */
    public string $eingabe = '';

    /** Der Statustext unter dem Feld — nie das Token selbst. */
    public string $status = '';

    public bool $tokenHinterlegt = false;

    /** Läuft gerade ein Verbindungstest? Treibt den Spinner am Knopf. */
    public bool $testLaeuft = false;

    /** Das Ergebnis des letzten Verbindungstests, solange der Screen offen ist. */
    public ?string $verbindung = null;

    public function navTitle(): string
    {
        return 'Einstellungen';
    }

    public function mount(): void
    {
        $this->statusLesen();
    }

    public function mealieUrl(): string
    {
        return (string) config('mealie.url');
    }

    public function tokenGetippt(string $eingabe): void
    {
        $this->eingabe = $eingabe;
    }

    /**
     * Ein leeres Feld ist kein Löschbefehl — dafür gibt es den eigenen Knopf.
     * Also lieber nichts tun und das sagen, als das hinterlegte Token still
     * mit einer leeren Zeichenkette zu überschreiben.
     */
    public function speichern(): void
    {
        $token = trim($this->eingabe);

        if ($token === '') {
            Dialog::toast('Bitte Token eingeben');

            return;
        }

        if (! app(Token::class)->speichern($token)) {
            Dialog::toast('Speichern fehlgeschlagen');

            return;
        }

        $this->eingabe = '';
        $this->verbindung = null;

        Dialog::toast('Token gespeichert');

        $this->statusLesen();
    }

    /**
     * Der Aufruf gehört in einen `async`-Task, nicht direkt in den Handler:
     * der Runloop rendert erst *nach* dem Handler wieder, ein synchrones
     * `Http::get()` bliebe also unsichtbar hinter einer eingefrorenen UI und
     * der Ladezustand käme nie aufs Bild. Die Closure muss `static` sein —
     * sie läuft in einem eigenen Interpreter — und bekommt darum alles, was
     * sie braucht, als Wert mit.
     */
    public function verbindungTesten(): void
    {
        $ergebnis = app(Token::class)->lesen();

        if (! $ergebnis->found()) {
            $this->statusLesen();

            return;
        }

        $this->testLaeuft = true;
        $this->verbindung = null;

        $basisUrl = $this->mealieUrl();
        $token = (string) $ergebnis->value;
        $timeout = (int) config('mealie.timeout');

        $this->async(static fn (): array => Verbindung::pruefen($basisUrl, $token, $timeout))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis): void {
                $this->testLaeuft = false;
                $this->verbindung = $ergebnis['text'];
            })
            ->failed(function (): void {
                $this->testLaeuft = false;
                $this->verbindung = 'Mealie nicht erreichbar';
            });
    }

    /**
     * Ohne Token ist die App von Mealie abgeschnitten, und zurückholen kann
     * man es nur mit dem Zettel aus Mealie — also erst fragen, dann löschen.
     * Jeder andere Ausgang des Dialogs lässt den Keystore in Ruhe.
     *
     * Mit dem Token geht auch der Mealie-Cache — Einkaufsliste wie
     * Wochenplan: was die App nicht mehr abrufen darf, soll sie auch nicht
     * mehr aus der Schublade zeigen.
     */
    public function loeschenBestaetigen(): void
    {
        Dialog::alert(
            'Token löschen?',
            'Die App kann danach nicht mehr auf Mealie zugreifen.',
            [
                ['label' => 'Abbrechen', 'style' => 'cancel'],
                ['label' => 'Löschen', 'style' => 'destructive'],
            ]
        )->buttonPressed(function (ButtonPressed $event): void {
            if ($event->label !== 'Löschen') {
                return;
            }

            app(Token::class)->loeschen();
            app(Sitzung::class)->vergessen();
            app(Wochenplansitzung::class)->vergessen();

            $this->verbindung = null;

            $this->statusLesen();
        });
    }

    public function render(): Element
    {
        return $this->view('einstellungen');
    }

    /**
     * Fragt den Keystore, was er hergibt, und übersetzt jeden der vier
     * Ausgänge in einen Satz. „Gesperrt“ zählt dabei nicht als „kein Token“ —
     * der Screen darf in dem Zustand nichts anbieten, was das Token bräuchte.
     */
    private function statusLesen(): void
    {
        $ergebnis = app(Token::class)->lesen();

        $this->tokenHinterlegt = $ergebnis->found();

        $this->status = match ($ergebnis->status) {
            SecureStorageStatus::Found => 'Token hinterlegt',
            SecureStorageStatus::NotFound => 'Kein Token hinterlegt',
            SecureStorageStatus::Unavailable => 'Gerät gesperrt, Token nicht lesbar',
            SecureStorageStatus::Failed => 'Fehler beim Lesen: '.($ergebnis->code ?? 'UNBEKANNT'),
        };
    }
}
