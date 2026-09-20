<?php

namespace App\NativeComponents;

use Native\Mobile\Edge\Element;
use Native\Mobile\Edge\NativeComponent;

class Vorrat extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Vorrat';
    }

    public function render(): Element
    {
        return $this->view('vorrat');
    }
}
