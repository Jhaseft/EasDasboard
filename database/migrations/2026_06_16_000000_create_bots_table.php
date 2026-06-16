<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->boolean('is_active')->default(false);

            // Pares / símbolos en los que opera el bot, p.ej. ["EURUSD", "GBPUSD"]
            $table->json('symbols')->nullable();

            // --- Cómo abre la operación (set básico de trading) ---
            $table->enum('direction', ['buy', 'sell', 'both'])->default('both');
            $table->decimal('lot_size', 8, 2)->default(0.01);
            $table->unsignedInteger('stop_loss_pips')->nullable();
            $table->unsignedInteger('take_profit_pips')->nullable();
            $table->unsignedInteger('max_open_trades')->default(1);

            // --- Gestión de riesgo ---
            $table->decimal('risk_percent', 5, 2)->nullable();
            $table->unsignedInteger('trailing_stop_pips')->nullable();
            $table->time('trading_start_time')->nullable();
            $table->time('trading_end_time')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
