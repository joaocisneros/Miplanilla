<?php

namespace App\Models;

use App\Models\Concerns\RestringidoPorEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Periodo extends Model
{
    use RestringidoPorEmpresa;

    protected $table = 'periodos';

    protected $fillable = [
        'empresa_id', 'anio', 'mes', 'quincena',
        'fecha_inicio', 'fecha_fin', 'fecha_pago', 'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_pago' => 'date',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function getDescripcionAttribute(): string
    {
        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        $ordinal = [1 => '1ra', 2 => '2da'];
        $q = $this->quincena ? ($ordinal[$this->quincena] ?? "{$this->quincena}a").' quincena' : 'Mensual';
        return "{$q} {$meses[$this->mes]} {$this->anio}";
    }
}
