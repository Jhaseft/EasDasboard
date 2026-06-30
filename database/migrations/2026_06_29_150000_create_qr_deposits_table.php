<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recargas de billetera pagadas vía QR de Banco Económico. El usuario recarga
     * en USD (amount_usd, lo que se acredita) y el QR cobra el equivalente en BOB
     * (amount_bob). Esta tabla rastrea el ciclo de vida del QR antes de acreditar.
     */
    public function up(): void
    {
        Schema::create('qr_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();

            // Nuestra referencia enviada al banco (transactionId) y el id del QR que él devuelve.
            $table->string('transaction_id')->unique();
            $table->string('qr_id')->nullable()->unique();

            $table->decimal('amount_usd', 12, 2);   // lo que se acredita a la billetera
            $table->decimal('amount_bob', 12, 2);   // lo que cobra el QR
            $table->decimal('exchange_rate', 8, 4); // tipo de cambio usado (congelado)
            $table->string('currency', 3)->default('BOB');

            $table->enum('status', ['pending', 'paid', 'canceled', 'failed', 'expired'])
                ->default('pending');

            $table->string('bank_transaction_id')->nullable();
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();

            // Enlace al movimiento de billetera generado al acreditar.
            $table->foreignId('wallet_transaction_id')->nullable()
                ->constrained('wallet_transactions')->nullOnDelete();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_deposits');
    }
};
