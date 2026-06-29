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
        Schema::create('marketplace_subscriptions', function (Blueprint $table) {
            $table->id();
            // Quién sigue (suscriptor) y a qué cuenta maestra pública.
            $table->foreignId('subscriber_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('master_account_id')->constrained('broker_accounts')->cascadeOnDelete();
            // Cuenta esclava del suscriptor que copia a esa maestra (si ya la creó).
            $table->foreignId('slave_account_id')->nullable()->constrained('slave_accounts')->nullOnDelete();

            $table->enum('pricing_model', ['subscription', 'profit_share']);
            $table->decimal('amount', 10, 2)->default(0);          // cuota mensual (subscription)
            $table->decimal('profit_share_pct', 5, 2)->default(0); // % de ganancias (profit_share)
            $table->decimal('take_rate_pct', 5, 2)->default(0);    // % que se queda el SaaS
            $table->decimal('high_water_mark', 12, 2)->default(0); // para profit_share

            $table->enum('status', ['active', 'past_due', 'cancelled'])->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('next_charge_at')->nullable();
            $table->timestamp('last_charged_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // Un suscriptor no puede tener dos suscripciones activas a la misma maestra.
            $table->unique(['subscriber_id', 'master_account_id'], 'mp_sub_subscriber_master_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_subscriptions');
    }
};
