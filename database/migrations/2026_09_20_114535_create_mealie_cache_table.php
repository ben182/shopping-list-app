<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die zuletzt erfolgreich von Mealie geladenen Daten — ein Datensatz je
     * Datenbestand (`einkaufsliste`, später eine Zeile je Wochenplan-Woche).
     * Gespeichert wird Mealies Antwort in der Form, in der der Screen sie
     * braucht, samt Zeitpunkt des Ladens: den nennt das Fehlerbanner als
     * „Stand“.
     */
    public function up(): void
    {
        Schema::create('mealie_cache', function (Blueprint $table) {
            $table->string('schluessel')->primary();
            $table->json('daten');
            $table->timestamp('geladen_am');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mealie_cache');
    }
};
