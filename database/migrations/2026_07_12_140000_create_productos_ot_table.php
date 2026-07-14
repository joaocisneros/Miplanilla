<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de productos para las OT de contratistas (SR TOLVA DE 22M3, etc.).
 * En el formulario de OT el producto se elige de esta lista (caja de opciones).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_ot', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Sembrar con los productos ya usados en las OT existentes.
        $usados = DB::table('ordenes_trabajo')
            ->whereNotNull('producto')->where('producto', '!=', '')
            ->distinct()->pluck('producto');
        foreach ($usados as $p) {
            DB::table('productos_ot')->insertOrIgnore([
                'nombre' => mb_strtoupper(trim($p)),
                'activo' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_ot');
    }
};
