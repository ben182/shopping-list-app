<?php

arch()->preset()->php();

arch('screens sind NativeComponents mit einer render-Methode')
    ->expect('App\NativeComponents')
    ->toExtend('Native\Mobile\Edge\NativeComponent')
    ->toHaveMethod('render');

arch('keine Livewire-, Inertia- oder WebView-Screens')
    ->expect(['Livewire', 'Inertia'])
    ->not->toBeUsed();
