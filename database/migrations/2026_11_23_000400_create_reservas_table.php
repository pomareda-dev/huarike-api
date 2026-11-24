<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 12)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre', 120);
            $table->string('correo', 190);
            $table->string('telefono', 20);
            $table->text('notas')->nullable();
            $table->date('fecha');
            $table->time('hora');
            $table->unsignedTinyInteger('personas');
            $table->foreignId('mesa_id')->nullable()->constrained('mesas')->nullOnDelete();
            $table->enum('estado', [
                'confirmada',
                'por-confirmar',
                'lista-espera',
                'no-show',
                'finalizada',
                'cancelada',
            ])->default('por-confirmar');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('creada_por', 120)->nullable();
            $table->timestamps();

            // Consultas del panel: por fecha+turno, por correo del cliente,
            // y la ventana de choque de mesa (fecha, mesa, hora).
            $table->index(['fecha', 'hora']);
            $table->index(['mesa_id', 'fecha', 'hora']);
            $table->index(['correo']);
            $table->index(['estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
