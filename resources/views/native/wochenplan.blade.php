@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:column class="w-full h-full bg-theme-background">
    {{-- Die Wochen-Navigation steht über allem anderen: sie bleibt stehen,
         egal ob darunter geladen wird, ein Leerzustand steht oder Tage. --}}
    <native:row class="w-full items-center gap-2 bg-theme-surface px-2 py-2">
        <native:pressable ref="woche-zurueck" a11y-label="Vorherige Woche" class="px-3 py-2" @press="vorherigeWoche">
            <native:icon :ios="Ios::ChevronLeft" :android="Android::ChevronLeft" :size="24" class="text-theme-on-surface" />
        </native:pressable>
        <native:pressable ref="woche-text" a11y-label="Zur aktuellen Woche" class="flex-1 py-2" @press="aktuelleWoche">
            <native:text class="text-center text-base font-semibold text-theme-on-surface">{{ $this->wochenText() }}</native:text>
        </native:pressable>
        <native:pressable ref="woche-vor" a11y-label="Nächste Woche" class="px-3 py-2" @press="naechsteWoche">
            <native:icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="24" class="text-theme-on-surface" />
        </native:pressable>
    </native:row>

    @if ($this->nichtVerbunden)
        <native:column class="w-full flex-1 items-center justify-center gap-3 px-8">
            <native:icon :ios="Ios::Calendar" :android="Android::CalendarMonth" :size="48" class="text-theme-on-surface-variant" />
            <native:text class="text-center text-base text-theme-on-surface">Mealie nicht verbunden</native:text>
            <native:button
                ref="wochenplan-einstellungen"
                size="sm"
                variant="secondary"
                label="Zu den Einstellungen"
                @press="oeffneEinstellungen"
            />
        </native:column>
    @elseif ($this->zeigtSpinner())
        <native:column class="w-full flex-1 items-center justify-center">
            <native:activity-indicator ref="wochenplan-spinner" size="lg" a11y-label="Wochenplan wird geladen" />
        </native:column>
    @else
        {{-- Die Tagesüberschriften hängen als lose `native:text` in der Liste
             statt als `list-section`: der heutige Tag muss in der Primärfarbe
             stehen, und ein Abschnitts-Header kennt keine eigene Farbe. --}}
        <native:list separator on-refresh="neuLaden" class="w-full flex-1 bg-theme-background">
            @foreach ($this->tage as $tag)
                @php($farbe = $tag->istHeute ? 'text-theme-primary' : 'text-theme-on-surface-variant')
                <native:text class="px-4 pt-5 pb-1 text-sm font-semibold {{ $farbe }}">{{ $tag->ueberschrift() }}</native:text>

                @forelse ($tag->eintraege as $eintrag)
                    @if ($eintrag->hatRezept())
                        {{-- Der Handler-Aufruf steht in einer Variablen, weil ein Argument
                             in Anführungszeichen direkt im Attribut den Callback-Parser
                             von `native:validate` aus dem Tritt bringt. --}}
                        @php($oeffnen = "rezeptOeffnen('{$eintrag->rezeptSlug}')")
                        @php($bild = $eintrag->bildUrl())
                        @if ($bild !== null)
                            <native:list-item
                                native:key="{{ $eintrag->id }}"
                                ref="eintrag-{{ $eintrag->id }}"
                                overline="{{ $eintrag->overline() }}"
                                headline="{{ $eintrag->headline() }}"
                                leadingImage="{{ $bild }}"
                                @press="{{ $oeffnen }}"
                            />
                        @else
                            <native:list-item
                                native:key="{{ $eintrag->id }}"
                                ref="eintrag-{{ $eintrag->id }}"
                                overline="{{ $eintrag->overline() }}"
                                headline="{{ $eintrag->headline() }}"
                                :leadingIconIos="Ios::ForkKnife"
                                :leadingIconAndroid="Android::Restaurant"
                                @press="{{ $oeffnen }}"
                            />
                        @endif
                    @else
                        {{-- Ohne Rezept gibt es nichts zu öffnen: kein `@press`,
                             kein Bild — nur der freie Text aus Mealie. --}}
                        <native:list-item
                            native:key="{{ $eintrag->id }}"
                            ref="eintrag-{{ $eintrag->id }}"
                            overline="{{ $eintrag->overline() }}"
                            headline="{{ $eintrag->headline() }}"
                            supporting="{{ $eintrag->supporting() }}"
                        />
                    @endif
                @empty
                    <native:list-item
                        ref="leer-{{ $tag->datum->format('Y-m-d') }}"
                        headline="Nichts geplant"
                        :headlineColor="theme('on-surface-variant', '#475569')"
                    />
                @endforelse
            @endforeach
        </native:list>
    @endif
</native:column>
