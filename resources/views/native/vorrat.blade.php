@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($suchbegriff = trim($this->suche))
@php($gewaehlterLaden = $this->gewaehlterLaden())

<native:column class="w-full h-full bg-theme-background">
    <native:row class="w-full items-center gap-2 px-4 pt-2 pb-1">
        {{-- `keyboard="url"` ist der Ersatz für autoCorrect/autoCapitalize: beide
             Props stehen in mobile-ui 0.3 noch offen (SHIPPING-CHECKLIST 3.1), die
             URL-Tastatur schaltet Autokorrektur und automatische Großschreibung auf
             beiden Plattformen ab. Sobald es die Props gibt, hier zurück auf "text". --}}
        <native:outlined-text-input
            ref="vorrat-suche"
            class="flex-1"
            placeholder="Artikel suchen…"
            leading-icon="{{ $lupenIcon }}"
            keyboard="url"
            sync-mode="debounce"
            debounce-ms="200"
            value="{{ $this->suche }}"
            @change="suchen"
        />

        {{-- Gehört optisch ans Ende des Feldes; ein Trailing-Icon im Textfeld ist
             in mobile-ui rein dekorativ und nimmt keinen @press. --}}
        @if ($this->suche !== '')
            <native:button
                ref="vorrat-suche-leeren"
                size="sm"
                variant="ghost"
                icon="{{ $leerenIcon }}"
                a11y-label="Suche leeren"
                @press="sucheLeeren"
            />
        @endif
    </native:row>

    {{-- Dieselben Chips wie auf dem Einkaufen-Screen, und dieselbe Wahl —
         nur, wenn im Vorrat überhaupt etwas zu filtern ist. --}}
    @if ($this->hatVorrat())
        @include('native.laden-filter', ['laeden' => $this->laeden(), 'gewaehlterLaden' => $gewaehlterLaden])
    @endif

    @if ($this->gruppen === [] && $suchbegriff !== '')
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::ExclamationmarkMagnifyingglass" :android="Android::SearchOff" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Keine Treffer für „{{ $suchbegriff }}“.</native:text>
            {{-- Ohne diesen Satz sucht man den Artikel, den der Laden-Chip
                 gerade wegfiltert, und hält ihn für nicht im Katalog. --}}
            @if ($gewaehlterLaden !== null)
                <native:text class="text-center text-sm text-theme-on-surface-variant">
                    Es werden nur Artikel für {{ $gewaehlterLaden->bezeichnung() }} gezeigt.
                </native:text>
            @endif
        </native:column>
    @elseif ($this->gruppen === [] && $gewaehlterLaden !== null && $this->hatVorrat())
        {{-- Für diesen Laden ist alles schon auf der Liste, anderswo aber
             nicht: der Weg zurück steht in den Chips darüber. --}}
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::Checkmark" :android="Android::Check" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Für {{ $gewaehlterLaden->bezeichnung() }} ist alles auf der Liste.</native:text>
            <native:text class="text-center text-sm text-theme-on-surface-variant">
                Tippe oben auf „Alle“, um den ganzen Vorrat zu sehen.
            </native:text>
        </native:column>
    @elseif ($this->gruppen === [])
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::Checkmark" :android="Android::Check" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Alles auf der Liste.</native:text>
        </native:column>
    @else
        <native:list separator class="w-full flex-1 bg-theme-background">
            @foreach ($this->gruppen as $gruppe)
                {{-- Die Überschrift hängt am Abschnitt: fällt der Abschnitt weg, fällt sie mit. --}}
                <native:list-section header="{{ $gruppe->name }}">
                    @foreach ($gruppe->artikel as $artikel)
                        {{-- Der Handler-Aufruf steht in einer Variablen, weil ein Argument in
                             Anführungszeichen direkt im Attribut den Callback-Parser von
                             `native:validate` aus dem Tritt bringt. --}}
                        @php($aufDieListe = "aufDieListe('{$artikel->id}')")
                        <native:list-item
                            native:key="{{ $artikel->id }}"
                            ref="vorrat-{{ $artikel->id }}"
                            headline="{{ $artikel->name }}"
                            :trailingIconIos="Ios::Plus"
                            :trailingIconAndroid="Android::Add"
                            @press="{{ $aufDieListe }}"
                        />
                    @endforeach
                </native:list-section>
            @endforeach

            {{-- Luft am Listenende: sonst klebt die letzte Zeile beim
                 Durchscrollen an der Tab-Leiste. Eine Zeilenhöhe, ohne eigene
                 Farbe — der Hintergrund der Liste steht durch. --}}
            <native:column ref="listenende" class="w-full h-14" />
        </native:list>
    @endif
</native:column>
