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
        Schema::create('slave_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_account_id')->constrained('broker_accounts')->cascadeOnDelete();

            $table->string('name');
            $table->enum('platform', ['mt4', 'mt5'])->default('mt5');
            $table->string('login');
            $table->string('server');

            $table->string('metaapi_account_id')->nullable()->index();
            $table->string('region')->nullable();
            $table->string('provision_state')->default('pending');
            $table->string('connection_status')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_enabled')->default(true);

            // Factor de lote proporcional al balance (balance_esclava / balance_maestra)
            $table->decimal('lot_multiplier', 8, 4)->default(1.0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slave_accounts');
    }
};
