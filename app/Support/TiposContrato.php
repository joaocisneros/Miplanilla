<?php

namespace App\Support;

/**
 * Catálogo único de tipos de contrato (D.Leg. 728 y regímenes especiales).
 * Lo usan el formulario de empleado (desplegable) y la plantilla del contrato PDF.
 *
 * Cada tipo define:
 *  - label: texto para el desplegable.
 *  - titulo: cómo aparece en el encabezado del contrato.
 *  - plazo_fijo: si requiere fecha de cese (vencimiento).
 *  - causa: la justificación legal que se redacta en la cláusula de plazo (null = indeterminado).
 *  - max: plazo máximo legal (informativo).
 *  - nota: aclaración adicional para la redacción.
 */
class TiposContrato
{
    public static function all(): array
    {
        return [
            'indeterminado' => [
                'label' => 'A plazo indeterminado',
                'titulo' => 'A PLAZO INDETERMINADO',
                'plazo_fijo' => false, 'causa' => null, 'max' => null, 'nota' => null,
            ],
            'inicio_actividad' => [
                'label' => 'Plazo fijo — Inicio o incremento de actividad',
                'titulo' => 'SUJETO A MODALIDAD — POR INICIO O INCREMENTO DE ACTIVIDAD',
                'plazo_fijo' => true,
                'causa' => 'por el inicio o incremento de las actividades de EL EMPLEADOR',
                'max' => '3 años', 'nota' => null,
            ],
            'necesidades_mercado' => [
                'label' => 'Plazo fijo — Necesidades del mercado',
                'titulo' => 'SUJETO A MODALIDAD — POR NECESIDADES DEL MERCADO',
                'plazo_fijo' => true,
                'causa' => 'por necesidades del mercado que motivan un incremento coyuntural de la producción',
                'max' => '5 años', 'nota' => null,
            ],
            'obra_servicio' => [
                'label' => 'Plazo fijo — Obra determinada o servicio específico',
                'titulo' => 'PARA OBRA DETERMINADA O SERVICIO ESPECÍFICO',
                'plazo_fijo' => true,
                'causa' => 'para la ejecución de la obra determinada o servicio específico que constituye su objeto',
                'max' => 'el que resulte necesario', 'nota' => null,
            ],
            'ocasional' => [
                'label' => 'Plazo fijo — Ocasional',
                'titulo' => 'SUJETO A MODALIDAD — OCASIONAL',
                'plazo_fijo' => true,
                'causa' => 'para atender necesidades transitorias distintas a la actividad habitual del centro de trabajo',
                'max' => '6 meses al año', 'nota' => null,
            ],
            'suplencia' => [
                'label' => 'Plazo fijo — Suplencia',
                'titulo' => 'SUJETO A MODALIDAD — DE SUPLENCIA',
                'plazo_fijo' => true,
                'causa' => 'para sustituir a un trabajador estable cuyo vínculo laboral se encuentra suspendido',
                'max' => 'mientras dure la suspensión', 'nota' => null,
            ],
            'intermitente' => [
                'label' => 'Plazo fijo — Intermitente',
                'titulo' => 'SUJETO A MODALIDAD — INTERMITENTE',
                'plazo_fijo' => true,
                'causa' => 'para cubrir necesidades de actividades permanentes pero discontinuas',
                'max' => 'según la actividad', 'nota' => null,
            ],
            'temporada' => [
                'label' => 'Plazo fijo — Temporada',
                'titulo' => 'SUJETO A MODALIDAD — DE TEMPORADA',
                'plazo_fijo' => true,
                'causa' => 'para atender necesidades propias del giro de la empresa que se cumplen en determinadas épocas del año',
                'max' => 'según la temporada', 'nota' => null,
            ],
            'tiempo_parcial' => [
                'label' => 'Tiempo parcial (part-time)',
                'titulo' => 'A TIEMPO PARCIAL',
                'plazo_fijo' => false, 'causa' => null, 'max' => null,
                'nota' => 'La jornada es menor a cuatro (4) horas diarias en promedio.',
            ],
            'construccion_civil' => [
                'label' => 'Construcción civil (por obra)',
                'titulo' => 'DE CONSTRUCCIÓN CIVIL (POR OBRA)',
                'plazo_fijo' => true,
                'causa' => 'para la ejecución de la obra de construcción civil que constituye su objeto, sujeto al régimen especial de construcción civil',
                'max' => 'mientras dure la obra',
                'nota' => 'Se rige por el régimen especial de construcción civil.',
            ],
            'mype' => [
                'label' => 'Régimen MYPE',
                'titulo' => 'BAJO EL RÉGIMEN LABORAL MYPE',
                'plazo_fijo' => false, 'causa' => null, 'max' => null,
                'nota' => 'Sujeto al régimen laboral especial de la micro y pequeña empresa (Ley MYPE).',
            ],
        ];
    }

    /** Devuelve la definición de un tipo; si no existe, asume indeterminado. */
    public static function get(?string $codigo): array
    {
        $todos = self::all();

        return $todos[$codigo] ?? $todos['indeterminado'];
    }

    /** Opciones {value,label,plazo_fijo} para el desplegable del formulario. */
    public static function opciones(): array
    {
        return collect(self::all())
            ->map(fn ($t, $k) => ['value' => $k, 'label' => $t['label'], 'plazo_fijo' => $t['plazo_fijo']])
            ->values()->all();
    }
}
