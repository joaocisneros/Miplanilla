<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BoletaController extends Controller
{
    public function pdf(Request $request, PayrollDetail $detalle)
    {
        // Honorarios (RxH) tiene su propio "recibo" en el módulo Honorarios (sin
        // pensión/renta/aportes); la boleta de planilla no le corresponde. Se usa la
        // modalidad congelada en el detalle, no la actual del empleado.
        abort_if(($detalle->modalidad ?? 'planilla') === 'honorarios', 404);

        $pdf = $this->generarPdf($detalle);

        return $pdf->download($this->nombreBoleta($detalle));
    }

    /** Descarga TODAS las boletas de planilla (excluye honorarios) en un solo ZIP. */
    public function zip(Request $request, Payroll $payroll)
    {
        $payroll->load(['empresa:id,razon_social', 'periodo', 'detalles.employee']);

        $detalles = $payroll->detalles->filter(fn ($d) => ($d->modalidad ?? 'planilla') !== 'honorarios');

        if ($detalles->isEmpty()) {
            return back()->with('error', 'La planilla no tiene boletas para descargar.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'boletas_').'.zip';
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($detalles as $detalle) {
            $pdf = $this->generarPdf($detalle);
            $zip->addFromString($this->nombreBoleta($detalle), $pdf->output());
        }
        $zip->close();

        $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($payroll->empresa->razon_social));
        $nombre = "boletas_{$slug}_{$payroll->periodo->anio}_".str_pad((string) $payroll->periodo->mes, 2, '0', STR_PAD_LEFT).'.zip';

        return response()->download($tmp, $nombre)->deleteFileAfterSend(true);
    }

    /** Construye el PDF de una boleta (reutilizado por pdf() y zip()). */
    private function generarPdf(PayrollDetail $detalle): \Barryvdh\DomPDF\PDF
    {
        $detalle->loadMissing([
            'employee.sede:id,nombre',
            'employee.contratoVigente.cargo:id,nombre',
            'employee.contratoVigente.area:id,nombre',
            'payroll.empresa', 'payroll.periodo',
        ]);

        return Pdf::loadView('boletas.pdf', [
            'd' => $detalle,
            'emp' => $detalle->employee,
            'contrato' => $detalle->employee->contratoVigente->first(),
            'empresa' => $detalle->payroll->empresa,
            'periodo' => $detalle->payroll->periodo,
            'desglose' => $detalle->desglose,
        ]);
    }

    private function nombreBoleta(PayrollDetail $detalle): string
    {
        return 'boleta_'.$detalle->employee->numero_documento.'_'.$detalle->payroll->periodo->anio.'_'.$detalle->payroll->periodo->mes.'.pdf';
    }
}
