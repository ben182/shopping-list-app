<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die lokalen Vorlieben des Nutzers — ein Datensatz je Einstellung,
     * nach dem Muster der Mealie-Cache-Tabelle: der Schlüssel sagt, welche
     * Einstellung gemeint ist (`erscheinungsbild`), der Wert, wie sie steht.
     *
     * Hier liegt bewusst nichts Geheimes: das Mealie-Token gehört in den
     * Secure Storage, eine Farbvorliebe nicht.
     */
    public function up(): void
    {
        Schema::create('einstellungen', function (Blueprint $table) {
            $table->string('schluessel')->primary();
            $table->string('wert');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einstellungen');
    }
};
