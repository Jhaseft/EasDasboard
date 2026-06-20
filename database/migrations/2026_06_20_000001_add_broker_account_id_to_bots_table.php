<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un bot se ejecuta sobre una cuenta de broker concreta. Si es null, el bot
     * existe pero el worker no lo opera hasta que se le asigne una cuenta.
     */
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->foreignId('broker_account_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('broker_account_id');
        });
    }
};
