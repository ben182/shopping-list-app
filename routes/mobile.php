<?php

use App\Layouts\StackLayout;
use App\Layouts\TabsLayout;
use App\NativeComponents\Einkaufen;
use App\NativeComponents\Einstellungen;
use App\NativeComponents\Vorrat;
use App\NativeComponents\Wochenplan;
use Illuminate\Support\Facades\Route;

/*
 * Root-Screens — sie teilen sich die Tab-Leiste.
 */
Route::nativeGroup(TabsLayout::class, function () {
    Route::native('/', Einkaufen::class);
    Route::native('/vorrat', Vorrat::class);
    Route::native('/wochenplan', Wochenplan::class);
});

/*
 * Gepushte Screens — Top-Bar mit Zurück-Button, keine Tab-Leiste.
 */
Route::nativeGroup(StackLayout::class, function () {
    Route::native('/einstellungen', Einstellungen::class);
});
