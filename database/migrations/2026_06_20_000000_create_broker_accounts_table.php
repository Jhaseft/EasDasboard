<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuenta de broker que el usuario conecta. Las credenciales reales viven
     * cifradas en MetaApi; aqui solo guardamos la referencia (metaapi_account_id)
     * y datos no sensibles para mostrar y operar.
     */
    public function up(): void
    {
        Schema::create('broker_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');                         // alias que pone el usuario
            $table->enum('platform', ['mt4', 'mt5'])->default('mt5');
            $table->string('login');                        // numero de cuenta del broker
            $table->string('server');                       // nombre del servidor del broker

            // Referencia a la cuenta provisionada en MetaApi.
            $table->string('metaapi_account_id')->nullable()->index();
            $table->string('region')->nullable();

            // Estado del ciclo de vida en MetaApi.
            // pending = aun no provisionada | deploying | deployed | undeployed | error
            $table->string('provision_state')->default('pending');
            $table->string('connection_status')->nullable(); // CONNECTED / DISCONNECTED (de MetaApi)
            $table->text('last_error')->nullable();

            // El usuario debe autorizar que operemos su cuenta.
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broker_accounts');
    }
};
