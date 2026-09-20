<?php

namespace App\NativeComponents;

use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

class Wochenplan extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Wochenplan';
    }

    public function render(): Element
    {
        return $this->view('wochenplan');
    }
}
