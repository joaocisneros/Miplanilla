<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de trabajos/descripciones de OT (igual que productos_ot):
     * en la orden se elige de una lista en vez de escribir libre.
     * Se siembra con las descripciones ya usadas en las OTs existentes.
     */
    public function up(): void
    {
        Schema::create('trabajos_ot', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $usados = DB::table('ordenes_trabajo')
            ->whereNotNull('descripcion')
            ->where('descripcion', '!=', '')
            ->distinct()
            ->pluck('descripcion');
        foreach ($usados as $d) {
            $nombre = mb_strtoupper(trim($d));
            if ($nombre === '') {
                continue;
            }
            DB::table('trabajos_ot')->insertOrIgnore([
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajos_ot');
    }
};
