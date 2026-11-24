<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            $table->json('dias');
            $table->time('abre');
            $table->time('cierra');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('turnos', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('nombre', 60);
            $table->json('slots');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
        Schema::dropIfExists('horarios');
    }
};
