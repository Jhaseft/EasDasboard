<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda la imagen base64 del QR para poder renderizarla en la página de pago
     * tras la redirección (Baneco solo la devuelve al generar, no al consultar).
     */
    public function up(): void
    {
        Schema::table('qr_deposits', function (Blueprint $table) {
            $table->longText('qr_image')->nullable()->after('qr_id');
        });
    }

    public function down(): void
    {
        Schema::table('qr_deposits', function (Blueprint $table) {
            $table->dropColumn('qr_image');
        });
    }
};
