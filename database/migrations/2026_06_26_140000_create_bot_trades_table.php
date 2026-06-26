<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reportes que el worker de Python envia al panel cada vez que intenta abrir
     * una operacion: si entro (opened), si la rechazo por exceder los limites del
     * broker (rejected_limits) o si fallo por otra razon (failed). Asi el panel
     * puede avisar al usuario que la operacion no entro.
     */
    public function up(): void
    {
        Schema::create('bot_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained('bots')->cascadeOnDelete();
            $table->foreignId('broker_account_id')->nullable()->constrained('broker_accounts')->nullOnDelete();

            $table->string('symbol');
            $table->enum('direction', ['buy', 'sell'])->nullable();
            $table->enum('status', ['opened', 'rejected_limits', 'failed']);
            $table->string('position_id')->nullable();

            // Niveles que el worker intento enviar (para mostrar por que se rechazo).
            $table->decimal('requested_sl', 12, 5)->nullable();
            $table->decimal('requested_tp', 12, 5)->nullable();

            $table->text('error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_trades');
    }
};
