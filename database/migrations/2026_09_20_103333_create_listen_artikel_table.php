<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Welche Katalog-Artikel gerade auf der Liste stehen — mehr steht hier
     * nicht: Der Artikel selbst (Name, Gruppe, Position) kommt aus dem
     * Katalog in `config/katalog.php`. Die Tabelle hält deshalb nur die ID
     * und überlebt jede Katalog-Änderung; IDs ohne Katalog-Eintrag werden
     * beim Lesen ignoriert.
     */
    public function up(): void
    {
        Schema::create('listen_artikel', function (Blueprint $table) {
            $table->string('artikel_id')->primary();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listen_artikel');
    }
};
