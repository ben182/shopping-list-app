@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($suchbegriff = trim($this->suche))

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

    @if ($this->gruppen === [] && $suchbegriff !== '')
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::ExclamationmarkMagnifyingglass" :android="Android::SearchOff" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Keine Treffer für „{{ $suchbegriff }}“.</native:text>
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
        </native:list>
    @endif
</native:column>
