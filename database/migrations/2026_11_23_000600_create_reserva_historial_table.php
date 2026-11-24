<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('texto', 255);
            $table->string('autor', 120);
            $table->timestamp('created_at')->nullable();

            $table->index(['reserva_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_historial');
    }
};
