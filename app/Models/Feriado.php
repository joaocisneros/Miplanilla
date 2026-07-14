<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    protected $table = 'feriados';

    protected $fillable = ['fecha', 'nombre'];

    protected $casts = ['fecha' => 'date'];

    /**
     * Se asegura de que el año tenga sus feriados: si no hay ninguno, los
     * genera (fijos del Perú + Semana Santa calculada). Devuelve cuántos creó.
     * Se invoca sola al abrir Feriados/Asistencia: nadie tiene que acordarse.
     */
    public static function asegurarAnio(int $anio): int
    {
        if (self::whereYear('fecha', $anio)->exists()) {
            return 0;
        }

        return self::generarAnio($anio);
    }

    /** Genera (sin duplicar) los feriados nacionales del año dado. */
    public static function generarAnio(int $anio): int
    {
        $fijos = [
            '01-01' => 'Año Nuevo',
            '05-01' => 'Día del Trabajo',
            '06-07' => 'Batalla de Arica y Día de la Bandera',
            '06-29' => 'San Pedro y San Pablo',
            '07-23' => 'Día de la Fuerza Aérea del Perú',
            '07-28' => 'Fiestas Patrias',
            '07-29' => 'Fiestas Patrias',
            '08-06' => 'Batalla de Junín',
            '08-30' => 'Santa Rosa de Lima',
            '10-08' => 'Combate de Angamos',
            '11-01' => 'Día de Todos los Santos',
            '12-08' => 'Inmaculada Concepción',
            '12-09' => 'Batalla de Ayacucho',
            '12-25' => 'Navidad',
        ];

        // Domingo de Pascua (algoritmo gregoriano anonimo): fija Semana Santa.
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
        $pascua = \Carbon\Carbon::create($anio, $mes, $dia);

        $agregar = [];
        foreach ($fijos as $md => $n) {
            $agregar[$anio.'-'.$md] = $n;
        }
        $agregar[$pascua->copy()->subDays(3)->toDateString()] = 'Jueves Santo';
        $agregar[$pascua->copy()->subDays(2)->toDateString()] = 'Viernes Santo';

        $nuevos = 0;
        foreach ($agregar as $fecha => $nombre) {
            if (self::firstOrCreate(['fecha' => $fecha], ['nombre' => $nombre])->wasRecentlyCreated) {
                $nuevos++;
            }
        }

        return $nuevos;
    }
}
