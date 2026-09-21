{{-- Die Filter-Chips über Einkaufsliste und Vorrat. Beide Screens zeigen
     dieselben Chips und teilen sich dieselbe Wahl; der einbindende Screen
     entscheidet nur, ob es gerade etwas zu filtern gibt.

     Eine schlichte Row, keine waagerecht scrollende Scroll-View: die rendert
     auf Android als LazyRow und verschluckt dabei das `gap` ihres Inhalts.
     Die vier Chips passen in eine Zeile; wird es eng, bricht `flex-wrap` sie
     um, statt den letzten abzuschneiden.

     `$laeden` und `$gewaehlterLaden` kommen vom einbindenden Screen: ein
     Include läuft nicht im Kontext der Komponente, `$this` gibt es hier
     nicht. Den Handler `ladenWaehlen()` muss jeder Screen mitbringen, der
     das hier einbindet. --}}

@php($alleWaehlen = "ladenWaehlen('')")

<native:row ref="laden-filter" class="w-full flex-wrap items-center gap-3 px-4 py-2">
    {{-- Der Handler-Aufruf steht jeweils in einer Variablen, weil ein
         Argument in Anführungszeichen direkt im Attribut den Callback-Parser
         von `native:validate` aus dem Tritt bringt. --}}
    <native:chip
        ref="laden-alle"
        label="Alle"
        :selected="$gewaehlterLaden === null"
        @change="{{ $alleWaehlen }}"
    />
    @foreach ($laeden as $laden)
        @php($ladenWaehlen = "ladenWaehlen('{$laden->value}')")
        <native:chip
            native:key="laden-{{ $laden->value }}"
            ref="laden-{{ $laden->value }}"
            label="{{ $laden->bezeichnung() }}"
            :selected="$gewaehlterLaden === $laden"
            @change="{{ $ladenWaehlen }}"
        />
    @endforeach
</native:row>
