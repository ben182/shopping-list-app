<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ein Katalog-Artikel, der gerade auf der Liste steht. Der Datensatz besteht
 * nur aus der Katalog-ID — alles andere (Name, Gruppe, Position) steht im
 * Katalog und darf sich mit einem App-Update ändern, ohne dass hier etwas
 * angefasst werden müsste.
 */
class ListenArtikel extends Model
{
    protected $table = 'listen_artikel';

    protected $primaryKey = 'artikel_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['artikel_id'];
}
