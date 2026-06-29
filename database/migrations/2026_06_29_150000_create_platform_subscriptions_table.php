<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suscripciones de PLATAFORMA (cobro del SaaS al usuario), una por concepto:
     *   - item 'account'        -> $7/mes por cada cuenta conectada (billable = la cuenta)
     *   - item 'webhook_module' -> $15/mes add-on por usuario (billable null)
     * Cada fila tiene su propio ciclo (next_charge_at): se cobra al conectar y
     * se renueva mes a mes desde esa fecha.
     */
    public function up(): void
    {
        Schema::create('platform_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('billable'); // BrokerAccount / SlaveAccount; null en webhook_module
            $table->string('item'); // 'account' | 'webhook_module'
            $table->decimal('amount', 8, 2);
            $table->string('status')->default('active'); // active | past_due | cancelled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('next_charge_at')->nullable();
            $table->timestamp('last_charged_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_subscriptions');
    }
};
