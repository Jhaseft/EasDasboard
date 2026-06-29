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
        Schema::table('broker_accounts', function (Blueprint $table) {
            // ¿La cuenta se publica en el marketplace para que otros la copien?
            $table->boolean('is_public')->default(false)->after('ingest_mode');
            $table->string('display_name')->nullable()->after('is_public');
            $table->text('description')->nullable()->after('display_name');
            // Modo incógnito: ocultar el balance en $ y mostrar solo %.
            $table->boolean('show_balance')->default(false)->after('description');
            // Cómo cobra el proveedor a sus seguidores.
            $table->enum('pricing_model', ['subscription', 'profit_share'])->default('subscription')->after('show_balance');
            $table->decimal('subscription_price', 10, 2)->default(0)->after('pricing_model');
            $table->decimal('profit_share_pct', 5, 2)->default(0)->after('subscription_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broker_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'is_public', 'display_name', 'description', 'show_balance',
                'pricing_model', 'subscription_price', 'profit_share_pct',
            ]);
        });
    }
};
