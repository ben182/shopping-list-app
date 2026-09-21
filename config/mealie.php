<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mealie-Instanz
    |--------------------------------------------------------------------------
    |
    | URL und Listen-ID sind fest hinterlegt — sie gehören zur App, nicht zum
    | Nutzer. Ein leerer `.env`-Platzhalter liefert `''` und nicht `null`, der
    | zweite `env()`-Parameter greift dann nicht; deshalb `?:` statt Default.
    |
    | Das API-Token steht bewusst *nicht* hier: es liegt ausschließlich im
    | Secure Storage des Geräts (siehe App\Mealie\Token).
    |
    */

    'url' => env('MEALIE_URL') ?: 'https://mealie.example.test',

    'shopping_list_id' => env('MEALIE_SHOPPING_LIST_ID') ?: '00000000-0000-4000-8000-000000000000',

    /*
    |--------------------------------------------------------------------------
    | Vorratsliste
    |--------------------------------------------------------------------------
    |
    | Eine zweite Mealie-Einkaufsliste, die nicht eingekauft, sondern gepflegt
    | wird: die Artikel, die man immer im Haus haben will. Sie ist der Katalog
    | des Vorrat-Screens — ein Tap dort kopiert den Artikel in die
    | Einkaufsliste oben, der Vorratseintrag bleibt stehen.
    |
    | Gepflegt wird sie in Mealie, von allen im Haushalt.
    |
    */

    'vorrat_liste_id' => env('MEALIE_VORRAT_LIST_ID') ?: '00000000-0000-4000-8000-000000000001',

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Sekunden, die ein Mealie-Aufruf höchstens dauern darf. Im Supermarkt ist
    | eine schnelle Absage besser als ein hängender Screen.
    |
    */

    'timeout' => 10,

    /*
    |--------------------------------------------------------------------------
    | Label-Aliase
    |--------------------------------------------------------------------------
    |
    | Mealie-Labels, die eine Katalog-Gruppe meinen, aber anders heißen
    | (Anhang B der PRD). Labels, die exakt wie eine Katalog-Gruppe heißen,
    | brauchen keinen Eintrag; alles, was hier fehlt, wird auf dem
    | Einkaufen-Screen zu einer eigenen Gruppe mit dem Label als Überschrift.
    |
    | Gepflegt wird die Tabelle im Code — eine UI dafür gibt es bewusst nicht.
    |
    */

    'label_aliase' => [
        'Gemüse' => 'Obst & Gemüse',
        'Obst' => 'Obst & Gemüse',
        'Bio-Lebensmittel' => 'Obst & Gemüse',
        'Backwaren' => 'Brot & Backwaren',
        'Konditorwaren' => 'Brot & Backwaren',
        'Milchprodukte' => 'Kühlregal',
        'Fleischprodukte' => 'Kühlregal',
        'Fleisch' => 'Kühlregal',
        'Meeresfrüchte' => 'Kühlregal',
        'Tiefkühlware' => 'Tiefkühl',
        'Getreide' => 'Lebensmittel',
        'Konserven' => 'Lebensmittel',
        'Gewürze' => 'Lebensmittel',
        'Würzmittel' => 'Lebensmittel',
        'Snacks' => 'Lebensmittel',
        'Süßwaren' => 'Lebensmittel',
        'Alkohol' => 'Getränke',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gruppe für Artikel ohne Label
    |--------------------------------------------------------------------------
    */

    'gruppe_ohne_label' => 'Sonstiges',

];
