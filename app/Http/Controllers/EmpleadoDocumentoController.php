<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmpleadoDocumentoController extends Controller
{
    /** Genera la ficha de registro en PDF (para imprimir y firmar). */
    public function ficha(Employee $empleado)
    {
        $empleado->load(['empresa', 'sede:id,nombre', 'contratoVigente.cargo:id,nombre', 'contratoVigente.area:id,nombre', 'derechohabientes']);

        $pdf = Pdf::loadView('empleados.ficha', [
            'emp' => $empleado,
            'contrato' => $empleado->contratoVigente->first(),
            'empresa' => $empleado->empresa,
            'derechohabientes' => $empleado->derechohabientes,
        ]);

        return $pdf->download('ficha_'.$empleado->numero_documento.'.pdf');
    }

    /** Genera el contrato de trabajo en PDF (para imprimir y firmar). */
    public function contrato(Employee $empleado)
    {
        $empleado->load([
            'empresa', 'sede:id,nombre,direccion',
            'contratoVigente.cargo:id,nombre', 'contratoVigente.area:id,nombre',
            'contratoVigente.turno:id,nombre,hora_entrada,hora_salida',
        ]);

        $contrato = $empleado->contratoVigente->first();
        abort_unless($contrato, 404, 'El trabajador no tiene contrato vigente.');

        $pdf = Pdf::loadView('empleados.contrato', [
            'emp' => $empleado,
            'contrato' => $contrato,
            'empresa' => $empleado->empresa,
            'sede' => $empleado->sede,
        ]);

        return $pdf->download('contrato_'.$empleado->numero_documento.'.pdf');
    }

    /** Sube y archiva un documento del empleado (ficha firmada escaneada, DNI, etc.). */
    public function subir(Request $request, Employee $empleado)
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:ficha_firmada,dni,contrato,otro'],
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ]);

        $archivo = $request->file('archivo');
        $ruta = $archivo->store('empleados/'.$empleado->id);

        $empleado->documentos()->create([
            'tipo' => $data['tipo'],
            'nombre_original' => $archivo->getClientOriginalName(),
            'ruta' => $ruta,
            'mime' => $archivo->getClientMimeType(),
            'size' => $archivo->getSize(),
            'subido_por' => $request->user()->id,
        ]);

        return back()->with('success', 'Documento archivado.');
    }

    public function descargar(EmployeeDocument $documento)
    {
        abort_unless(Storage::exists($documento->ruta), 404);

        return Storage::download($documento->ruta, $documento->nombre_original);
    }

    public function eliminar(EmployeeDocument $documento)
    {
        Storage::delete($documento->ruta);
        $documento->delete();

        return back()->with('success', 'Documento eliminado.');
    }
}
