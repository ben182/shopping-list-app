<native:column class="w-full h-full bg-theme-background gap-3 px-4 pt-4">
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
