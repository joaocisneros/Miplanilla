<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Derechohabiente extends Model
{
    protected $table = 'derechohabientes';

    protected $fillable = [
        'employee_id', 'tipo', 'nombres', 'numero_documento',
        'fecha_nacimiento', 'genero', 'estudia',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'estudia' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
