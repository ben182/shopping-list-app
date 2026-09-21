@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($banner = $this->banner())
@php($gewaehlterLaden = $this->gewaehlterLaden())

<native:column class="w-full h-full bg-theme-background">
    {{-- Direkt unter der Top-Bar: die Einladung, Mealie zu verbinden, das
         Fehlerbanner oder — nur beim ersten Laden einer Sitzung — der
         Hinweis, dass noch etwas unterwegs ist. Jedes weitere Laden bleibt
         stumm, und das Banner bleibt während eines Neuversuchs stehen,
         statt hin und her zu springen. --}}
    @if ($this->mealieNichtVerbunden)
        <native:row class="w-full items-center gap-2 bg-theme-surface px-4 py-2">
            <native:icon :ios="Ios::InfoCircle" :android="Android::Info" :size="20" a11y-label="Hinweis" class="text-theme-on-surface-variant" />
            <native:text class="flex-1 text-sm text-theme-on-surface-variant">Mealie nicht verbunden</native:text>
            <native:button
                ref="mealie-einstellungen"
                size="sm"
                variant="secondary"
                label="Einstellungen"
                @press="oeffneEinstellungen"
            />
        </native:row>
    @elseif ($banner !== null)
        <native:row class="w-full items-center gap-2 bg-theme-surface px-4 py-2">
            <native:icon :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" :size="20" a11y-label="Warnung" class="text-theme-accent" />
            <native:text class="flex-1 text-sm text-theme-on-surface-variant">{{ $banner->text() }}</native:text>
            {{-- Zwei Knöpfe statt einem mit bedingtem Handler: ein `@if` in
                 der Attributliste eines `native:`-Tags zerlegt der
                 Precompiler. --}}
            @if ($banner->tokenUngueltig())
                <native:button
                    ref="mealie-banner-aktion"
                    size="sm"
                    variant="secondary"
                    label="{{ $banner->aktion() }}"
                    @press="oeffneEinstellungen"
                />
            @else
                <native:button
                    ref="mealie-banner-aktion"
                    size="sm"
                    variant="secondary"
                    label="{{ $banner->aktion() }}"
                    @press="neuLaden"
                />
            @endif
        </native:row>
    @elseif ($this->mealieLaedt)
        <native:row class="w-full items-center gap-2 bg-theme-surface px-4 py-2">
            <native:activity-indicator size="sm" a11y-label="Mealie wird geladen" />
            <native:text class="text-sm text-theme-on-surface-variant">Mealie wird geladen…</native:text>
        </native:row>
    @endif

    {{-- Der Ladenfilter — nur, wenn es etwas zu filtern gibt; über einer
         leeren Liste wäre er Zierde. --}}
    @if ($this->gesamtzahl() > 0)
        @include('native.laden-filter', ['laeden' => $this->laeden(), 'gewaehlterLaden' => $gewaehlterLaden])
    @endif

    @if ($this->abschnitte === [] && $gewaehlterLaden !== null && $this->gesamtzahl() > 0)
        {{-- Nichts für diesen Laden, aber sehr wohl etwas auf der Liste: der
             Weg zurück steht in den Chips darüber, der Text zeigt darauf. --}}
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::Cart" :android="Android::ShoppingCart" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Nichts für {{ $gewaehlterLaden->bezeichnung() }} auf der Liste.</native:text>
            <native:text class="text-center text-sm text-theme-on-surface-variant">
                Tippe oben auf „Alle“, um die ganze Liste zu sehen.
            </native:text>
        </native:column>
    @elseif ($this->abschnitte === [])
        {{-- Füllt den Platz zwischen Banner und dem angepinnten Block
             „Abgehakt“ — der sitzt unten und nimmt ihn nicht mehr ein. --}}
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::Cart" :android="Android::ShoppingCart" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Liste ist leer.</native:text>
            <native:text class="text-center text-sm text-theme-on-surface-variant">
                Tippe oben auf „+“ oder auf den Vorrat-Tab, um Artikel hinzuzufügen.
            </native:text>
        </native:column>
    @endif

    @if ($this->abschnitte !== [])
        <native:list separator on-refresh="neuLaden" class="w-full flex-1 bg-theme-background">
            @foreach ($this->abschnitte as $abschnitt)
                {{-- Die Überschrift hängt am Abschnitt: fällt der Abschnitt weg, fällt sie mit. --}}
                <native:list-section header="{{ $abschnitt->name }}">
                    @foreach ($abschnitt->zeilen as $zeile)
                        @php($umschalten = "mealieUmschalten('{$zeile->id}')")
                        {{-- Das Besteck-Icon sagt „das wollte ein Rezept“ und bleibt ohne
                             eigene Farbe: die Renderer zeichnen ein Trailing-Icon von sich
                             aus in der gedämpften Sekundärfarbe, und eine feste Farbe hier
                             hätte keine Dark-Mode-Entsprechung. Zwei Zeilen statt einer mit
                             bedingtem Icon: ein `@if` in der Attributliste eines
                             `native:`-Tags zerlegt der Precompiler. --}}
                        @if ($zeile->ausRezept)
                            <native:list-item
                                native:key="{{ $zeile->id }}"
                                ref="mealie-{{ $zeile->id }}"
                                headline="{{ $zeile->text }}"
                                :supporting="$zeile->notiz ?? ''"
                                :leadingCheckbox="false"
                                :disabled="$banner !== null"
                                :trailingIconIos="Ios::ForkKnife"
                                :trailingIconAndroid="Android::Restaurant"
                                trailing-a11y-label="aus einem Rezept"
                                @press="{{ $umschalten }}"
                                on-leading-change="{{ $umschalten }}"
                            />
                        @else
                            <native:list-item
                                native:key="{{ $zeile->id }}"
                                ref="mealie-{{ $zeile->id }}"
                                headline="{{ $zeile->text }}"
                                :supporting="$zeile->notiz ?? ''"
                                :leadingCheckbox="false"
                                :disabled="$banner !== null"
                                @press="{{ $umschalten }}"
                                on-leading-change="{{ $umschalten }}"
                            />
                        @endif
                    @endforeach
                </native:list-section>
            @endforeach

            {{-- Die Rezepte, aus denen Mealie die Liste gefüllt hat — einmal
                 unter allen Warengruppen statt an jeder Zeile. Die Zeile
                 selbst trägt jetzt die Notiz aus Mealie. --}}
            @if ($this->rezepte !== [])
                <native:list-section header="Verknüpfte Rezepte ({{ count($this->rezepte) }})">
                    @foreach ($this->rezepte as $rezept)
                        @php($rezeptSchluessel = md5($rezept))
                        <native:list-item
                            native:key="rezept-{{ $rezeptSchluessel }}"
                            ref="rezept-{{ $rezeptSchluessel }}"
                            headline="{{ $rezept }}"
                            :leadingIconIos="Ios::ForkKnife"
                            :leadingIconAndroid="Android::Restaurant"
                            :headlineColor="theme('on-surface-variant', '#475569')"
                        />
                    @endforeach
                </native:list-section>
            @endif

            {{-- Luft am Listenende: sonst klebt die letzte Zeile an der Naht
                 zum angepinnten Block „Abgehakt“ und liest sich wie seine
                 erste. Fehlt der Block, steht darunter nur die Tab-Leiste —
                 dann braucht es eine ganze Zeilenhöhe, damit die letzte Zeile
                 frei über ihr steht. Ohne eigene Farbe: der Hintergrund der
                 Liste steht durch. --}}
            @php($luft = $this->abgehakte === [] ? 'h-14' : 'h-6')
            <native:column ref="listenende" class="w-full {{ $luft }}" />
        </native:list>
    @endif

    {{-- Die Rücknahme des letzten „Alles abhaken“ — ohne Timer, aber auch
         ohne zweiten Tap vorher. Sie sitzt über dem Block „Abgehakt“ und
         damit, wenn der fehlt, direkt über der Tab-Leiste. --}}
    @php($vorgang = $this->rueckgaengigVorgang())
    @if ($vorgang !== null)
        <native:row ref="rueckgaengig-leiste" class="w-full items-center gap-2 border-theme-outline bg-theme-surface px-4 py-2">
            <native:text class="flex-1 text-sm text-theme-on-surface">{{ $vorgang->text() }}</native:text>
            <native:button
                ref="rueckgaengig"
                size="sm"
                variant="ghost"
                label="Rückgängig"
                @press="rueckgaengigMachen"
            />
            <native:pressable
                ref="rueckgaengig-schliessen"
                class="h-10 w-10 items-center justify-center"
                a11y-label="Schließen"
                @press="leisteSchliessen"
            >
                <native:icon :ios="Ios::Xmark" :android="Android::Close" :size="20" class="text-theme-on-surface-variant" />
            </native:pressable>
        </native:row>
    @endif

    {{-- „Abgehakt“ sitzt fest über der Tab-Leiste statt am Ende der Liste:
         so bleibt er erreichbar, ohne durch die ganze Liste zu scrollen, und
         liest sich nicht mehr als letzte Zeile der Gruppe darüber. Er hängt
         ohne `list-section` an der Column, weil seine Überschrift tappbar
         sein muss — die eines Abschnitts ist es nicht. --}}
    @if ($this->abgehakte !== [])
        @php($aufgeklappt = $this->abgehakteAufgeklappt())
        @php($chevronIos = $aufgeklappt ? Ios::ChevronUp : Ios::ChevronDown)
        @php($chevronAndroid = $aufgeklappt ? Android::ExpandLess : Android::ExpandMore)
        <native:column ref="abgehakt-block" class="w-full bg-theme-surface">
            {{-- Die Naht zur Liste darüber. `border-t` kennt der Parser nicht,
                 seitenweise Ränder gibt es nicht — also eine Haarlinie. --}}
            <native:column class="w-full h-px bg-theme-outline-variant" />

            <native:list-item
                ref="abgehakt-kopf"
                headline="Abgehakt ({{ count($this->abgehakte) }})"
                :trailingIconIos="$chevronIos"
                :trailingIconAndroid="$chevronAndroid"
                trailing-a11y-label="{{ $aufgeklappt ? 'Zuklappen' : 'Aufklappen' }}"
                @press="abgehakteUmklappen"
            />

            @if ($aufgeklappt)
                {{-- Gedeckelt statt flex-1: aufgeklappt wächst der Block nach
                     oben, soll aber nicht die ganze Liste verdrängen. Die
                     Zeilen scrollen innerhalb dieser Höhe. --}}
                <native:list separator class="w-full max-h-80 bg-theme-surface">
                    @foreach ($this->abgehakte as $zeile)
                        @php($umschalten = "mealieUmschalten('{$zeile->id}')")
                        {{-- Gedämpft über den Theme-Wert der aktuellen
                             Darstellung: `headline-color` kennt keine eigene
                             Dark-Mode-Hälfte, `theme()` löst sie schon hier auf. --}}
                        <native:list-item
                            native:key="{{ $zeile->id }}"
                            ref="abgehakt-{{ $zeile->id }}"
                            headline="{{ $zeile->text }}"
                            :leadingCheckbox="true"
                            :disabled="$banner !== null"
                            :headlineColor="theme('on-surface-variant', '#475569')"
                            @press="{{ $umschalten }}"
                            on-leading-change="{{ $umschalten }}"
                        />
                    @endforeach
                </native:list>

                {{-- Der Weg, die Liste nach dem Einkauf leer zu bekommen.
                     Er steht unter den Zeilen und nicht im Kopf: der Kopf
                     klappt um, und ein zweites Ziel daneben trifft man im
                     Vorbeigehen falsch. Die Liste darüber ist gedeckelt und
                     scrollt in sich — der Knopf bleibt also stehen, auch
                     wenn zwanzig Zeilen darüber liegen. --}}
                <native:row class="w-full items-center justify-center px-4 py-2">
                    <native:button
                        ref="abgehakt-loeschen"
                        size="sm"
                        variant="ghost"
                        icon="{{ $papierkorbIcon }}"
                        label="{{ count($this->abgehakte) === 1 ? 'Artikel löschen' : count($this->abgehakte).' Artikel löschen' }}"
                        :disabled="$banner !== null"
                        @press="abgehakteLoeschenBestaetigen"
                    />
                </native:row>
            @endif
        </native:column>
    @endif
</native:column>
