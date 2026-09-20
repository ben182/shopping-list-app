<?php

namespace App\NativeComponents;

use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

class Einstellungen extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Einstellungen';
    }

    public function render(): Element
    {
        return $this->view('einstellungen');
    }
}
