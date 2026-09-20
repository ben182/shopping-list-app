<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eine lokal gemerkte Vorliebe des Nutzers. Der Schlüssel sagt, welche —
 * das Erscheinungsbild hat genau einen, weitere kommen dazu, wenn es sie
 * gibt.
 */
class Einstellung extends Model
{
    protected $table = 'einstellungen';

    protected $primaryKey = 'schluessel';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['schluessel', 'wert'];
}
