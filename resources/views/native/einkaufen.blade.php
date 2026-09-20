@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:column class="w-full h-full bg-theme-background">
    {{-- Direkt unter der Top-Bar: entweder die Einladung, Mealie zu verbinden,
         oder — nur beim ersten Laden einer Sitzung — der Hinweis, dass noch
         etwas unterwegs ist. Jedes weitere Laden bleibt stumm. --}}
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
    @elseif ($this->mealieLaedt)
        <native:row class="w-full items-center gap-2 bg-theme-surface px-4 py-2">
            <native:activity-indicator size="sm" a11y-label="Mealie wird geladen" />
            <native:text class="text-sm text-theme-on-surface-variant">Mealie wird geladen…</native:text>
        </native:row>
    @endif

    @if ($this->abschnitte === [])
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::Cart" :android="Android::ShoppingCart" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Liste ist leer.</native:text>
            <native:text class="text-center text-sm text-theme-on-surface-variant">
                Tippe auf den Vorrat-Tab, um Artikel hinzuzufügen.
            </native:text>
        </native:column>
    @else
        <native:list separator on-refresh="neuLaden" class="w-full flex-1 bg-theme-background">
            @foreach ($this->abschnitte as $abschnitt)
                {{-- Die Überschrift hängt am Abschnitt: fällt der Abschnitt weg, fällt sie mit. --}}
                <native:list-section header="{{ $abschnitt->name }}">
                    @foreach ($abschnitt->zeilen as $zeile)
                        @if ($zeile->ausMealie)
                            {{-- Das Besteck-Icon bleibt ohne eigene Farbe: die Renderer
                                 zeichnen ein Trailing-Icon von sich aus in der gedämpften
                                 Sekundärfarbe, und eine feste Farbe hier hätte keine
                                 Dark-Mode-Entsprechung. --}}
                            <native:list-item
                                native:key="{{ $zeile->id }}"
                                ref="mealie-{{ $zeile->id }}"
                                headline="{{ $zeile->text }}"
                                :supporting="$zeile->zusatz ?? ''"
                                :leadingCheckbox="false"
                                :trailingIconIos="Ios::ForkKnife"
                                :trailingIconAndroid="Android::Restaurant"
                                trailing-a11y-label="aus Mealie"
                            />
                        @else
                            {{-- Der Handler-Aufruf steht in einer Variablen, weil ein Argument in
                                 Anführungszeichen direkt im Attribut den Callback-Parser von
                                 `native:validate` aus dem Tritt bringt. --}}
                            @php($abhaken = "abhaken('{$zeile->id}')")
                            <native:list-item
                                native:key="{{ $zeile->id }}"
                                ref="einkaufen-{{ $zeile->id }}"
                                headline="{{ $zeile->text }}"
                                :leadingCheckbox="false"
                                @press="{{ $abhaken }}"
                            />
                        @endif
                    @endforeach
                </native:list-section>
            @endforeach
        </native:list>
    @endif
</native:column>
