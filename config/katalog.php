<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Katalog
|--------------------------------------------------------------------------
|
| Die feste Artikelliste der App — 1:1 aus dem Vorgänger
| (~/Code/shopping-list/shared/items.ts) übernommen. Die Reihenfolge der
| Gruppen und der Artikel innerhalb einer Gruppe ist die Anzeigereihenfolge
| auf allen Screens; sie ist bewusst nicht alphabetisch, sondern folgt dem
| Weg durch den Laden.
|
| Die IDs bleiben unverändert, damit ein späterer Import des alten Zustands
| möglich bleibt. Der Katalog ist in der App nicht editierbar — Änderungen
| hier brauchen ein App-Update.
|
*/

return [

    'gruppen' => [

        'obst-gemuese' => [
            'name' => 'Obst & Gemüse',
            'artikel' => [
                'aepfel' => 'Äpfel',
                'bananen' => 'Bananen',
                'beeren' => 'Beeren',
                'zitronen' => 'Zitronen',
                'avocado' => 'Avocado',
                'tomaten' => 'Tomaten',
                'gurken' => 'Gurken',
                'salat' => 'Salat',
                'paprika' => 'Paprika',
                'spinat' => 'Spinat',
                'brokkoli' => 'Brokkoli',
                'champignons' => 'Champignons',
                'zwiebeln' => 'Zwiebeln',
                'knoblauch' => 'Knoblauch',
                'ingwer' => 'Ingwer',
                'karotten' => 'Karotten',
                'kartoffeln' => 'Kartoffeln',
            ],
        ],

        'brot' => [
            'name' => 'Brot & Backwaren',
            'artikel' => [
                'brot' => 'Brot',
                'broetchen' => 'Brötchen',
                'toast' => 'Toast',
                'wraps' => 'Wraps',
                'hot-dog-broetchen' => 'Hot Dog Brötchen',
            ],
        ],

        'kuehlregal' => [
            'name' => 'Kühlregal',
            'artikel' => [
                'hafermilch' => 'Hafermilch',
                'sojamilch' => 'Sojamilch',
                'mandelmilch' => 'Mandelmilch',
                'sojajoghurt' => 'Sojajoghurt',
                'pflanzliche-sahne' => 'Pflanzliche Sahne',
                'margarine' => 'Margarine',
                'veganer-kaese' => 'Veganer Käse',
                'veganer-frischkaese' => 'Veganer Frischkäse',
                'tofu' => 'Tofu',
                'raeuchertofu' => 'Räuchertofu',
                'tempeh' => 'Tempeh',
                'seitan' => 'Seitan',
                'hummus' => 'Hummus',
                'zaziki' => 'Zaziki',
                'vegane-wurst' => 'Vegane Wurst',
                'vegane-bratwurst' => 'Vegane Bratwurst',
                'vegane-leberwurst' => 'Vegane Leberwurst',
                'veganer-fleischsalat' => 'Veganer Fleischsalat',
                'veganer-aufstrich' => 'Veganer Aufstrich',
                'vivera-schnitzel' => 'Vivera Schnitzel',
                'veganes-schnitzel' => 'Veganes Schnitzel',
                'veganes-cordon-bleu' => 'Veganes Cordon Bleu',
                'veganer-streukaese' => 'Veganer Streukäse',
                'vegane-creme-fraiche' => 'Vegane Crème Fraîche',
                'vegane-mayonnaise' => 'Vegane Mayonnaise',
                'hafercreme' => 'Hafercreme',
            ],
        ],

        'tiefkuehl' => [
            'name' => 'Tiefkühl',
            'artikel' => [
                'vegane-pizza' => 'Vegane Pizza',
                'pommes' => 'Pommes',
                'kartoffelspalten' => 'Kartoffelspalten',
                'tk-gemuese' => 'TK-Gemüse',
                'tk-beeren' => 'TK-Beeren',
                'tk-spinat' => 'TK-Spinat',
                'veganes-eis' => 'Veganes Eis',
            ],
        ],

        'lebensmittel' => [
            'name' => 'Lebensmittel',
            'artikel' => [
                'nudeln' => 'Nudeln',
                'reis' => 'Reis',
                'linsen' => 'Linsen',
                'kichererbsen' => 'Kichererbsen (Dose)',
                'bohnen' => 'Bohnen (Dose)',
                'erbsen' => 'Erbsen (Dose)',
                'mais' => 'Mais (Dose)',
                'tomaten-dose' => 'Tomaten (Dose)',
                'passierte-tomaten' => 'Passierte Tomaten',
                'oliven' => 'Oliven',
                'ananas-dose' => 'Ananas (Dose)',
                'kokosmilch' => 'Kokosmilch',
                'mehl' => 'Mehl',
                'zucker' => 'Zucker',
                'salz' => 'Salz',
                'pfeffer' => 'Pfeffer',
                'olivenoel' => 'Olivenöl',
                'essig' => 'Essig',
                'sojasauce' => 'Sojasauce',
                'suess-sauer-sauce' => 'Süß-Sauer-Sauce',
                'barbecue-sauce' => 'Barbecue-Sauce',
                'hefeflocken' => 'Hefeflocken',
                'haferflocken' => 'Haferflocken',
                'muesli' => 'Müsli',
                'kaffee' => 'Kaffee',
                'tee' => 'Tee',
                'erdnussbutter' => 'Erdnussbutter',
                'marmelade' => 'Marmelade',
                'ahornsirup' => 'Ahornsirup',
                'schokolade' => 'Schokolade (vegan)',
                'nuesse' => 'Nüsse',
                'kekse' => 'Kekse',
                'chips' => 'Chips',
                'tortilla-chips' => 'Tortilla Chips',
                'erdnuss-flips' => 'Erdnussflips',
            ],
        ],

        'getraenke' => [
            'name' => 'Getränke',
            'artikel' => [
                'wasser-still' => 'Wasser (still)',
                'wasser-sprudel' => 'Wasser (Sprudel)',
                'saft' => 'Saft',
                'bier' => 'Bier',
                'wein' => 'Wein (vegan)',
                'cola' => 'Cola',
                'energy-drink' => 'Energy Drink',
            ],
        ],

        'haushalt' => [
            'name' => 'Haushalt',
            'artikel' => [
                'spuelmittel' => 'Spülmittel',
                'waschmittel' => 'Waschmittel',
                'weichspueler' => 'Weichspüler',
                'muellbeutel' => 'Müllbeutel',
                'kuechenrolle' => 'Küchenrolle',
                'toilettenpapier' => 'Toilettenpapier',
                'backpapier' => 'Backpapier',
                'frischhaltefolie' => 'Frischhaltefolie',
            ],
        ],

        'drogerie' => [
            'name' => 'Drogerie',
            'artikel' => [
                'zahnpasta' => 'Zahnpasta',
                'duschgel' => 'Duschgel',
                'shampoo' => 'Shampoo',
                'deo' => 'Deo',
                'handseife' => 'Handseife',
                'taschentuecher' => 'Taschentücher',
                'feuchttuecher-toilette' => 'Feuchttücher (Toilette)',
            ],
        ],

    ],

];
