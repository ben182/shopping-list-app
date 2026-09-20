@use('App\Icons\Android')
@use('App\Icons\Ios')

@if ($this->gruppen === [])
    <native:column class="w-full h-full items-center justify-center gap-3 bg-theme-background px-8">
        <native:icon :ios="Ios::Cart" :android="Android::ShoppingCart" :size="48" class="text-theme-on-surface-variant" />
        <native:text class="text-center text-base text-theme-on-surface">Liste ist leer.</native:text>
        <native:text class="text-center text-sm text-theme-on-surface-variant">
            Tippe auf den Vorrat-Tab, um Artikel hinzuzufügen.
        </native:text>
    </native:column>
@else
    <native:list separator class="w-full h-full bg-theme-background">
        @foreach ($this->gruppen as $gruppe)
            {{-- Die Überschrift hängt am Abschnitt: fällt der Abschnitt weg, fällt sie mit. --}}
            <native:list-section header="{{ $gruppe->name }}">
                @foreach ($gruppe->artikel as $artikel)
                    {{-- Der Handler-Aufruf steht in einer Variablen, weil ein Argument in
                         Anführungszeichen direkt im Attribut den Callback-Parser von
                         `native:validate` aus dem Tritt bringt. --}}
                    @php($abhaken = "abhaken('{$artikel->id}')")
                    <native:list-item
                        native:key="{{ $artikel->id }}"
                        ref="einkaufen-{{ $artikel->id }}"
                        headline="{{ $artikel->name }}"
                        :leadingCheckbox="false"
                        @press="{{ $abhaken }}"
                    />
                @endforeach
            </native:list-section>
        @endforeach
    </native:list>
@endif
