<?php

namespace App\NativeComponents;

use App\Icons\Android;
use App\Icons\Ios;
use App\Katalog\Laden;
use App\Katalog\Ladenfilter;
use App\Mealie\Artikelanlage;
use App\Mealie\Artikelstatus;
use App\Mealie\Einkaufsliste;
use App\Mealie\Eintrag;
use App\Mealie\Fehler;
use App\Mealie\Fehlerzustand;
use App\Mealie\Sitzung as Einkaufssitzung;
use App\Mealie\Token;
use App\Vorrat\Abschnitt;
use App\Vorrat\Artikel;
use App\Vorrat\Sitzung;
use App\Vorrat\Uebersicht;
use App\Vorrat\Vorratsliste;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBarOptions;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\SecureStorageStatus;

/**
 * Der Vorrat: was man immer im Haus haben will, gepflegt als zweite
 * Einkaufsliste in Mealie. Ein Tap kopiert einen Artikel auf die
 * Einkaufsliste; im Vorrat bleibt er stehen, bis er dort wieder abgehakt ist.
 */
class Vorrat extends Screen
{
    /**
     * Der Suchtext, wie er im Feld steht — ungetrimmt, damit der Löschen-Button
     * auch bei reinen Leerzeichen erscheint. Als Komponenten-Zustand ist er
     * beim nächsten Öffnen des Tabs wieder leer.
     */
    public string $suche = '';

    /**
     * Läuft gerade der erste Ladevorgang dieser Sitzung? Nur der bekommt eine
     * sichtbare Zeile; jeder weitere läuft still, damit die Liste beim
     * Tab-Wechsel nicht flackert.
     */
    public bool $laedt = false;

    /** Ist gar kein Token hinterlegt? Dann steht statt des Vorrats ein Hinweis. */
    public bool $nichtVerbunden = false;

    public function navTitle(): string
    {
        return 'Vorrat';
    }

    public function navSubtitle(): ?string
    {
        $anzahl = count(app(Einkaufssitzung::class)->offene());

        return $anzahl === 0
            ? 'Tippe auf einen Artikel zum Hinzufügen'
            : $anzahl.' auf der Liste';
    }

    /** Das Zahnrad — von hier aus kommt man an das Mealie-Token. */
    public function navigationOptions(): ?NavBarOptions
    {
        return NavBarOptions::make()->action(
            NavAction::make('einstellungen')
                ->icon(ios: Ios::Gearshape, android: Android::Settings)
                ->a11yLabel('Einstellungen')
                ->press('oeffneEinstellungen')
        );
    }

    public function mount(): void
    {
        $this->listenLaden();
    }

    /** Nach der Rückkehr von den Einstellungen — dort kann ein Token dazugekommen sein. */
    public function onResume(): void
    {
        $this->listenLaden();
    }

    /** Pull-to-Refresh an der Liste, und der Knopf im Banner. */
    public function neuLaden(): void
    {
        $this->listenLaden();
    }

    /**
     * Was das Banner unter der Top-Bar sagt — `null`, solange Mealie
     * mitspielt.
     */
    public function banner(): ?Fehlerzustand
    {
        return app(Sitzung::class)->fehlerzustand();
    }

    /**
     * Die Artikel des Vorrats, gruppiert nach Warengruppe — ohne die, die
     * schon auf der Einkaufsliste stehen.
     *
     * @return list<Abschnitt>
     */
    #[Computed]
    public function abschnitte(): array
    {
        return $this->uebersicht()->abschnitte($this->suche);
    }

    /**
     * Ist überhaupt etwas offen, ungeachtet von Suche und Laden? Die View
     * entscheidet daran, ob die Chips etwas zu filtern haben.
     */
    public function hatOffene(): bool
    {
        return $this->uebersicht()->offene() !== [];
    }

    /**
     * Steht überhaupt etwas in der Vorratsliste? Der Unterschied zu
     * {@see hatOffene()} trägt den Leerzustand: „alles schon auf der Liste“
     * ist etwas anderes als „in Mealie steht nichts“.
     */
    public function hatVorrat(): bool
    {
        return app(Sitzung::class)->alle() !== [];
    }

    /**
     * Die Läden als Chips über der Liste — dieselben wie beim Einkaufen,
     * und dieselbe Wahl: wer bei Lidl steht, will auch beim Nachlegen nur
     * sehen, was es dort gibt.
     *
     * @return list<Laden>
     */
    public function laeden(): array
    {
        return Laden::alle();
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
        $filter = app(Ladenfilter::class);

        $filter->setzen($filter->laden()?->value === $schluessel ? '' : $schluessel);

        $this->neuZeichnen();
    }

    public function suchen(string $eingabe): void
    {
        $this->suche = $eingabe;

        $this->neuZeichnen();
    }

    public function sucheLeeren(): void
    {
        $this->suche = '';

        $this->neuZeichnen();
    }

    /**
     * Ein Tap auf eine Vorratszeile: der Artikel wandert auf die
     * Einkaufsliste und verschwindet hier — sofort, lange bevor Mealie
     * geantwortet hat. Widerspricht Mealie, kommt er zurück.
     *
     * Liegt derselbe Artikel dort schon abgehakt, wird diese Zeile wieder
     * geöffnet, statt eine zweite für dasselbe anzulegen.
     */
    public function aufDieListe(string $artikelId): void
    {
        $artikel = app(Sitzung::class)->finden($artikelId);

        if ($artikel === null) {
            return;
        }

        $token = $this->token();

        if ($token === null || $this->banner() !== null) {
            Dialog::toast('Offline: Der Artikel kann gerade nicht auf die Liste');

            return;
        }

        $abgehakter = $this->uebersicht()->abgehakter($artikel);

        if ($abgehakter !== null) {
            $this->wiederOeffnen($abgehakter, $token);

            return;
        }

        $this->kopieren($artikel, $token);
    }

    public function oeffneEinstellungen(): void
    {
        $this->navigate('/einstellungen');
    }

    public function render(): Element
    {
        return $this->view('vorrat', [
            'lupenIcon' => $this->iconName(Ios::Magnifyingglass, Android::Search),
            'leerenIcon' => $this->iconName(Ios::Xmark, Android::Close),
        ]);
    }

    /** Zurück aus dem Hintergrund — dasselbe wie beim Öffnen des Tabs. */
    protected function wiederImVordergrund(): void
    {
        $this->listenLaden();
    }

    /**
     * Legt den Artikel als neue Zeile auf der Einkaufsliste an. Die Zeile
     * steht bis zur Antwort unter einer vorläufigen ID da; erst Mealies
     * Antwort bringt die, unter der sie sich später abhaken lässt.
     */
    private function kopieren(Artikel $artikel, string $token): void
    {
        $vorlaeufigeId = 'neu-'.$artikel->id;

        app(Einkaufssitzung::class)->einfuegen([
            'id' => $vorlaeufigeId,
            'text' => $artikel->name,
            'notiz' => null,
            'label' => $artikel->label,
            'rezepte' => [],
            'abgehakt' => false,
            'roh' => [],
            'laeden' => array_map(fn (Laden $laden) => $laden->value, $artikel->laeden),
        ]);

        $this->neuZeichnen();

        $basisUrl = (string) config('mealie.url');
        $timeout = (int) config('mealie.timeout');
        $listenId = (string) config('mealie.shopping_list_id');
        $vorlage = $artikel->roh;

        $this->async(static fn (): array => Artikelanlage::ausfuehren($basisUrl, $token, $timeout, $listenId, $vorlage))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis) use ($vorlaeufigeId): void {
                if (! ($ergebnis['ok'] ?? false)) {
                    $this->kopierenZuruecknehmen($vorlaeufigeId);

                    return;
                }

                app(Einkaufssitzung::class)->ersetzen(
                    $vorlaeufigeId,
                    Einkaufsliste::ausRohdaten($ergebnis['artikel'] ?? []),
                );

                $this->neuZeichnen();
            })
            ->failed(function () use ($vorlaeufigeId): void {
                $this->kopierenZuruecknehmen($vorlaeufigeId);
            });
    }

    /**
     * Derselbe Artikel liegt schon abgehakt auf der Einkaufsliste: sein Haken
     * fällt weg, statt dass eine zweite Zeile entsteht.
     */
    private function wiederOeffnen(Eintrag $eintrag, string $token): void
    {
        app(Einkaufssitzung::class)->haken($eintrag->id, false);

        $this->neuZeichnen();

        $basisUrl = (string) config('mealie.url');
        $timeout = (int) config('mealie.timeout');
        $artikel = $eintrag->roh;

        $this->async(static fn (): array => Artikelstatus::setzen($basisUrl, $token, $timeout, $artikel, false))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis) use ($eintrag): void {
                if ($ergebnis['ok'] ?? false) {
                    return;
                }

                app(Einkaufssitzung::class)->haken($eintrag->id, true);

                $this->neuZeichnen();

                Dialog::toast('Mealie: Änderung fehlgeschlagen');
            })
            ->failed(function () use ($eintrag): void {
                app(Einkaufssitzung::class)->haken($eintrag->id, true);

                $this->neuZeichnen();

                Dialog::toast('Mealie: Änderung fehlgeschlagen');
            });
    }

    private function kopierenZuruecknehmen(string $vorlaeufigeId): void
    {
        app(Einkaufssitzung::class)->entfernen($vorlaeufigeId);

        $this->neuZeichnen();

        Dialog::toast('Mealie: Hinzufügen fehlgeschlagen');
    }

    /**
     * Holt Vorrat und Einkaufsliste — beide, weil der Screen nur mit beiden
     * weiß, was noch fehlt. Ohne Token wird weder geladen noch etwas gezeigt:
     * der Vorrat lebt in Mealie, nicht mehr im Gerät.
     */
    private function listenLaden(): void
    {
        $ergebnis = app(Token::class)->lesen();

        $this->nichtVerbunden = $ergebnis->status === SecureStorageStatus::NotFound;

        if ($this->nichtVerbunden) {
            app(Sitzung::class)->vergessen();
            app(Einkaufssitzung::class)->vergessen();

            $this->neuZeichnen();
        }

        if (! $ergebnis->found()) {
            return;
        }

        $this->laedt = app(Sitzung::class)->ersterLadevorgang();

        $basisUrl = (string) config('mealie.url');
        $token = (string) $ergebnis->value;
        $timeout = (int) config('mealie.timeout');
        $vorratId = (string) config('mealie.vorrat_liste_id');
        $einkaufId = (string) config('mealie.shopping_list_id');

        $this->async(static fn (): array => Vorratsliste::laden($basisUrl, $token, $vorratId, $timeout))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis): void {
                $this->laedt = false;

                if (isset($ergebnis['artikel'])) {
                    app(Sitzung::class)->setzen($ergebnis['artikel']);
                } else {
                    app(Sitzung::class)->fehlerMelden(Fehler::ausSchluessel((string) ($ergebnis['fehler'] ?? '')));
                }

                $this->neuZeichnen();
            })
            ->failed(function (): void {
                $this->laedt = false;

                app(Sitzung::class)->fehlerMelden(Fehler::Netz);

                $this->neuZeichnen();
            });

        // Die Einkaufsliste sagt, was nicht mehr in den Vorrat gehört. Scheitert
        // sie, bleibt es beim zuletzt bekannten Stand — der Vorrat steht
        // trotzdem da, schlimmstenfalls mit einer Zeile zu viel.
        $this->async(static fn (): array => Einkaufsliste::laden($basisUrl, $token, $einkaufId, $timeout))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis): void {
                if (isset($ergebnis['artikel'])) {
                    app(Einkaufssitzung::class)->setzen($ergebnis['artikel']);

                    $this->neuZeichnen();
                }
            });
    }

    private function token(): ?string
    {
        $ergebnis = app(Token::class)->lesen();

        return $ergebnis->found() ? (string) $ergebnis->value : null;
    }

    private function neuZeichnen(): void
    {
        unset($this->abschnitte);
    }

    private function uebersicht(): Uebersicht
    {
        return app(Uebersicht::class);
    }
}
