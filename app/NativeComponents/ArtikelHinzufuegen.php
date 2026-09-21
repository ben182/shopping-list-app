<?php

namespace App\NativeComponents;

use App\Einkaufen\Gruppenoption;
use App\Einkaufen\Gruppenwahl;
use App\Katalog\Laden;
use App\Mealie\Artikelanlage;
use App\Mealie\Einkaufsliste;
use App\Mealie\Gruppenzuordnung;
use App\Mealie\Sitzung;
use App\Mealie\Token;
use App\Mealie\Warengruppen;
use Native\Mobile\Edge\Element;
use Native\Mobile\Facades\Dialog;

/**
 * Ein Artikel von Hand: Wattepads, Blumenerde, das Geschenkpapier — alles,
 * was weder aus einem Rezept kommt noch im Vorrat steht.
 *
 * Der Screen fragt drei Dinge: was es ist, unter welche Warengruppe es
 * gehört und in welchen Läden es das gibt. Die Gruppe entscheidet, unter
 * welcher Überschrift die Zeile landet, die Läden, ob sie ein Filter
 * stehen lässt — beides ist optional, ohne Angabe steht der Artikel unter
 * „Sonstiges“ und in jedem Laden.
 *
 * Anders als der Vorrat legt dieser Screen nicht vorab auf die Liste und
 * navigiert sofort zurück: er wartet auf Mealie. Ginge er gleich zurück,
 * liefe sein POST gegen das Neuladen des Einkaufen-Screens — und bei einer
 * Absage wäre das Getippte weg, statt noch im Feld zu stehen.
 */
class ArtikelHinzufuegen extends Screen
{
    /** Was im Namensfeld steht — ungetrimmt, wie das Gerät es schickt. */
    public string $name = '';

    /** Das gewählte Mealie-Label — leer heißt „Sonstiges“. */
    public string $labelId = '';

    /**
     * Die Läden, in denen es den Artikel gibt, als Schlüssel. Leer heißt
     * „überall“ — der Artikel steht dann in jedem Filter.
     *
     * @var list<string>
     */
    public array $gewaehlteLaeden = [];

    /**
     * Die Labels, die Mealie kennt — die Grundlage der Warengruppen-Chips.
     *
     * @var list<array{id: string, name: string}>
     */
    public array $labels = [];

    public bool $gruppenLaedt = false;

    /** Sind die Warengruppen nicht zu holen gewesen? Dann bleibt „Sonstiges“. */
    public bool $gruppenGescheitert = false;

    /** Läuft gerade die Anlage in Mealie? Treibt den Spinner am Knopf. */
    public bool $sendet = false;

    /** Ohne Token gibt es nichts anzulegen — dann steht statt der Felder ein Hinweis. */
    public bool $nichtVerbunden = false;

    public function navTitle(): string
    {
        return 'Artikel hinzufügen';
    }

    public function mount(): void
    {
        $this->nichtVerbunden = $this->token() === null;

        if (! $this->nichtVerbunden) {
            $this->gruppenLaden();
        }
    }

    /**
     * Die Warengruppen zur Wahl — die des Katalogs, soweit Mealie ein Label
     * dafür kennt.
     *
     * @return list<Gruppenoption>
     */
    public function gruppen(): array
    {
        return app(Gruppenwahl::class)->optionen($this->labels);
    }

    /** Die Überschrift, unter der ein Artikel ohne Warengruppe landet. */
    public function gruppeOhneLabel(): string
    {
        return app(Gruppenzuordnung::class)->gruppeOhneLabel();
    }

    /**
     * Die Läden als Chips — dieselben wie im Filter, hier aber mehrfach
     * wählbar: „gibt es bei dm und bei Rossmann“ ist der Normalfall.
     *
     * @return list<Laden>
     */
    public function laeden(): array
    {
        return Laden::alle();
    }

    public function nameGetippt(string $eingabe): void
    {
        $this->name = $eingabe;
    }

    /**
     * Ein Tap auf einen Gruppen-Chip. Der Chip „Sonstiges“ schickt einen
     * leeren Wert; ein zweiter Tap auf den aktiven Chip führt dorthin
     * zurück.
     */
    public function gruppeWaehlen(string $labelId): void
    {
        $this->labelId = $this->labelId === $labelId ? '' : $labelId;
    }

    /** Ein Tap auf einen Laden-Chip nimmt ihn dazu oder wieder heraus. */
    public function ladenUmschalten(string $schluessel): void
    {
        if (Laden::tryFrom($schluessel) === null) {
            return;
        }

        $this->gewaehlteLaeden = in_array($schluessel, $this->gewaehlteLaeden, strict: true)
            ? array_values(array_diff($this->gewaehlteLaeden, [$schluessel]))
            : [...$this->gewaehlteLaeden, $schluessel];
    }

    /** Der Chip „Überall“ — er hebt die Ladenauswahl auf. */
    public function laedenLeeren(): void
    {
        $this->gewaehlteLaeden = [];
    }

    /**
     * Legt den Artikel in Mealie an und geht erst danach zurück. Die
     * Einkaufsliste bekommt ihn hier schon mit, damit sie ihn zeigt, bevor
     * ihr eigener Ladevorgang durch ist.
     */
    public function hinzufuegen(): void
    {
        $name = trim($this->name);

        if ($name === '') {
            Dialog::toast('Bitte einen Artikel eingeben');

            return;
        }

        $token = $this->token();

        if ($token === null) {
            Dialog::toast('Ohne Mealie-Token geht das nicht');

            return;
        }

        $this->sendet = true;

        $basisUrl = (string) config('mealie.url');
        $timeout = (int) config('mealie.timeout');
        $listenId = (string) config('mealie.shopping_list_id');
        $vorlage = $this->vorlage($name);

        $this->async(static fn (): array => Artikelanlage::ausfuehren($basisUrl, $token, $timeout, $listenId, $vorlage))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis): void {
                $this->sendet = false;

                if (! ($ergebnis['ok'] ?? false)) {
                    Dialog::toast('Mealie: Hinzufügen fehlgeschlagen');

                    return;
                }

                app(Sitzung::class)->einfuegen(Einkaufsliste::ausRohdaten($ergebnis['artikel'] ?? []));

                $this->back();
            })
            ->failed(function (): void {
                $this->sendet = false;

                Dialog::toast('Mealie: Hinzufügen fehlgeschlagen');
            });
    }

    public function render(): Element
    {
        return $this->view('artikel-hinzufuegen');
    }

    /**
     * Die Vorlage, aus der App\Mealie\Artikelanlage die Nutzlast baut — von
     * Hand gebaut statt aus Mealie kopiert. Ohne `foodId` legt Mealie einen
     * Artikel an, der seinen Text in der Notiz trägt; genau das tut auch,
     * wer in Mealie selbst eine Zeile tippt.
     *
     * @return array<string, mixed>
     */
    private function vorlage(string $name): array
    {
        $vorlage = [
            'note' => $name,
            'quantity' => 0,
        ];

        if ($this->labelId !== '') {
            $vorlage['labelId'] = $this->labelId;
        }

        // Mealies Extras halten nur flache Zeichenketten — mehrere Läden
        // stehen deshalb kommagetrennt, so wie sie aus dem Vorrat kommen.
        if ($this->gewaehlteLaeden !== []) {
            $vorlage['extras'] = ['laeden' => implode(',', $this->gewaehlteLaeden)];
        }

        return $vorlage;
    }

    /**
     * Holt die Mealie-Labels. Scheitert das, bleibt es bei „Sonstiges“ —
     * ein Artikel ohne Warengruppe ist besser als kein Artikel.
     */
    private function gruppenLaden(): void
    {
        $token = $this->token();

        if ($token === null) {
            return;
        }

        $this->gruppenLaedt = true;

        $basisUrl = (string) config('mealie.url');
        $timeout = (int) config('mealie.timeout');

        $this->async(static fn (): array => Warengruppen::laden($basisUrl, $token, $timeout))
            ->timeout($timeout + 5)
            ->finished(function (array $ergebnis): void {
                $this->gruppenLaedt = false;
                $this->gruppenGescheitert = ! isset($ergebnis['labels']);
                $this->labels = $ergebnis['labels'] ?? [];
            })
            ->failed(function (): void {
                $this->gruppenLaedt = false;
                $this->gruppenGescheitert = true;
            });
    }

    private function token(): ?string
    {
        $ergebnis = app(Token::class)->lesen();

        return $ergebnis->found() ? (string) $ergebnis->value : null;
    }
}
