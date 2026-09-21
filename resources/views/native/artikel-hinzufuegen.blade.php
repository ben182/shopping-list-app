@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:column class="w-full h-full bg-theme-background gap-3 px-4 pt-4">
    @if ($this->nichtVerbunden)
        {{-- Ohne Token geht gar nichts: der Artikel entsteht in Mealie, nicht
             im Gerät. Statt eines Formulars, das beim Absenden scheitert,
             steht hier der Weg dorthin. --}}
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::InfoCircle" :android="Android::Info" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Mealie nicht verbunden.</native:text>
            <native:text class="text-center text-sm text-theme-on-surface-variant">
                Hinterlege in den Einstellungen ein Token, dann lassen sich hier Artikel anlegen.
            </native:text>
        </native:column>
    @else
        {{-- `keyboard="url"` schaltet Autokorrektur und automatische
             Großschreibung ab, wie im Suchfeld des Vorrats — bis mobile-ui
             eigene Props dafür hat. --}}
        <native:outlined-text-input
            ref="artikel-name"
            class="w-full"
            label="Artikel"
            placeholder="z. B. Wattepads"
            keyboard="url"
            sync-mode="debounce"
            debounce-ms="200"
            value="{{ $this->name }}"
            @change="nameGetippt"
        />

        <native:text class="text-sm text-theme-on-surface-variant">Warengruppe</native:text>

        @if ($this->gruppenLaedt)
            <native:row class="w-full items-center gap-2">
                <native:activity-indicator size="sm" a11y-label="Warengruppen werden geladen" />
                <native:text class="text-sm text-theme-on-surface-variant">Warengruppen werden geladen…</native:text>
            </native:row>
        @endif

        {{-- „Sonstiges“ steht immer da und ist die Vorgabe: die Überschrift,
             unter der ein Artikel ohne Warengruppe landet. Die übrigen Chips
             sind die Katalog-Gruppen, für die Mealie ein Label kennt. --}}
        <native:row class="w-full flex-wrap items-center gap-2">
            @php($ohneGruppe = 'gruppeWaehlen(\'\')')
            <native:chip
                ref="gruppe-sonstiges"
                label="{{ $this->gruppeOhneLabel() }}"
                :selected="$this->labelId === ''"
                @change="{{ $ohneGruppe }}"
            />
            @foreach ($this->gruppen() as $gruppe)
                {{-- Der Handler-Aufruf steht in einer Variablen, weil ein Argument in
                     Anführungszeichen direkt im Attribut den Callback-Parser von
                     `native:validate` aus dem Tritt bringt. --}}
                @php($gruppeWaehlen = "gruppeWaehlen('{$gruppe->labelId}')")
                <native:chip
                    native:key="gruppe-{{ $gruppe->labelId }}"
                    ref="gruppe-{{ $gruppe->labelId }}"
                    label="{{ $gruppe->name }}"
                    :selected="$this->labelId === $gruppe->labelId"
                    @change="{{ $gruppeWaehlen }}"
                />
            @endforeach
        </native:row>

        @if ($this->gruppenGescheitert)
            <native:text class="text-sm text-theme-on-surface-variant">
                Die Warengruppen aus Mealie sind gerade nicht zu haben — der Artikel landet unter „{{ $this->gruppeOhneLabel() }}“.
            </native:text>
        @endif

        <native:text class="text-sm text-theme-on-surface-variant">Wo gibt es das?</native:text>

        {{-- Mehrfachwahl, anders als beim Filter: ein Artikel ist oft in
             mehreren Läden zu haben. „Überall“ ist die Vorgabe und heißt,
             dass ihn kein Ladenfilter wegnimmt. --}}
        <native:row class="w-full flex-wrap items-center gap-2">
            <native:chip
                ref="laden-ueberall"
                label="Überall"
                :selected="$this->gewaehlteLaeden === []"
                @change="laedenLeeren"
            />
            @foreach ($this->laeden() as $laden)
                @php($ladenUmschalten = "ladenUmschalten('{$laden->value}')")
                <native:chip
                    native:key="laden-{{ $laden->value }}"
                    ref="laden-{{ $laden->value }}"
                    label="{{ $laden->bezeichnung() }}"
                    :selected="in_array($laden->value, $this->gewaehlteLaeden, true)"
                    @change="{{ $ladenUmschalten }}"
                />
            @endforeach
        </native:row>

        <native:button
            ref="artikel-anlegen"
            class="w-full"
            label="Auf die Liste"
            :disabled="trim($this->name) === ''"
            :loading="$this->sendet"
            @press="hinzufuegen"
        />
    @endif
</native:column>
