<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('slug', 160)->unique();
            $table->boolean('destacada')->default(false);
            $table->string('vigencia', 120);
            $table->enum('categoria', ['desayunos', 'almuerzos', 'familia']);
            $table->json('condiciones')->nullable();
            $table->string('codigo', 30);
            $table->string('precio_texto', 30);
            $table->string('foto')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activa', 'categoria', 'destacada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promociones');
    }
};
