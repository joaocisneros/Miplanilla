<?php

namespace App\Http\Controllers;

use App\Models\PayrollDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BoletaController extends Controller
{
    public function pdf(Request $request, PayrollDetail $detalle)
    {
        $detalle->load(['employee', 'payroll.empresa', 'payroll.periodo']);
        abort_if((int) $detalle->payroll->empresa_id !== (int) $request->session()->get('empresa_id'), 403);

        $pdf = Pdf::loadView('boletas.pdf', [
            'd' => $detalle,
            'emp' => $detalle->employee,
            'empresa' => $detalle->payroll->empresa,
            'periodo' => $detalle->payroll->periodo,
            'desglose' => $detalle->desglose,
        ]);

        $nombre = 'boleta_'.$detalle->employee->numero_documento.'_'.$detalle->payroll->periodo->anio.'_'.$detalle->payroll->periodo->mes.'.pdf';

        return $pdf->download($nombre);
    }
}
