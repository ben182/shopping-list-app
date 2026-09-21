<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Warengruppen
|--------------------------------------------------------------------------
|
| Die Gliederung aller Listen der App und die Reihenfolge, in der ihre
| Überschriften stehen. Sie ist bewusst nicht alphabetisch, sondern folgt dem
| Weg durch den Laden.
|
| Die Artikel stehen nicht mehr hier: sie kommen aus Mealie — die
| Einkaufsliste aus der Liste, die der Wochenplan füllt, der Vorrat aus der
| Liste „Vorrat“. Ein Mealie-Label landet über `mealie.label_aliase` unter
| einer dieser Gruppen; was sich nirgends zuordnen lässt, bekommt eine eigene
| Überschrift hinter den hiesigen.
|
| `laeden` sagt, wo es die Sachen dieser Gruppe gibt. Jeder Artikel erbt das,
| solange an ihm selbst nichts anderes steht — die Ausnahme trägt er als
| `extras.laeden` aus Mealie mit (siehe App\Katalog\Ladenzuordnung). Erlaubte
| Schlüssel: `lidl`, `rewe`, `getraenkemarkt` (siehe App\Katalog\Laden). Eine
| Gruppe ganz ohne Laden steht in jedem Filter: lieber eine Zeile zu viel als
| eine vergessene.
|
*/

return [

    'gruppen' => [

        'obst-gemuese' => [
            'name' => 'Obst & Gemüse',
            'laeden' => ['lidl'],
        ],

        'brot' => [
            'name' => 'Brot & Backwaren',
            'laeden' => ['lidl'],
        ],

        'kuehlregal' => [
            'name' => 'Kühlregal',
            'laeden' => ['lidl'],
        ],

        'tiefkuehl' => [
            'name' => 'Tiefkühl',
            'laeden' => ['lidl'],
        ],

        'lebensmittel' => [
            'name' => 'Lebensmittel',
            'laeden' => ['lidl'],
        ],

        'getraenke' => [
            'name' => 'Getränke',
            'laeden' => ['getraenkemarkt'],
        ],

        'haushalt' => [
            'name' => 'Haushalt',
            'laeden' => ['lidl'],
        ],

        'drogerie' => [
            'name' => 'Drogerie',
            'laeden' => ['lidl'],
        ],

    ],

];
