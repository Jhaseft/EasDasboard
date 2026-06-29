<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cada señal recibida (por webhook o lectura) queda registrada para auditoría
     * e idempotencia: si llega dos veces el mismo external_id no se ejecuta doble.
     */
    public function up(): void
    {
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broker_account_id')->constrained('broker_accounts')->cascadeOnDelete();

            $table->string('source')->default('webhook');      // 'webhook' | 'read'
            $table->string('external_id')->nullable();          // id que manda el emisor (dedupe)

            $table->enum('action', ['buy', 'sell', 'close']);
            $table->string('symbol');
            $table->decimal('volume', 8, 2)->nullable();
            $table->decimal('sl', 12, 5)->nullable();
            $table->decimal('tp', 12, 5)->nullable();

            $table->enum('status', ['received', 'executed', 'failed', 'duplicate'])->default('received');
            $table->text('error')->nullable();
            $table->json('payload')->nullable();                // payload crudo recibido
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            // Idempotencia: un mismo external_id solo se procesa una vez por cuenta.
            $table->unique(['broker_account_id', 'external_id'], 'signals_account_external_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signals');
    }
};
