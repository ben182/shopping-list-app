<?php

namespace App\Release;

/** Welcher Teil der Versionsnummer beim Veröffentlichen hochgezählt wird. */
enum Stufe: string
{
    case Major = 'major';
    case Minor = 'minor';
    case Patch = 'patch';
}
