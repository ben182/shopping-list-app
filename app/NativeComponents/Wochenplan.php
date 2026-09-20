<?php

namespace App\NativeComponents;

use App\Mealie\Fehler;
use App\Mealie\Fehlerzustand;
use App\Mealie\Token;
use App\Wochenplan\Plan;
use App\Wochenplan\Sitzung;
use App\Wochenplan\Tag;
use App\Wochenplan\Uebersicht;
use App\Wochenplan\Woche;
use Ben182\AppLifecycle\Events\AppForegrounded;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;
use Native\Mobile\SecureStorageStatus;

class Wochenplan extends NativeComponent
{
    /**
     * Der Montag der gewählten Woche als `Y-m-d`. Ein String statt eines
     * Objekts, weil der Zustand des Screens über die Wire-Grenze geht.
     */
    public string $montag = '';

    /** Läuft gerade ein Ladevorgang? Sichtbar nur bei einer Woche ohne Daten. */
    public bool $laedt = false;

    /** Ist gar kein Token hinterlegt? Dann statt der Tage ein Leerzustand. */
    public bool $nichtVerbunden = false;

    public function navTitle(): string
    {
        return 'Wochenplan';
    }

    /**
     * Beim Öffnen des Tabs steht immer die Woche des heutigen Tages —
     * jeder Tab-Wechsel mountet den Screen neu.
     */
    public function mount(): void
    {
        $this->montag = Woche::aktuelle()->schluessel();

        $this->laden();
    }

    /** Nach der Rückkehr von den Einstellungen — dort kann ein Token entstanden sein. */
    public function onResume(): void
    {
        $this->laden();
    }

    /**
     * Die App kommt aus dem Hintergrund zurück; das Ereignis schickt das
     * Plugin `ben182/app-lifecycle`.
     */
    #[On(AppForegrounded::class)]
    public function appImVordergrund(): void
    {
        $this->laden();
    }

    public function woche(): Woche
    {
        return $this->montag === '' ? Woche::aktuelle() : Woche::ausSchluessel($this->montag);
    }

    public function wochenText(): string
    {
        return $this->woche()->text();
    }

    public function vorherigeWoche(): void
    {
        $this->wocheWechseln($this->woche()->vorherige());
    }

    public function naechsteWoche(): void
    {
        $this->wocheWechseln($this->woche()->naechste());
    }

    /** Der Tap auf den Wochen-Text: zurück zur Woche des heutigen Tages. */
    public function aktuelleWoche(): void
    {
        $this->wocheWechseln(Woche::aktuelle());
    }

    /** Pull-to-Refresh an der Liste. */
    public function neuLaden(): void
    {
        $this->laden();
    }

    /**
     * Die sieben Tage der gewählten Woche mit dem, was für sie geplant ist.
     *
     * @return list<Tag>
     */
    #[Computed]
    public function tage(): array
    {
        return Uebersicht::tage($this->woche(), app(Sitzung::class)->eintraege($this->montag));
    }

    /**
     * Was das Banner unter der Wochen-Navigation sagt — `null`, solange
     * Mealie mitspielt. Der Stand gehört der aufgeschlagenen Woche.
     */
    public function banner(): ?Fehlerzustand
    {
        return app(Sitzung::class)->fehlerzustand($this->montag);
    }

    /**
     * Gescheitert und nichts in der Schublade: dann bleibt unter dem Banner
     * nur der Hinweis, dass hier nichts zu holen war. Eine Woche mit Cache
     * zeigt dagegen weiter ihre Einträge.
     */
    public function zeigtFehlerLeerzustand(): bool
    {
        return $this->banner() !== null && ! app(Sitzung::class)->hat($this->montag);
    }

    /**
     * Der Spinner gehört nur der Woche, von der die App noch nichts weiß.
     * Eine schon geladene Woche bleibt beim Neuladen stehen, statt zu blinken.
     */
    public function zeigtSpinner(): bool
    {
        return $this->laedt && ! app(Sitzung::class)->hat($this->montag);
    }

    /** Ein Tap auf eine Rezept-Zeile öffnet die Rezeptseite im System-Browser. */
    public function rezeptOeffnen(string $slug): void
    {
        Browser::open(rtrim((string) config('mealie.url'), '/').'/g/home/r/'.$slug);
    }

    public function oeffneEinstellungen(): void
    {
        $this->navigate('/einstellungen');
    }

    public function render(): Element
    {
        return $this->view('wochenplan');
    }

    private function wocheWechseln(Woche $woche): void
    {
        if ($woche->schluessel() === $this->montag) {
            return;
        }

        $this->montag = $woche->schluessel();

        unset($this->tage);

        $this->laden();
    }

    /**
     * Holt die gewählte Woche — aber nur, wenn ein Token da ist. „Gerät
     * gesperrt“ und „Lesefehler“ sind ausdrücklich kein „kein Token“.
     *
     * Der Aufruf läuft über `async`, weil der Runloop erst nach dem Handler
     * wieder rendert — synchron bliebe der Spinner unsichtbar.
     */
    private function laden(): void
    {
        $ergebnis = app(Token::class)->lesen();

        $this->nichtVerbunden = $ergebnis->status === SecureStorageStatus::NotFound;

        if ($this->nichtVerbunden) {
            app(Sitzung::class)->vergessen();

            unset($this->tage);
        }

        if (! $ergebnis->found()) {
            return;
        }

        $this->laedt = true;

        $woche = $this->woche();
        $schluessel = $woche->schluessel();
        $basisUrl = (string) config('mealie.url');
        $token = (string) $ergebnis->value;
        $timeout = (int) config('mealie.timeout');
        $start = $woche->startDatum();
        $ende = $woche->endDatum();

        $this->async(static fn (): array => Plan::laden($basisUrl, $token, $start, $ende, $timeout))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis) use ($schluessel): void {
                $this->laedt = false;

                if (isset($ergebnis['eintraege'])) {
                    app(Sitzung::class)->setzen($schluessel, $ergebnis['eintraege']);
                } else {
                    app(Sitzung::class)->fehlerMelden(Fehler::ausSchluessel((string) ($ergebnis['fehler'] ?? '')));
                }

                unset($this->tage);
            })
            ->failed(function (): void {
                $this->laedt = false;

                app(Sitzung::class)->fehlerMelden(Fehler::Netz);

                unset($this->tage);
            });
    }
}
