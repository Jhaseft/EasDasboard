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
        Schema::create('copied_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_account_id')->constrained('broker_accounts')->cascadeOnDelete();
            $table->foreignId('slave_account_id')->constrained('slave_accounts')->cascadeOnDelete();

            $table->string('master_position_id');
            $table->string('slave_position_id')->nullable();

            $table->string('symbol');
            $table->enum('direction', ['buy', 'sell']);
            $table->decimal('master_lot', 8, 2);
            $table->decimal('slave_lot', 8, 2);
            $table->enum('status', ['pending', 'open', 'closed', 'failed'])->default('pending');
            $table->text('error')->nullable();

            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('copied_trades');
    }
};
