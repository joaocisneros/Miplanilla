<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de códigos de OT (unidades/órdenes del taller). Se registran una
 * vez con su producto y en la OT del contratista solo se seleccionan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos_ot', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('producto')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Sembrar con los codigos ya usados (con el primer producto visto).
        $usados = DB::table('ordenes_trabajo')
            ->select('codigo', DB::raw('MIN(producto) as producto'))
            ->groupBy('codigo')->get();
        foreach ($usados as $u) {
            DB::table('codigos_ot')->insertOrIgnore([
                'codigo' => $u->codigo,
                'producto' => $u->producto,
                'activo' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_ot');
    }
};
