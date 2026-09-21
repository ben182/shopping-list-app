@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($suchbegriff = trim($this->suche))
@php($gewaehlterLaden = $this->gewaehlterLaden())
@php($banner = $this->banner())

<native:column class="w-full h-full bg-theme-background">
    {{-- Dieselben drei Zustände wie beim Einkaufen und in derselben
         Rangfolge: kein Token, Fehler, erster Ladevorgang. --}}
    @if ($this->nichtVerbunden)
        <native:row class="w-full items-center gap-2 bg-theme-surface px-4 py-2">
            <native:icon :ios="Ios::InfoCircle" :android="Android::Info" :size="20" a11y-label="Hinweis" class="text-theme-on-surface-variant" />
            <native:text class="flex-1 text-sm text-theme-on-surface-variant">Mealie nicht verbunden</native:text>
            <native:button
                ref="vorrat-einstellungen"
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
                    ref="vorrat-banner-aktion"
                    size="sm"
                    variant="secondary"
                    label="{{ $banner->aktion() }}"
                    @press="oeffneEinstellungen"
                />
            @else
                <native:button
                    ref="vorrat-banner-aktion"
                    size="sm"
                    variant="secondary"
                    label="{{ $banner->aktion() }}"
                    @press="neuLaden"
                />
            @endif
        </native:row>
    @elseif ($this->laedt)
        <native:row class="w-full items-center gap-2 bg-theme-surface px-4 py-2">
            <native:activity-indicator size="sm" a11y-label="Vorrat wird geladen" />
            <native:text class="text-sm text-theme-on-surface-variant">Vorrat wird geladen…</native:text>
        </native:row>
    @endif

    @if ($this->hatOffene() || $suchbegriff !== '')
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
    @endif

    {{-- Dieselben Chips wie auf dem Einkaufen-Screen, und dieselbe Wahl —
         nur, wenn im Vorrat überhaupt etwas zu filtern ist. --}}
    @if ($this->hatOffene())
        @include('native.laden-filter', ['laeden' => $this->laeden(), 'gewaehlterLaden' => $gewaehlterLaden])
    @endif

    @if ($this->abschnitte === [] && $suchbegriff !== '')
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::ExclamationmarkMagnifyingglass" :android="Android::SearchOff" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Keine Treffer für „{{ $suchbegriff }}“.</native:text>
            {{-- Ohne diesen Satz sucht man den Artikel, den der Laden-Chip
                 gerade wegfiltert, und hält ihn für nicht im Vorrat. --}}
            @if ($gewaehlterLaden !== null)
                <native:text class="text-center text-sm text-theme-on-surface-variant">
                    Es werden nur Artikel für {{ $gewaehlterLaden->bezeichnung() }} gezeigt.
                </native:text>
            @endif
        </native:column>
    @elseif ($this->abschnitte === [] && $gewaehlterLaden !== null && $this->hatOffene())
        {{-- Für diesen Laden ist alles schon auf der Liste, anderswo aber
             nicht: der Weg zurück steht in den Chips darüber. --}}
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::Checkmark" :android="Android::Check" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Für {{ $gewaehlterLaden->bezeichnung() }} ist alles auf der Liste.</native:text>
            <native:text class="text-center text-sm text-theme-on-surface-variant">
                Tippe oben auf „Alle“, um den ganzen Vorrat zu sehen.
            </native:text>
        </native:column>
    @elseif ($this->abschnitte === [] && $this->hatVorrat())
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::Checkmark" :android="Android::Check" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Alles auf der Liste.</native:text>
        </native:column>
    @elseif ($this->abschnitte === [] && ! $this->nichtVerbunden)
        {{-- Kein Vorrat da: entweder ist die Liste in Mealie leer oder sie
             ist noch unterwegs. Der Satz sagt, wo sie gepflegt wird — in der
             App gibt es dafür bewusst keine Oberfläche. --}}
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::Tray" :android="Android::Inventory" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Der Vorrat ist leer.</native:text>
            <native:text class="text-center text-sm text-theme-on-surface-variant">
                Er steht in Mealie in der Liste „Vorrat“ — dort wird er gepflegt.
            </native:text>
        </native:column>
    @elseif ($this->abschnitte === [])
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::Tray" :android="Android::Inventory" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Ohne Mealie kein Vorrat.</native:text>
            <native:text class="text-center text-sm text-theme-on-surface-variant">
                Hinterlege oben in den Einstellungen ein Token.
            </native:text>
        </native:column>
    @else
        <native:list separator on-refresh="neuLaden" class="w-full flex-1 bg-theme-background">
            @foreach ($this->abschnitte as $abschnitt)
                {{-- Die Überschrift hängt am Abschnitt: fällt der Abschnitt weg, fällt sie mit. --}}
                <native:list-section header="{{ $abschnitt->name }}">
                    @foreach ($abschnitt->artikel as $artikel)
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
