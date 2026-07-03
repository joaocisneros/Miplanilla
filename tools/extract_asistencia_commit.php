<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Attendance;
use App\Models\Empresa;
use App\Models\Employee;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsxDate;

$empresa = Empresa::where('razon_social', 'ACS EIRL')->firstOrFail();

// Mapa de empleados de ACS EIRL por nombre normalizado
$norm = fn ($s) => preg_replace('/\s+/', ' ', trim(str_replace(',', ' ', mb_strtoupper((string) $s))));
$empleados = Employee::where('empresa_id', $empresa->id)->get()
    ->keyBy(fn ($e) => $norm($e->nombre_completo));

$path = __DIR__.'/../Planilla 1ra quinc jun 26 - ACS EIRL FINAL.xlsx';
$reader = IOFactory::createReader('Xlsx');
$reader->setReadDataOnly(true);
$sheet = $reader->load($path)->getSheetByName('ASISTENCIA');

$val = function (string $coord) use ($sheet) {
    $cell = $sheet->getCell($coord);
    $v = $cell->getValue();
    if (is_string($v) && str_starts_with($v, '=')) return $cell->getOldCalculatedValue();
    return $v;
};

$diaCols = [];
for ($c = 4; $c <= Coordinate::columnIndexFromString($sheet->getHighestColumn()); $c += 9) {
    $serial = $val(Coordinate::stringFromColumnIndex($c).'17');
    if (! is_numeric($serial) || $serial < 40000) continue;
    $diaCols[] = ['col' => $c, 'fecha' => XlsxDate::excelToDateTimeObject($serial)->format('Y-m-d')];
}

$mapEstado = function (string $raw, bool $finde) {
    $r = mb_strtoupper($raw);
    if (str_contains($r, 'VACACIONES')) return 'VACACIONES';
    if (str_contains($r, 'JUSTIFIC')) return 'FALTA_JUSTIFICADA';
    if (str_contains($r, 'LICENCIA')) return 'LICENCIA';
    if (str_contains($r, 'TRABAJO SABADO')) return 'TRABAJO_SABADO';
    if (str_contains($r, 'TRABAJO DOMINGO')) return 'TRABAJO_DOMINGO';
    if (str_contains($r, 'FERIADO') || str_contains($r, '1RO MAYO')) return 'TRABAJO_FERIADO';
    if (str_contains($r, 'FALTA')) return $finde ? 'DESCANSO' : 'FALTA';
    return $finde ? 'DESCANSO' : 'NORMAL';
};

// Limpia asistencia previa de ACS EIRL (incluye la demo)
Attendance::where('empresa_id', $empresa->id)->delete();

$creados = 0; $noMatch = [];
for ($r = 19; $r <= 80; $r++) {
    $nombre = $val('B'.$r);
    if (! $nombre || trim((string) $nombre) === '') break;
    $emp = $empleados->get($norm($nombre));
    if (! $emp) { $noMatch[] = $nombre; continue; }

    foreach ($diaCols as $d) {
        $base = $d['col'];
        $finde = Carbon::parse($d['fecha'])->isWeekend();
        $estadoRaw = trim((string) $val(Coordinate::stringFromColumnIndex($base + 7).$r));
        $tmin = $val(Coordinate::stringFromColumnIndex($base + 4).$r);
        $lv = $val(Coordinate::stringFromColumnIndex($base + 5).$r);
        $sd = $val(Coordinate::stringFromColumnIndex($base + 6).$r);

        $estado = $mapEstado($estadoRaw, $finde);
        $tard = is_numeric($tmin) ? (int) round($tmin) : 0;
        $he = round(((is_numeric($lv) ? $lv : 0) + (is_numeric($sd) ? $sd : 0)) / 60, 2);

        // Solo guardamos días con valor (laborados o con incidencia); descansos vacíos se omiten
        if ($estado === 'DESCANSO' && $tard === 0 && $he == 0) continue;

        Attendance::create([
            'empresa_id' => $empresa->id,
            'employee_id' => $emp->id,
            'fecha' => $d['fecha'],
            'estado' => $estado,
            'minutos_tarde' => in_array($estado, ['NORMAL','TRABAJO_SABADO','TRABAJO_DOMINGO','TRABAJO_FERIADO']) ? $tard : 0,
            'horas_extra' => in_array($estado, ['NORMAL','TRABAJO_SABADO','TRABAJO_DOMINGO','TRABAJO_FERIADO']) ? min($he, 12) : 0,
            'origen' => 'excel',
        ]);
        $creados++;
    }
}

echo "Registros creados: {$creados}".PHP_EOL;
echo 'Empleados sin match: '.(count($noMatch) ? implode(', ', $noMatch) : 'ninguno').PHP_EOL;
echo 'Total asistencia ACS EIRL: '.Attendance::where('empresa_id', $empresa->id)->count().PHP_EOL;
