@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:column class="w-full h-full bg-theme-background gap-3 px-4 pt-4">
    <native:text class="text-sm text-theme-on-surface-variant">Erscheinungsbild</native:text>

    {{-- Kein Speichern-Knopf: der Tipp auf eine Option ist die Eingabe. Das
         `a11y-label` benennt die Gruppe; ob eine Option gewählt ist, meldet
         der Segmented Control dem Screenreader selbst. --}}
    <native:button-group
        ref="erscheinungsbild"
        class="w-full"
        a11y-label="Erscheinungsbild"
        :options="$this->erscheinungsbildOptionen()"
        :value="$this->erscheinungsbild"
        @change="erscheinungsbildGewaehlt"
    />

    {{-- Sechs Kreise ohne Beschriftung: die Farbe ist die Aussage. Was sie
         heißt und ob sie die gewählte ist, steht im `a11y-label`. Die
         Farbwerte stehen als feste Hex-Paare in der Klasse — sie können
         nicht aus dem Theme kommen, denn hier wird ja gerade ausgewählt,
         was das Theme künftig hergibt. --}}
    <native:row class="w-full items-center gap-3">
        @foreach ($this->akzentfarben() as $farbe)
            {{-- Der Handler-Aufruf steht in einer Variablen, weil ein Argument in
                 Anführungszeichen direkt im Attribut den Callback-Parser von
                 `native:validate` aus dem Tritt bringt. --}}
            @php($waehlen = "akzentfarbeGewaehlt('{$farbe->value}')")
            @php($gewaehlt = $farbe->value === $this->akzentfarbe)
            <native:pressable
                ref="akzentfarbe-{{ $farbe->value }}"
                a11y-label="{{ $farbe->a11yLabel($gewaehlt) }}"
                class="w-10 h-10 rounded-full items-center justify-center bg-[{{ $farbe->hell() }}] dark:bg-[{{ $farbe->dunkel() }}]"
                @press="{{ $waehlen }}"
            >
                @if ($gewaehlt)
                    <native:icon
                        :ios="Ios::Checkmark"
                        :android="Android::Check"
                        :size="20"
                        color="{{ $farbe->aufHell() }}"
                        dark-color="{{ $farbe->aufDunkel() }}"
                    />
                @endif
            </native:pressable>
        @endforeach
    </native:row>

    <native:text class="text-sm text-theme-on-surface-variant">Mealie-Server</native:text>
    <native:text class="text-base text-theme-on-surface">{{ $this->mealieUrl() }}</native:text>

    {{-- `secure` maskiert die Eingabe; das Token verlässt den Keystore nie wieder
         sichtbar, und auch beim Tippen steht es nicht offen auf dem Bildschirm. --}}
    <native:outlined-text-input
        ref="mealie-token"
        class="w-full"
        label="API-Token"
        secure
        value="{{ $this->eingabe }}"
        sync-mode="debounce"
        debounce-ms="200"
        @change="tokenGetippt"
    />

    <native:button ref="token-speichern" label="Speichern" class="w-full" @press="speichern" />

    <native:text class="text-sm text-theme-on-surface-variant">{{ $this->status }}</native:text>

    <native:button
        ref="verbindung-testen"
        class="w-full"
        label="Verbindung testen"
        :disabled="! $this->tokenHinterlegt"
        :loading="$this->testLaeuft"
        @press="verbindungTesten"
    />

    @if ($this->verbindung !== null)
        <native:text class="text-sm text-theme-on-surface">{{ $this->verbindung }}</native:text>
    @endif

    <native:button
        ref="token-loeschen"
        class="w-full"
        variant="destructive"
        label="Token löschen"
        @press="loeschenBestaetigen"
    />
</native:column>
