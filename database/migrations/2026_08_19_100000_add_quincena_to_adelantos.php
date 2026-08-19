<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite elegir en qué quincena se descuenta cada adelanto o cuota.
     * NULL = mensual: se descuenta en la 2da quincena (o en el cierre del mes),
     * que es como venía funcionando, así que los registros ya existentes
     * conservan su comportamiento sin tocarlos.
     */
    public function up(): void
    {
        Schema::table('adelantos', function (Blueprint $table) {
            $table->unsignedTinyInteger('quincena')->nullable()->after('mes');
        });
    }

    public function down(): void
    {
        Schema::table('adelantos', function (Blueprint $table) {
            $table->dropColumn('quincena');
        });
    }
};
