<?php

namespace App\NativeComponents;

use App\Einkaufen\Abhakvorgang;
use App\Einkaufen\Abschnitt;
use App\Einkaufen\Rueckgaengig;
use App\Einkaufen\Uebersicht;
use App\Einkaufen\Zeile;
use App\Icons\Android;
use App\Icons\Ios;
use App\Katalog\Laden;
use App\Katalog\Ladenfilter;
use App\Mealie\Artikelstatus;
use App\Mealie\Einkaufsliste;
use App\Mealie\Eintrag;
use App\Mealie\Fehler;
use App\Mealie\Fehlerzustand;
use App\Mealie\Sitzung;
use App\Mealie\Token;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBarOptions;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\SecureStorageStatus;

class Einkaufen extends Screen
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
     * Die Anzahl der offenen Artikel auf diesem Screen. Filtert gerade ein
     * Laden, steht daneben, wie viele es ohne ihn wären: sonst sähe eine
     * halbe Liste aus wie die ganze.
     */
    public function navSubtitle(): ?string
    {
        $uebersicht = $this->uebersicht();
        $anzahl = $uebersicht->anzahl();
        $gesamt = $uebersicht->gesamtzahl();

        if ($gesamt === 0) {
            return null;
        }

        return $anzahl === $gesamt
            ? $gesamt.' Artikel'
            : $anzahl.' von '.$gesamt.' Artikeln';
    }

    /**
     * Beim Öffnen des Tabs steht die Liste aus dem Cache sofort da; Mealie
     * kommt nach, sobald die Antwort da ist.
     */
    public function mount(): void
    {
        // Ein Tab-Wechsel mountet diesen Screen neu — und räumt damit die
        // Leiste ab, ohne dass der Tab-Wechsel selbst davon wissen muss.
        $this->leisteVerwerfen();

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
     * Die Leiste nach „Alles abhaken“ — `null`, solange es nichts
     * zurückzunehmen gibt.
     */
    public function rueckgaengigVorgang(): ?Abhakvorgang
    {
        return app(Rueckgaengig::class)->vorgang();
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
        $this->leisteVerwerfen();

        $this->mealieLaden();
    }

    /** Zurück aus dem Hintergrund — dasselbe wie beim Öffnen des Tabs. */
    protected function wiederImVordergrund(): void
    {
        $this->leisteVerwerfen();

        $this->mealieLaden();
    }

    /**
     * Rechts in der Top-Bar: „Alles abhaken“, solange irgendetwas offen ist,
     * und das Zahnrad — der einzige Weg zu den Einstellungen, der von überall
     * erreichbar ist.
     */
    public function navigationOptions(): ?NavBarOptions
    {
        $optionen = NavBarOptions::make();

        if ($this->uebersicht()->anzahl() > 0) {
            $laden = $this->gewaehlterLaden();

            $optionen->action(
                NavAction::make('alles-abhaken')
                    ->icon(ios: Ios::CheckmarkCircle, android: Android::DoneAll)
                    ->a11yLabel($laden === null ? 'Alles abhaken' : 'Alles bei '.$laden->bezeichnung().' abhaken')
                    ->press('alleAbhaken')
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
     * Die Läden, die als Chips über der Liste stehen — in der Reihenfolge des
     * Enums, „Alle“ setzt die View davor.
     *
     * @return list<Laden>
     */
    public function laeden(): array
    {
        return Laden::alle();
    }

    /**
     * Wie viele Artikel offen wären, stünde der Filter auf „Alle“. Die View
     * entscheidet daran, ob die Chips überhaupt etwas zu filtern haben.
     */
    public function gesamtzahl(): int
    {
        return $this->uebersicht()->gesamtzahl();
    }

    /** Welcher Chip aktiv ist — `null` heißt „Alle“. */
    public function gewaehlterLaden(): ?Laden
    {
        return app(Ladenfilter::class)->laden();
    }

    /**
     * Ein Tap auf einen Chip. Der Chip „Alle“ schickt einen leeren
     * Schlüssel; ein zweiter Tap auf den aktiven Chip führt zurück auf
     * „Alle“, damit man nicht erst zurückzielen muss.
     */
    public function ladenWaehlen(string $schluessel): void
    {
        $this->leisteVerwerfen();

        $filter = app(Ladenfilter::class);

        $filter->setzen($filter->laden()?->value === $schluessel ? '' : $schluessel);

        $this->listeNeuZeichnen();
    }

    /**
     * Was auf der Liste steht — die offenen Artikel, gruppiert nach
     * Warengruppe.
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

    /**
     * Die Rezepte hinter der Mealie-Liste — der Block unter den Warengruppen.
     *
     * @return list<string>
     */
    #[Computed]
    public function rezepte(): array
    {
        return $this->uebersicht()->rezepte();
    }

    public function abgehakteAufgeklappt(): bool
    {
        return app(Sitzung::class)->abgehakteAufgeklappt();
    }

    public function abgehakteUmklappen(): void
    {
        $this->leisteVerwerfen();

        app(Sitzung::class)->abgehakteUmklappen();
    }

    /**
     * Ein Tap auf eine Mealie-Zeile — abhaken, wenn sie offen war, sonst
     * zurückholen. Die Liste springt sofort, Mealie erfährt es nebenher;
     * widerspricht Mealie, springt sie zurück.
     */
    public function mealieUmschalten(string $artikelId): void
    {
        $this->leisteVerwerfen();

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
     * Ein Tap, und die Liste ist leer: alle offenen Artikel bekommen ihren
     * Haken. Keine Nachfrage — der Fehlgriff kostet nichts, weil unten die
     * Leiste stehen bleibt, die ihn zurücknimmt.
     */
    public function alleAbhaken(): void
    {
        $artikel = $this->mealieZumAbhaken();

        if ($artikel === []) {
            return;
        }

        $vorgang = new Abhakvorgang(array_map(fn (Eintrag $eintrag) => $eintrag->roh, $artikel));

        app(Rueckgaengig::class)->merken($vorgang);

        $this->mealieAlleAbhaken($artikel, $vorgang);

        $this->listeNeuZeichnen();
    }

    /**
     * Der Weg zurück: genau die Artikel dieses Vorgangs kommen wieder — in
     * einem einzigen Bulk-Update auf „offen“.
     */
    public function rueckgaengigMachen(): void
    {
        $vorgang = $this->rueckgaengigVorgang();

        if ($vorgang === null) {
            return;
        }

        $this->leisteVerwerfen();

        $this->mealieZurueckholen($vorgang->artikel());

        $this->listeNeuZeichnen();
    }

    /** Das Kreuz rechts in der Leiste. */
    public function leisteSchliessen(): void
    {
        $this->leisteVerwerfen();
    }

    /**
     * Die offenen Artikel, die „Alles abhaken“ mitnimmt — die, die gerade
     * dastehen. Leer, solange das Banner steht oder kein Token hinterlegt
     * ist: dann kann die App Mealie nichts melden und zählt es in der Leiste
     * auch nicht mit.
     *
     * @return list<Eintrag>
     */
    private function mealieZumAbhaken(): array
    {
        if ($this->banner() !== null || $this->mealieToken() === null) {
            return [];
        }

        return $this->uebersicht()->offeneMealieArtikel();
    }

    /**
     * Hakt alle offenen Artikel in einem Zug ab: erst auf dem Screen, dann —
     * in einem einzigen Bulk-Update — in Mealie. Lehnt Mealie ab, kehren sie
     * in ihre Gruppen zurück und die Leiste verschwindet.
     *
     * @param  list<Eintrag>  $eintraege
     */
    private function mealieAlleAbhaken(array $eintraege, Abhakvorgang $vorgang): void
    {
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
            ->finished(function (array $ergebnis) use ($ids, $vorgang): void {
                if ($ergebnis['ok'] ?? false) {
                    return;
                }

                $this->mealieAbhakenZuruecknehmen($ids, $vorgang);
            })
            ->failed(function () use ($ids, $vorgang): void {
                $this->mealieAbhakenZuruecknehmen($ids, $vorgang);
            });
    }

    /**
     * Mealie hat das Bulk-Update nicht angenommen: die Artikel kehren in ihre
     * Gruppen zurück, und mit dem leeren Vorgang verschwindet die Leiste.
     *
     * @param  list<string>  $ids
     */
    private function mealieAbhakenZuruecknehmen(array $ids, Abhakvorgang $vorgang): void
    {
        app(Sitzung::class)->hakenMehrere($ids, false);

        $vorgang->vergessen();

        $this->listeNeuZeichnen();

        Dialog::toast('Mealie: Abhaken fehlgeschlagen');
    }

    /**
     * Setzt die Artikel eines zurückgenommenen Vorgangs in einem Zug wieder
     * auf „offen“. Ohne Token passiert nichts — dann ist beim Abhaken
     * ohnehin nichts an Mealie gegangen.
     *
     * @param  list<array<string, mixed>>  $artikel  Mealies Darstellung der Artikel
     */
    private function mealieZurueckholen(array $artikel): void
    {
        $token = $this->mealieToken();

        if ($artikel === [] || $token === null) {
            return;
        }

        $ids = array_map(fn (array $eintrag) => (string) ($eintrag['id'] ?? ''), $artikel);

        app(Sitzung::class)->hakenMehrere($ids, false);

        $basisUrl = (string) config('mealie.url');
        $timeout = (int) config('mealie.timeout');

        $this->async(static fn (): array => Artikelstatus::alleSetzen($basisUrl, $token, $timeout, $artikel, false))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis) use ($ids): void {
                if ($ergebnis['ok'] ?? false) {
                    return;
                }

                $this->mealieZurueckholenZuruecknehmen($ids);
            })
            ->failed(function () use ($ids): void {
                $this->mealieZurueckholenZuruecknehmen($ids);
            });
    }

    /**
     * Mealie hat das Zurückholen nicht angenommen: die Artikel bleiben
     * abgehakt.
     *
     * @param  list<string>  $ids
     */
    private function mealieZurueckholenZuruecknehmen(array $ids): void
    {
        app(Sitzung::class)->hakenMehrere($ids, true);

        $this->listeNeuZeichnen();

        Dialog::toast('Mealie: Zurückholen fehlgeschlagen');
    }

    public function oeffneEinstellungen(): void
    {
        $this->leisteVerwerfen();

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
     * wieder rendert — synchron bliebe die Ladezeile unsichtbar und der
     * Screen hinge, bis Mealie antwortet.
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

    /** Alle drei Computed-Werte hängen an denselben Daten. */
    private function listeNeuZeichnen(): void
    {
        unset($this->abschnitte, $this->abgehakte, $this->rezepte);
    }

    /** Die erste Interaktion nach „Alles abhaken“ räumt die Leiste ab. */
    private function leisteVerwerfen(): void
    {
        app(Rueckgaengig::class)->verwerfen();
    }

    private function uebersicht(): Uebersicht
    {
        return app(Uebersicht::class);
    }
}
