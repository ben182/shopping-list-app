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
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Sekunden, die ein Mealie-Aufruf höchstens dauern darf. Im Supermarkt ist
    | eine schnelle Absage besser als ein hängender Screen.
    |
    */

    'timeout' => 10,

];
