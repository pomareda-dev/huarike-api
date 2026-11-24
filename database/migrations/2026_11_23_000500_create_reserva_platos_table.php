<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva_platos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            // Si el plato se borra (soft-delete no dispara esto), el snapshot
            // de nombre/precio mantiene legible el histórico de la reserva.
            $table->foreignId('plato_id')->nullable()->constrained('platos')->nullOnDelete();
            $table->string('nombre', 150);
            $table->decimal('precio', 8, 2);
            $table->unsignedSmallInteger('cantidad');
            $table->timestamps();

            $table->unique(['reserva_id', 'plato_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_platos');
    }
};
