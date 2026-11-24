<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 8, 2);
            $table->json('etiquetas')->nullable();
            $table->string('foto')->nullable();
            $table->boolean('disponible')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['categoria_id', 'nombre']);
            $table->index(['disponible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platos');
    }
};
