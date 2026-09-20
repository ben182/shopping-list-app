@use('App\Icons\Android')
@use('App\Icons\Ios')

@if ($this->gruppen === [])
    <native:column class="w-full h-full items-center justify-center gap-3 bg-theme-background px-8">
        <native:icon :ios="Ios::Checkmark" :android="Android::Check" :size="48" class="text-theme-on-surface-variant" />
        <native:text class="text-center text-base text-theme-on-surface">Alles auf der Liste.</native:text>
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
