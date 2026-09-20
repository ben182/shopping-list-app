<?php

namespace App\NativeComponents;

use App\Einkaufen\Abschnitt;
use App\Einkaufen\Uebersicht;
use App\Einkaufen\Zeile;
use App\Icons\Android;
use App\Icons\Ios;
use App\Liste\EigeneListe;
use App\Mealie\Artikelstatus;
use App\Mealie\Einkaufsliste;
use App\Mealie\Eintrag;
use App\Mealie\Fehler;
use App\Mealie\Fehlerzustand;
use App\Mealie\Sitzung;
use App\Mealie\Token;
use Ben182\AppLifecycle\Events\AppForegrounded;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBarOptions;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\SecureStorageStatus;

class Einkaufen extends NativeComponent
{
    /**
     * Läuft gerade der erste Mealie-Ladevorgang dieser Sitzung? Nur der
     * bekommt eine sichtbare Zeile; jeder weitere läuft still.
     */
    public bool $mealieLaedt = false;

    /** Ist gar kein Token hinterlegt? Dann statt Mealie eine Hinweiszeile. */
    public bool $mealieNichtVerbunden = false;

    public function navTitle(): string
    {
        return 'Einkaufen';
    }

    /**
     * Die Anzahl der offenen Artikel auf diesem Screen — eigene plus die
     * nicht abgehakten aus Mealie.
     */
    public function navSubtitle(): ?string
    {
        $anzahl = $this->uebersicht()->anzahl();

        return $anzahl === 0 ? null : $anzahl.' Artikel';
    }

    /**
     * Beim Öffnen des Tabs liegt die eigene Liste sofort da; Mealie kommt
     * nach, sobald die Antwort da ist.
     */
    public function mount(): void
    {
        $this->mealieLaden();
    }

    /**
     * Nach der Rückkehr von den Einstellungen — dort kann gerade erst ein
     * Token gespeichert worden sein, das diesen Screen etwas angeht.
     */
    public function onResume(): void
    {
        $this->mealieLaden();
    }

    /**
     * Was das Banner unter der Top-Bar sagt — `null`, solange Mealie
     * mitspielt. Solange es etwas sagt, sind die Mealie-Zeilen gesperrt.
     */
    public function banner(): ?Fehlerzustand
    {
        return app(Sitzung::class)->fehlerzustand();
    }

    /** Pull-to-Refresh an der Liste, und der Knopf im Banner. */
    public function neuLaden(): void
    {
        $this->mealieLaden();
    }

    /**
     * Die App kommt aus dem Hintergrund zurück — das Ereignis schickt das
     * Plugin `ben182/app-lifecycle`. Der Screen tut dann dasselbe wie beim
     * Öffnen des Tabs.
     */
    #[On(AppForegrounded::class)]
    public function appImVordergrund(): void
    {
        $this->mealieLaden();
    }

    /**
     * Rechts in der Top-Bar: „Alles abhaken“, solange irgendetwas offen ist —
     * eigene Artikel oder offene Mealie-Artikel —,
     * und das Zahnrad — der einzige Weg zu den Einstellungen, der von überall
     * erreichbar ist.
     */
    public function navigationOptions(): ?NavBarOptions
    {
        $optionen = NavBarOptions::make();

        if ($this->uebersicht()->anzahl() > 0) {
            $optionen->action(
                NavAction::make('alles-abhaken')
                    ->icon(ios: Ios::CheckmarkCircle, android: Android::DoneAll)
                    ->a11yLabel('Alles abhaken')
                    ->press('alleAbhakenBestaetigen')
            );
        }

        return $optionen->action(
            NavAction::make('einstellungen')
                ->icon(ios: Ios::Gearshape, android: Android::Settings)
                ->a11yLabel('Einstellungen')
                ->press('oeffneEinstellungen')
        );
    }

    /**
     * Was auf der Liste steht — eigene Artikel und offene Mealie-Artikel,
     * gruppiert nach Warengruppe.
     *
     * @return list<Abschnitt>
     */
    #[Computed]
    public function abschnitte(): array
    {
        return $this->uebersicht()->abschnitte();
    }

    /**
     * Die abgehakten Mealie-Artikel — der Abschnitt am Ende der Liste.
     *
     * @return list<Zeile>
     */
    #[Computed]
    public function abgehakte(): array
    {
        return $this->uebersicht()->abgehakte();
    }

    public function abgehakteAufgeklappt(): bool
    {
        return app(Sitzung::class)->abgehakteAufgeklappt();
    }

    public function abgehakteUmklappen(): void
    {
        app(Sitzung::class)->abgehakteUmklappen();
    }

    public function abhaken(string $artikelId): void
    {
        $this->liste()->entfernen($artikelId);

        unset($this->abschnitte);
    }

    /**
     * Ein Tap auf eine Mealie-Zeile — abhaken, wenn sie offen war, sonst
     * zurückholen. Die Liste springt sofort, Mealie erfährt es nebenher;
     * widerspricht Mealie, springt sie zurück.
     */
    public function mealieUmschalten(string $artikelId): void
    {
        if ($this->banner() !== null) {
            Dialog::toast('Offline: Mealie-Artikel können gerade nicht geändert werden');

            return;
        }

        $eintrag = app(Sitzung::class)->finden($artikelId);

        if ($eintrag === null) {
            return;
        }

        $token = $this->mealieToken();

        if ($token === null) {
            $this->mealieAenderungGescheitert();

            return;
        }

        $abgehakt = ! $eintrag->abgehakt;

        app(Sitzung::class)->haken($artikelId, $abgehakt);

        $this->listeNeuZeichnen();

        $basisUrl = (string) config('mealie.url');
        $timeout = (int) config('mealie.timeout');
        $artikel = $eintrag->roh;

        $this->async(static fn (): array => Artikelstatus::setzen($basisUrl, $token, $timeout, $artikel, $abgehakt))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis) use ($artikelId, $abgehakt): void {
                if ($ergebnis['ok'] ?? false) {
                    return;
                }

                $this->mealieAenderungZuruecknehmen($artikelId, $abgehakt);
            })
            ->failed(function () use ($artikelId, $abgehakt): void {
                $this->mealieAenderungZuruecknehmen($artikelId, $abgehakt);
            });
    }

    /**
     * Zwei Taps bis zur leeren Liste: Der Dialog fragt nach, und erst sein
     * „Abhaken“ räumt ab. Jeder andere Ausgang — „Abbrechen“, Wegtippen,
     * Zurück-Geste — lässt die Liste stehen, weil dann entweder kein Event
     * kommt oder eines mit einem anderen Label.
     */
    public function alleAbhakenBestaetigen(): void
    {
        $eigene = $this->liste()->anzahl();
        $mealie = count($this->mealieZumAbhaken());

        if ($eigene === 0 && $mealie === 0) {
            return;
        }

        Dialog::alert(
            'Alles abhaken?',
            $this->alleAbhakenFrage($eigene, $mealie),
            [
                ['label' => 'Abbrechen', 'style' => 'cancel'],
                ['label' => 'Abhaken', 'style' => 'default'],
            ]
        )->buttonPressed(function (ButtonPressed $event): void {
            if ($event->label !== 'Abhaken') {
                return;
            }

            $this->liste()->alleEntfernen();

            $this->mealieAlleAbhaken();

            $this->listeNeuZeichnen();
        });
    }

    /**
     * Der Dialogtext: ein Satz je Seite, und jeder nur, wenn er etwas zu
     * sagen hat.
     */
    private function alleAbhakenFrage(int $eigene, int $mealie): string
    {
        $saetze = [];

        if ($eigene > 0) {
            $saetze[] = $eigene === 1
                ? '1 eigener Artikel wandert zurück in den Vorrat.'
                : $eigene.' eigene Artikel wandern zurück in den Vorrat.';
        }

        if ($mealie > 0) {
            $saetze[] = $mealie === 1
                ? '1 Mealie-Artikel wird abgehakt.'
                : $mealie.' Mealie-Artikel werden abgehakt.';
        }

        return implode(' ', $saetze);
    }

    /**
     * Die offenen Mealie-Artikel, die „Alles abhaken“ mitnimmt. Leer, solange
     * das Banner steht oder kein Token hinterlegt ist: dann kann die App
     * Mealie nichts melden und verspricht es im Dialog auch nicht.
     *
     * @return list<Eintrag>
     */
    private function mealieZumAbhaken(): array
    {
        if ($this->banner() !== null || $this->mealieToken() === null) {
            return [];
        }

        return app(Sitzung::class)->offene();
    }

    /**
     * Hakt alle offenen Mealie-Artikel in einem Zug ab: erst auf dem Screen,
     * dann — in einem einzigen Bulk-Update — in Mealie. Lehnt Mealie ab,
     * kehren sie in ihre Gruppen zurück.
     */
    private function mealieAlleAbhaken(): void
    {
        $eintraege = $this->mealieZumAbhaken();
        $token = $this->mealieToken();

        if ($eintraege === [] || $token === null) {
            return;
        }

        $ids = array_map(fn (Eintrag $eintrag) => $eintrag->id, $eintraege);
        $artikel = array_map(fn (Eintrag $eintrag) => $eintrag->roh, $eintraege);

        app(Sitzung::class)->hakenMehrere($ids, true);

        $basisUrl = (string) config('mealie.url');
        $timeout = (int) config('mealie.timeout');

        $this->async(static fn (): array => Artikelstatus::alleSetzen($basisUrl, $token, $timeout, $artikel, true))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis) use ($ids): void {
                if ($ergebnis['ok'] ?? false) {
                    return;
                }

                $this->mealieAbhakenZuruecknehmen($ids);
            })
            ->failed(function () use ($ids): void {
                $this->mealieAbhakenZuruecknehmen($ids);
            });
    }

    /**
     * Mealie hat das Bulk-Update nicht angenommen: die Artikel kehren in ihre
     * Gruppen zurück. Die eigenen Artikel bleiben entfernt — die hat Mealie
     * nie etwas angegangen.
     *
     * @param  list<string>  $ids
     */
    private function mealieAbhakenZuruecknehmen(array $ids): void
    {
        app(Sitzung::class)->hakenMehrere($ids, false);

        $this->listeNeuZeichnen();

        Dialog::toast('Mealie: Abhaken fehlgeschlagen');
    }

    public function oeffneEinstellungen(): void
    {
        $this->navigate('/einstellungen');
    }

    public function render(): Element
    {
        return $this->view('einkaufen');
    }

    /**
     * Holt die Mealie-Liste — aber nur, wenn ein Token da ist. „Gerät
     * gesperrt“ und „Lesefehler“ sind ausdrücklich kein „kein Token“: dann
     * wird weder geladen noch zum Verbinden aufgefordert.
     *
     * Der Aufruf läuft über `async`, weil der Runloop erst nach dem Handler
     * wieder rendert — synchron bliebe die Ladezeile unsichtbar und die
     * eigene Liste hinge, bis Mealie antwortet.
     */
    private function mealieLaden(): void
    {
        $ergebnis = app(Token::class)->lesen();

        $this->mealieNichtVerbunden = $ergebnis->status === SecureStorageStatus::NotFound;

        if ($this->mealieNichtVerbunden) {
            // Kein Token mehr: was aus Mealie kam, hat auf dem Screen nichts
            // mehr verloren — auch nicht aus dem Cache.
            app(Sitzung::class)->vergessen();

            $this->listeNeuZeichnen();
        }

        if (! $ergebnis->found()) {
            return;
        }

        $this->mealieLaedt = app(Sitzung::class)->ersterLadevorgang();

        $basisUrl = (string) config('mealie.url');
        $token = (string) $ergebnis->value;
        $listenId = (string) config('mealie.shopping_list_id');
        $timeout = (int) config('mealie.timeout');

        $this->async(static fn (): array => Einkaufsliste::laden($basisUrl, $token, $listenId, $timeout))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis): void {
                $this->mealieLaedt = false;

                if (isset($ergebnis['artikel'])) {
                    app(Sitzung::class)->setzen($ergebnis['artikel']);
                } else {
                    app(Sitzung::class)->fehlerMelden(Fehler::ausSchluessel((string) ($ergebnis['fehler'] ?? '')));
                }

                $this->listeNeuZeichnen();
            })
            ->failed(function (): void {
                $this->mealieLaedt = false;

                app(Sitzung::class)->fehlerMelden(Fehler::Netz);

                $this->listeNeuZeichnen();
            });
    }

    /**
     * Mealie hat die Änderung nicht angenommen: der Artikel springt in den
     * Zustand zurück, in dem er vor dem Tap war.
     */
    private function mealieAenderungZuruecknehmen(string $artikelId, bool $abgehakt): void
    {
        app(Sitzung::class)->haken($artikelId, ! $abgehakt);

        $this->listeNeuZeichnen();

        $this->mealieAenderungGescheitert();
    }

    private function mealieAenderungGescheitert(): void
    {
        Dialog::toast('Mealie: Änderung fehlgeschlagen');
    }

    private function mealieToken(): ?string
    {
        $ergebnis = app(Token::class)->lesen();

        return $ergebnis->found() ? (string) $ergebnis->value : null;
    }

    /** Beide Computed-Werte hängen an denselben Daten. */
    private function listeNeuZeichnen(): void
    {
        unset($this->abschnitte, $this->abgehakte);
    }

    private function liste(): EigeneListe
    {
        return app(EigeneListe::class);
    }

    private function uebersicht(): Uebersicht
    {
        return app(Uebersicht::class);
    }
}
