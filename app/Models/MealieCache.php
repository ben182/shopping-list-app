<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ein gecachter Mealie-Datenbestand. Der Schlüssel sagt, welcher — die
 * Einkaufsliste hat genau einen, der Wochenplan bekommt später einen je
 * Woche.
 */
class MealieCache extends Model
{
    protected $table = 'mealie_cache';

    protected $primaryKey = 'schluessel';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['schluessel', 'daten', 'geladen_am'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daten' => 'array',
            'geladen_am' => 'immutable_datetime',
        ];
    }
}
