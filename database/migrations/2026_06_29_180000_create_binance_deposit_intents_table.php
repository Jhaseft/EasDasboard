<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Intentos de recarga por USDT (Binance). El usuario declara que enviará X
     * USDT a una de nuestras direcciones; el backend matchea el depósito real del
     * historial de Binance contra estos intents pendientes (monto + ventana, o
     * TXID manual) y acredita la billetera en USD (1 USDT = 1 USD).
     */
    public function up(): void
    {
        Schema::create('binance_deposit_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();

            $table->string('network');                 // TRX, BSC, MATIC...
            $table->string('coin', 10)->default('USDT');
            $table->string('wallet_address');          // dirección mostrada al usuario

            $table->decimal('amount_usd', 12, 2);      // lo que se acredita
            $table->decimal('expected_usdt', 18, 8);   // USDT que debe llegar (= USD, 1:1)

            $table->enum('status', ['pending', 'confirmed', 'expired', 'rejected'])
                ->default('pending');

            // Hash de la transacción: unique para que un depósito no acredite dos veces.
            $table->string('txid')->nullable()->unique();

            $table->foreignId('wallet_transaction_id')->nullable()
                ->constrained('wallet_transactions')->nullOnDelete();

            $table->json('binance_data')->nullable();  // copia cruda del depósito (auditoría)
            $table->string('failure_reason')->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            // El cron filtra pendientes vigentes por aquí.
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binance_deposit_intents');
    }
};
