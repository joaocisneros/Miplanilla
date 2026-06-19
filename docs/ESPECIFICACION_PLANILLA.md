# Especificación de Reglas de Negocio — Sistema de Planilla (MiPlanilla)

> Documento de reglas extraído del archivo `Planilla 1ra quinc jun 26 - ACS EIRL FINAL.xlsx`.
> Fuente: inspección estructural en `tools/payroll_inspection.json`.
> Objetivo: ser la **fuente de verdad** para implementar el motor de cálculo en PHP/Laravel
> sin introducir errores respecto al Excel actual.
>
> Periodo de ejemplo: **1ra quincena de junio 2026** — empresa ACS EIRL.
> Régimen: planilla **quincenal** (se paga por mitades del mes).

---

## 1. Visión general y flujo de datos

El Excel está compuesto por 6 hojas que forman una cadena de cálculo:

```
ASISTENCIA            HOJA RXH                  1RA QUINCENA JUNIO 26 (PLANILLA)
(marcas diarias  →    (totales por concepto  →  (cálculo final de remuneración,
 por empleado)         por empleado/quincena)    descuentos, aportes y neto)
                                                        ↑           ↑
                                                     TASAS      quinta categoria + Tabla
                                                  (AFP/ONP/SBS)  (renta 5ta, comisiones, RMV)
```

| Hoja | Rol | Dimensión |
|------|-----|-----------|
| `ASISTENCIA` | Registro diario de asistencia (1 columna por día), con leyenda de estados y cálculo de tardanzas por minutos | A1:ES37 (149 cols) |
| `HOJA RXH` | Consolida por empleado los totales de cada concepto (horas extras, sábados, feriados, vacaciones, licencias, faltas, tardanzas, días trabajados) separados por 1ra y 2da quincena | A3:AH20 |
| `1RA QUINCENA JUNIO 26` | Planilla principal: 62 columnas con datos del trabajador, ingresos, descuentos, retenciones, aportes del empleador y neto | A1:BK142 |
| `TASAS` | Tasas oficiales AFP/ONP (SBS), tabla nombrada `TAB_AFP` | A1:O34 |
| `quinta categoria` | Cálculo de renta de 5ta categoría (proyección anual, 7 UIT, escala progresiva) | B1:K20 |
| `Tabla` | Comisiones AFP (fija/flujo/mixta), asignación familiar, RMV, límites | A1:M11 |

**Constantes globales — ⚠️ valores del Excel vs. valores vigentes 2026:**

| Parámetro | Valor en el Excel (desactualizado) | **Valor correcto a usar (vigente 2026)** |
|-----------|-----------------------------------|------------------------------------------|
| Días base prorrateo | 30 (`$C$3`) | 30 |
| Quincena | 15 días | 15 días |
| UIT | S/ 5,500 (`quinta categoria!G18`) | confirmar UIT 2026 con MEF/SUNAT |
| RMV | S/ 1,025 (`Tabla!D11`) | **S/ 1,130** |
| Asignación familiar (10% RMV) | S/ 102.50 | **S/ 113.00** |

> Estos valores **NO se hardcodean**: viven en `parametros_periodo` versionados por vigencia.
> El Excel usa cifras viejas (RMV 1,025 / AF 102.50); la planilla operativa real usa
> RMV 1,130 / AF 113. Todo parámetro debe confirmarse con el contador y fecharse.

---

## 2. Hoja ASISTENCIA (entrada de datos diaria)

Cada empleado tiene una fila; cada día del periodo es una columna (rango `D:EG`).
En cada celda diaria se escribe un **estado** según la leyenda:

| Estado (texto en celda) | Significado |
|-------------------------|-------------|
| `DIA NORMAL` (vacío/normal) | Asistió normal |
| `VACACIONES` | En vacaciones |
| `FALTA` | Faltó |
| `LICENCIA` | Licencia |
| `JUSTIFICADO` | Falta justificada |
| `TRABAJO SABADO` | Trabajó sábado |
| `TRABAJO DOMINGO` | Trabajó domingo |
| `TRABAJO FERIADO` | Trabajó feriado |
| `TRABAJO 1RO MAYO` | Trabajó 1° de mayo |
| `TRABAJO 1RO MAYO EN SU DIA LIBRE` | Trabajó 1° mayo en día libre |

**Configuración de turnos / tardanzas** (parte superior de la hoja):
- Define hora de entrada, salida, refrigerio y minutos esperados por turno.
- `G = C - B - F` (horas efectivas = salida − entrada − refrigerio).
- `H = G * 1440` (minutos esperados).
- La tardanza se mide en **minutos** comparando contra los minutos esperados del turno.

**Columnas de agregación por empleado (EK..ES):**

| Col | Fórmula | Significado |
|-----|---------|-------------|
| `EM` | `COUNTIF` de días con marca > 0 | Días con horas extras (cant.) |
| `EN` | `=COUNTIF(D:EG,"FALTA")` | Días de falta |
| `EO` | `=15-EN` | Días efectivos (quincena − faltas) |
| `EP` | `=COUNTIF(D:EG,"TRABAJO SABADO")` | Sábados trabajados |
| `EQ` | `=COUNTIF(D:EG,"TRABAJO DOMINGO")+...` | Domingos/feriados trabajados |
| `ER` | `=COUNTIF(D:EG,"VACACIONES")` | Días de vacaciones |
| `ES` | `=COUNTIF(D:EG,"LICENCIA")` | Días de licencia |
| `EK` | `=SUM(...)` de bloques de minutos | Total de minutos de tardanza |

> **Regla de implementación:** en Laravel esto NO se modela como 149 columnas, sino como
> filas `asistencias (empleado_id, fecha, estado, minutos_tarde, horas_extra)`. Las
> agregaciones (EM..ES) se calculan con `GROUP BY` / `whereBetween` sobre el periodo.

### 2.1 Integración con reloj biométrico (fuente real de asistencia)

La empresa cuenta con **reloj biométrico**, por lo que la asistencia NO se digita a mano:
se importa desde el dispositivo. Esto reemplaza la captura manual del Excel ASISTENCIA.

**Arquitectura propuesta:**
- Tabla cruda `marcaciones` (raw): `empleado_id` (mapeado por DNI/código biométrico),
  `fecha_hora`, `tipo` (entrada/salida), `dispositivo`, `origen`. Una fila por marca.
- Proceso ETL `ImportarMarcacionesService` que lee del biométrico y vuelca a `marcaciones`.
- Proceso `ProcesarAsistenciaService` que, a partir de las marcaciones crudas + el turno
  asignado al empleado, **deriva** cada día: estado (NORMAL/FALTA/...), minutos de tardanza
  (entrada real − hora esperada del turno) y horas extra. Resultado → tabla `asistencias`.
- Sobre `asistencias` corren los mismos agregados que hoy hace ASISTENCIA (EM..ES).

**Fuentes típicas de un biométrico (según marca):**
- **ZKTeco** (lo más común en Perú): base SQL Server / Access del software (ZKTime, ZKBio),
  SDK, o export a Excel/CSV/TXT. También endpoint *push* HTTP en modelos con ADMS.
- **Hikvision / Suprema / Anviz**: API/SDK propio o export.

**Reglas a definir con el reloj:**
1. Mapeo de identidad: ID biométrico ↔ DNI ↔ `empleado_id`.
2. Emparejamiento de marcas: cómo formar pares entrada/salida por día y turno.
3. Tolerancias: minutos de gracia antes de contar tardanza; cálculo de horas extra
   (umbral, redondeo a bloques de 10 según fórmula `T/30/10` del Excel).
4. Manejo de excepciones manuales: vacaciones, licencias, DM, justificaciones, feriados
   y "trabajo sábado/domingo/feriado" — estos siguen siendo **marcas/estados manuales o
   por permiso** que se superponen a las marcaciones del reloj.
5. Días sin marca → candidato a FALTA (salvo descanso/feriado/permiso registrado).

> Pendiente confirmar: **marca y modelo del biométrico** y cómo expone los datos
> (BD directa, archivo de export, o API), para definir el conector concreto.

**Campos esenciales de `marcaciones` (requisitos firmes para trazabilidad):**
- `hash_unico` — hash de (device + código_trabajador + fecha_hora + tipo) para **evitar
  duplicados** en reimportaciones (índice único).
- `codigo_trabajador_origen` — el código tal cual viene del reloj (no el `employee_id` interno).
- `device_id` / `codigo_dispositivo_origen` — qué reloj generó la marca.
- `fecha_hora_marca` (hora local del evento) + `zona_horaria` + `fecha_hora_recepcion`
  (cuándo la recibió el sistema).
- `tipo` (entrada/salida/desconocido) y `metodo` (huella/rostro/tarjeta).
- `raw_payload` — registro original íntegro del reloj (conservación del original).
- `procesada` (bool) — si ya se consolidó en `attendance`.

**Reglas operativas a definir (requisitos firmes):**
- **Política de marcas incompletas/múltiples:** qué hacer si falta entrada o salida, o si hay
  marcas repetidas el mismo día (tomar primera/última, marcar incidencia, etc.).
- **Calendarios:** feriados nacionales/locales, descansos semanales, días no laborables.
- **Turnos nocturnos:** jornadas que cruzan medianoche (entrada un día, salida el siguiente).
- **Flujo formal de aprobación de horas extras:** las HE solo se pagan si fueron **autorizadas**
  (solicitud → aprobación supervisor/RRHH) antes de entrar al cálculo.
- **Conservación:** `marcaciones` y `raw_payload` inmutables; toda corrección vía `incidencias`
  + `audit_logs`, conservando el registro original.

---

## 3. Hoja HOJA RXH (consolidación por empleado)

Toma los agregados de ASISTENCIA y los presenta por empleado en columnas
**1ra quincena / 2da quincena / total**. Cada bloque referencia a ASISTENCIA:

| Concepto | Origen (1ra quinc.) | Total |
|----------|---------------------|-------|
| Horas extra | `=ASISTENCIA!EM{fila}` | `=D+E` |
| Sábados | `=ASISTENCIA!EP{fila}` | `=H+I` |
| Feriados y domingos | `=ASISTENCIA!EQ{fila}` | `=L+M` |
| Vacaciones | `=ASISTENCIA!ER{fila}` | `=P+Q` |
| Licencia | `=ASISTENCIA!ES{fila}` | `=T+U` |
| Faltas | `=ASISTENCIA!EN{fila}` | `=X+Y` |
| Tardanza (min) | `=ASISTENCIA!EK{fila}` | `=AB+AC` |
| Días trabajados | `=ASISTENCIA!EF/EH{fila}` | `=AF+AG` |

> En Laravel esta hoja desaparece como entidad: es una **vista/consulta agregada** del
> registro de asistencia filtrado por quincena. Se conserva solo como reporte.

---

## 4. Hoja PLANILLA (cálculo principal) — mapa de columnas

> 🛑 **ESTA SECCIÓN DOCUMENTA LO QUE HACE EL EXCEL, INCLUIDOS SUS ERRORES.**
> NO es la regla a implementar. Sirve solo para entender el origen y la estructura.
> Las **reglas correctas** a programar están en **§9.1 (correcciones), §9.2 (naturaleza
> remunerativa) y §9.3 (horas extras)**. Donde §4 y §9 difieran, **manda §9**.
> En particular son INCORRECTOS aquí: horas extras (`AN = T/30/10`), base de aportes
> (solo `AJ`), descuentos que mezclan movilidad, y `días = 15 − faltas`.

Cada fila es un trabajador. Leyenda: `P/Q/R/S` = componentes del sueldo, `AJ` =
remuneración quincenal base, `AR` = adicionales quincenales, `BA` = retención al
trabajador, `BC` = neto, `BD..BG` = aportes del empleador.

### 4.1 Datos del trabajador (A–O) — solo captura, sin cálculo
| Col | Campo |
|-----|-------|
| A | N° / orden |
| B | Apellidos y nombres |
| C | Fecha de nacimiento |
| D | DNI |
| E | Fecha de ingreso |
| F | Fecha de cese |
| G | Género |
| H | Tipo de contrato (ej. A PLAZO FIJO) |
| I | Categoría ocupacional (EMPLEADO/OBRERO) |
| J | ¿Afecto ONP? (SI/NO) |
| K | ¿SCTR? (SI/NO) |
| L | ¿Senati? (SI/NO) |
| M | Código AFP |
| N | Área |
| O | Cargo |

### 4.2 Conceptos remunerativos base (P–T)
| Col | Campo | Fórmula |
|-----|-------|---------|
| P | Sueldo básico | (dato) |
| Q | Asignación familiar | (dato — S/ 113.00 vigente 2026 si aplica) |
| R | Movilidad | (dato) |
| S | Por fuera | (dato) |
| **T** | **Total mensual** | `=P+Q+R+S` |

### 4.3 Asistencia y descuentos (U–AI)
| Col | Campo | Fórmula | Regla |
|-----|-------|---------|-------|
| U | Días trabajados | `=HOJA RXH!AH` (← ASISTENCIA) | |
| V | DM (descanso médico) | (dato) | |
| W | Días falto (cant.) | `=HOJA RXH!Z` | |
| X | **Descuento por falta** | `=((P+Q)/$C$3)*W` | (Básico+AsigFam)/30 × días falta |
| Y | Tardanzas (cant. min) | `=HOJA RXH` | |
| Z | **Descuento por tardanza** | `=(T/30/8/60*Y)` | Total/30 días/8 h/60 min × minutos |
| AA | Pago gratificación | (dato) | |
| AB | Licencia | (dato) | |
| AC | Subsidio | `=(P/30)*V` | Básico/30 × días DM |
| AD/AE/AF | Liq. vacaciones (días/monto/final) | (dato) | |
| AG | Incentivos | (dato) | |
| AH | Licencia hijo enfermo (cant.) | `=HOJA RXH!V` | |
| AI | Descuento lic. hijo | (dato) | |

### 4.4 Remuneración quincenal y adicionales (AJ–AR)
| Col | Campo | Fórmula |
|-----|-------|---------|
| **AJ** | **Remuneración quincenal (base afecta)** | `=(((P+Q)/30)*U) - Z + AA + AB + AC + AF + AG + AI` |
| AK | Sábados (cant.) | `=HOJA RXH!J` |
| AL | Monto sábados | `=T/30*AK` |
| AM | Horas extras (cant.) | `=HOJA RXH!F` |
| AN | Monto horas extras | `=T/30/10*AM` |
| AO | Domingos y feriados (cant.) | `=HOJA RXH!N` |
| AP | Monto domingos/feriados | `=T/30*AO` |
| AQ | Incentivo por producción | (dato) |
| **AR** | **Total movilidad/adicionales quincenal** | `=((R/$C$3)*(U-V)) + AL + AP + AN + AQ` |

> **Nota clave:** `AJ` (base afecta a aportes) NO incluye movilidad ni adicionales (`AR`).
> La movilidad se prorratea aparte y va al neto pero no a la base de AFP/ONP/ESSALUD.

### 4.5 Sistema de pensiones — descuentos al trabajador (AS–BA)
| Col | Campo | Fórmula | Regla |
|-----|-------|---------|-------|
| AS | Sistema de pensiones | (dato: `ONP` o nombre AFP, ej. `INTEGRA MIXTA`) | |
| AT | Tasa comisión AFP | (dato según AFP, ej. 0) | comisión sobre flujo/mixta |
| AU | Tasa prima seguro AFP | (dato, ej. 0.0137) | |
| AV | **ONP** | `=IF(AS="ONP", VLOOKUP(AS,TAB_AFP,8,0)*AJ, 0)` | 13% si ONP |
| AW | **Aporte obligatorio AFP (10%)** | `=IF(AS<>"ONP", AJ*$AW$8, 0)` | `$AW$8 = 0.10` |
| AX | **Comisión AFP** | `=IF(AS<>"ONP", AJ*AT, 0)` | |
| AY | **Prima de seguro AFP** | `=IF(AU<>"ONP", AJ*AU, 0)` | |
| AZ | Total descuento AFP | `=AX+AY+AW` | |
| **BA** | **Retención AFP/ONP total** | `=AZ+AV` | |

> Lógica: si está en **ONP**, solo aplica `AV = 13%×AJ`. Si está en **AFP**, aplica
> `AW (10% fondo) + AX (comisión) + AY (prima seguro)`. Las tasas de comisión y prima
> dependen de la AFP elegida y del tipo (MIXTA = sobre flujo + saldo / SUELDO = solo flujo).

### 4.6 Renta de 5ta, neto y aportes del empleador (BB–BJ)
| Col | Campo | Fórmula | Regla |
|-----|-------|---------|-------|
| BB | Retención renta 5ta categoría | (de hoja `quinta categoria`) | ver §5 |
| **BC** | **Neto a pagar** | `=AJ + AR - BA - BB` | base + adicionales − pensión − 5ta |
| BD | **ESSALUD (9%)** — empleador | `=ROUND(AJ*$BD$8, 2)` | `$BD$8 = 0.09`, base = AJ |
| BE | **SCTR Pensión (2.14%)** — empleador | `=IF(K="SI", AJ*2.14%, 0)` | |
| BF | SCTR Salud | (dato) | |
| BG | **SVL — Seguro Vida Ley (DL 688)** | `=P*0.54%` | 0.54% del sueldo básico |
| BH | Adelantos / préstamos | (dato) | |
| BI | Reintegro | (dato) | |
| **BJ** | **A PAGAR (final)** | `=BC - BH + BI` | neto − adelantos + reintegro |

---

## 5. Renta de 5ta categoría (hoja `quinta categoria`)

Cálculo anual proyectado, luego dividido entre 12 para la retención mensual.

```
G5  Proyección de ingresos anuales      = (remuneración mensual estimada × meses) + grat. etc.
G6  Deducción 7 UIT                      = UIT × 7              (= 5500 × 7 = 38,500)
G7  Renta neta gravable                  = MAX(G5 - G6, 0)
```

**Escala progresiva por tramos (acumulativa):**

| Tramo | Tasa | Límite (en S/) |
|-------|------|----------------|
| Hasta 5 UIT | 8% | 27,500 |
| Más de 5 hasta 20 UIT | 14% | 110,000 |
| Más de 20 hasta 35 UIT | 17% | 192,500 |
| Más de 35 hasta 45 UIT | 20% | 247,500 |
| Más de 45 UIT | 30% | > 247,500 |

```
G15 Impuesto anual = SUMA(impuesto de cada tramo afecto)
G17 Retención mensual = G15 / 12   ← ⚠️ SIMPLIFICACIÓN INCORRECTA del Excel
```

> ⚠️ **El Excel divide el impuesto anual entre 12, lo cual NO es el procedimiento legal.**

### 5.1 Procedimiento correcto SUNAT (a implementar)

La retención es **acumulada y progresiva por mes**, según el procedimiento del art. 40 del
Reglamento de la LIR. Resumen del algoritmo mensual:

1. **Proyectar** la remuneración anual: remuneración del mes × meses que faltan (incluido el
   actual) + gratificaciones que correspondan + remuneraciones ya percibidas en el año +
   retenciones/ingresos previos.
2. Restar **7 UIT** → renta neta proyectada.
3. Aplicar la **escala progresiva** (8/14/17/20/30%) → impuesto anual proyectado.
4. Determinar la **fracción del impuesto** que corresponde al mes según la tabla de divisores
   SUNAT por mes (ene–mar: /12; abr: /9; may–jul: /8; ago: /5; etc.), **restando lo ya
   retenido en meses anteriores** del año.
5. En meses con ingresos extraordinarios (gratif., utilidades, bonificaciones) se aplica el
   procedimiento adicional sobre ese extraordinario.

> Implementación: `Renta5taService` que mantenga **retenciones acumuladas** por trabajador y
> por año (tabla `retenciones_5ta` o columna acumulada), no un simple /12. UIT desde
> `parametros_periodo`. Esto es un requisito firme: el motor necesita estado acumulado anual.

### 5.2 Particularidad de planilla quincenal

Como se paga en quincenas, la retención mensual de 5ta se **prorratea/aplica** según política
(toda en la 2da quincena, o mitad y mitad). Definir con el contador. El cálculo del impuesto
es **mensual**; la quincena solo decide cuándo se descuenta.

---

## 6. Tasas AFP/ONP (hojas `TASAS` y `Tabla`)

Tabla nombrada **`TAB_AFP`** = `TASAS!$B$5:$I$13`. Columnas del rango (B=1 … I=8):

| # | Columna | Contenido |
|---|---------|-----------|
| 1 | AFP (nombre) | HABITAT MIXTA, INTEGRA MIXTA, PRIMA MIXTA, PROFUTURO MIXTA, *_SUELDO, ONP |
| 4 | Comisión sobre flujo | ej. INTEGRA MIXTA 0.0056 / SUELDO 0.0155 |
| 5 | Comisión anual sobre saldo | (mixtas) |
| 6 | Prima seguro | 0.0174 |
| 7 | Aporte obligatorio | 0.10 |
| 8 | Remuneración máxima asegurable | 9,665.33 (mixtas) / fila ONP usada como **0.13** |

Valores de ejemplo (al devengue del archivo):

| AFP | Comisión flujo | Comisión saldo | Prima | Aporte |
|-----|---------------|----------------|-------|--------|
| HABITAT MIXTA | 0.0038 | 0.0125 | 0.0174 | 0.10 |
| INTEGRA MIXTA | 0.0056 | 0.0120 | 0.0174 | 0.10 |
| PRIMA MIXTA | 0.0018 | 0.0125 | 0.0174 | 0.10 |
| PROFUTURO MIXTA | 0.0067 | 0.0120 | 0.0174 | 0.10 |
| ONP | — | — | — | 0.13 |

> ⚠️ Hay un `#REF!` en una definición de `TAB_AFP` en el Excel — síntoma de fragilidad
> que justifica la migración. En BD: tabla `tasas_afp` versionada por periodo de devengue.

**Asignación familiar** (`Tabla`): `RMV × 10%`. En el Excel = `1025 × 10% = 102.50`
(desactualizado); **vigente 2026 = `1130 × 10% = 113.00`**.

---

## 7. Conceptos calculados — resumen formulario

```
Total mensual            T  = P + Q + R + S
Descuento falta          X  = (P+Q)/30 × días_falta
Descuento tardanza       Z  = T/30/8/60 × minutos_tarde
Subsidio                 AC = P/30 × días_DM
Remuneración quincenal   AJ = (P+Q)/30 × días_trab − Z + AA + AB + AC + AF + AG + AI
Monto sábados            AL = T/30 × sábados
Monto horas extras       AN = T/30/10 × horas_extra
Monto domingos/feriados  AP = T/30 × domingos
Total adicionales        AR = (R/30 × (días_trab − DM)) + AL + AP + AN + AQ
ONP                      AV = 13% × AJ        (si régimen = ONP)
AFP aporte               AW = 10% × AJ        (si AFP)
AFP comisión             AX = tasa_com × AJ   (si AFP)
AFP prima                AY = tasa_prima × AJ (si AFP)
Retención pensión        BA = AW + AX + AY + AV
Neto                     BC = AJ + AR − BA − BB(5ta)
ESSALUD (empleador)      BD = 9% × AJ
SCTR pensión (empleador) BE = 2.14% × AJ      (si SCTR = SI)
SVL (empleador)          BG = 0.54% × P
A pagar                  BJ = BC − adelantos + reintegro
```

---

## 7.1 Ficha de Registro de Personal (alta de empleado)

La empresa usa una **"FICHA DE REGISTRO DE PERSONAL" (ACSA PERU)** físico para dar de alta
trabajadores. Es la fuente de captura del módulo de empleados. Campos a digitalizar:

**Datos personales:** apellido paterno, apellido materno, nombres, sexo, edad, estado civil,
lugar de nacimiento, fecha de nacimiento, profesión/ocupación, DNI/CE/CI, RUC, teléfono/celular,
fotografía.

**Domicilio:** vía (Av./Jr./Calle/Psje), distrito, provincia, departamento, correo electrónico,
tipo de vivienda (propia/alquilada/otros).

**Nivel educativo:** texto + código de catálogo (05 Primaria completa … 13 Univ. completa).

**Sistema pensionario:** SPP INTEGRA / SPP HORIZONTE / SPP PROFUTURO / SPP PRIMA / SNP-ONP /
otros, fecha de ingreso a AFP/ONP, código AFP. → alimenta `contracts.sistema_pensiones`.

**Área laboral:** área/planta (ej. PLANTA CAJAMARQUILLA), fecha de inicio, tipo de contrato,
aporta SCTR (sí/no), seguro de vida, sueldo básico, movilidad, otros, cargo/actividad
(MAESTRO / OFICIAL / AYUDANTE). → alimenta `contracts`.

**Datos bancarios:** nombre del banco, cuenta corriente, cuenta de ahorros. → para el pago del neto.

**Datos del cónyuge/concubino** y **datos de hijos** (apellidos y nombres, N° doc/DNI, edad,
sexo, fecha de nacimiento, otro domicilio). → tabla `derechohabientes`.

> ⭐ **Regla clave:** los **datos de hijos** determinan la **asignación familiar** (columna Q de
> la planilla = 10% RMV = S/ 113.00 vigente 2026): aplica si el trabajador tiene hijos menores de 18 años
> (o hasta 24 si estudian estudios superiores). Se deriva automáticamente de `derechohabientes`.

**Contacto de emergencia:** apellidos y nombres, teléfono, parentesco.

**Datos del área usuaria (control interno):** gerencia, RRHH, tipo de remuneración, seleccionado
por, puesto nuevo / reemplazo. → metadatos de contratación (opcional).

---

## 8. Modelo de datos propuesto (Laravel)

**Maestros versionados por periodo (clave para no romper cálculos históricos):**
- `parametros_periodo` (uit, rmv, asignacion_familiar, dias_base=30)
- `tasas_afp` (afp, tipo[mixta/sueldo], comision_flujo, comision_saldo, prima_seguro, aporte_obligatorio, rem_max, vigente_desde)
- `tasas_aportes` (essalud=0.09, senati, vigente_desde)
- `polizas_sctr` (aseguradora, actividad_riesgo, tasa_salud, tasa_pension, vigente_desde) — NO tasa universal
- `polizas_vida_ley` (aseguradora, prima/tasa, base, vigente_desde) — NO 0.54% universal
- `tramos_renta_5ta` (orden, tope_uit, tasa, vigente_desde)

**Operativos:**
- `empresas`, `empleados`, `contratos` (tipo, fecha_ingreso, fecha_cese, sistema_pensiones, afp, tipo_afp, afecto_onp, sctr, senati, categoria)
- `empleados` incluye además (de la Ficha de Registro): estado_civil, lugar_nacimiento, profesion, ruc, telefono, direccion (via/distrito/provincia/departamento), correo, tipo_vivienda, nivel_educativo, banco, cuenta_corriente, cuenta_ahorros, contacto_emergencia (nombre/telefono/parentesco)
- `derechohabientes` (empleado_id, tipo[hijo|conyuge|concubino], nombres, dni, fecha_nacimiento, sexo, estudia) — base para asignación familiar y ESSALUD
- `conceptos` (catálogo: tipo = ingreso/descuento/aporte_empleador, fórmula/estrategia, afecta_base_pension, afecta_essalud)
- `periodos` (mes, quincena, estado[borrador/cerrado], fecha_pago)
- `asistencias` (empleado_id, fecha, estado, minutos_tarde, horas_extra)
- `planillas` (periodo_id, estado) y `planilla_detalle` (empleado_id, concepto_id, cantidad, monto) — formato largo, no 62 columnas
- `recibos_honorarios` (4ta categoría)

**Motor de cálculo:**
- `CalculadoraPlanillaService` orquesta; cada concepto = una clase Strategy testeable.
- Suite de tests que reproduzca **fila por fila** el Excel actual (ej. el trabajador
  CISNEROS: básico 4500, 7 faltas → desc. 1050, AJ=1200, AFP INTEGRA MIXTA → AY=16.44,
  AW=120, BA=136.44, BC=1138.56, ESSALUD=108, SCTR=25.68, SVL=24.30, BJ=1138.56).

---

## 9. Riesgos y consideraciones

1. **Las tasas cambian por periodo** (SBS publica comisiones AFP, UIT anual, RMV). Todo
   debe ser versionado por fecha de devengue — el Excel ya las fecha.
2. **Base afecta ≠ total**: AFP/ONP/ESSALUD se calculan sobre `AJ` (sin movilidad ni
   adicionales). Respetar esa separación.
3. **Prorrateo siempre /30**, no por días reales del mes (regla laboral peruana).
4. **Redondeos**: ESSALUD usa `ROUND(...,2)`. Definir política de redondeo por concepto.
5. **Tope de remuneración asegurable** para prima de seguro AFP (9,665.33).
6. **Validación cruzada obligatoria** contra el Excel antes de dar por bueno el motor.

---

## 9.1 Auditoría del Excel — fallas detectadas y regla correcta del sistema

> ⚠️ **El Excel NO es la fuente de verdad de los valores.** Tiene errores. El sistema
> implementa las reglas **legalmente correctas**; el Excel solo sirve de referencia de
> estructura. Los golden tests se basan en cálculos correctos a mano, NO en copiar el Excel.

| # | Falla detectada en el Excel | Regla correcta en el sistema |
|---|------------------------------|------------------------------|
| 1 | **Horas extras** se pagan por *cantidad de días con HE*, no por minutos/horas reales | Calcular desde **minutos reales del biométrico**, con recargo legal **+25%** (primeras 2 h) y **+35%** (desde la 3ra h) sobre el valor hora |
| 2 | **AFP/ONP, EsSalud, SCTR** se calculan solo sobre la remuneración quincenal; **omiten** horas extras y trabajo en sábado | La base de aportes se arma sumando **todos los conceptos remunerativos afectos** (ver §9.2), incluidas HE y sobretiempo |
| 3 | Resúmenes de asistencia **digitados a mano** | Todo **calculado** desde `marcaciones`; sin digitación |
| 4 | Biometría sin trazabilidad: horarios repetidos (07:23–18:00, 08:00–18:00), sin reporte original ni ID de marcación ni historial de correcciones | `marcaciones` cruda **inmutable** con `marcacion_id` y `raw_payload`; toda corrección vía `incidencias` + `audit_logs` |
| 5 | Fórmulas rotas en días 14 y 15 de ASISTENCIA | No hay fórmulas de celda; lógica en código testeado |
| 6 | Seis `#VALUE!` en ASISTENCIA | Imposible: validación de entrada y cálculo en servidor |
| 7 | TASAS con devengue **2020** y `TAB_AFP` con `#REF!` | Tasas **versionadas por periodo** (2026 vigente), sin referencias rotas |
| 8 | Quinta categoría vacía y **no vinculada** a trabajadores | `Renta5taService` por trabajador, automático en cada planilla |
| 9 | Un trabajador **sin AFP/ONP**; otro solo como "PRIMA" sin flujo/mixta | **Validación obligatoria**: no se cierra planilla sin régimen; AFP exige **flujo o mixta** |
| 10 | Descuento por tardanza **mezcla** sueldo + asignación + movilidad | Cada descuento usa la **base correcta según naturaleza** del concepto (movilidad = condición de trabajo, normalmente NO entra) |

## 9.2 Naturaleza remunerativa de los conceptos (núcleo de la exactitud)

Cada concepto del catálogo lleva banderas que determinan en qué bases entra. La base de
cada cálculo se arma sumando **solo los conceptos marcados como afectos**, en lugar del
"AJ" rígido del Excel.

| Concepto | Remunerativo | Afecto AFP/ONP | Afecto EsSalud | Afecto SCTR | Afecto 5ta | Base descuento tardanza/falta |
|----------|:---:|:---:|:---:|:---:|:---:|:---:|
| Sueldo básico | sí | sí | sí | sí | sí | sí |
| Asignación familiar | sí | sí | sí | sí | sí | a definir |
| Horas extras | sí | sí | sí | sí | sí | n/a |
| Trabajo sábado/domingo/feriado | sí | sí | sí | sí | sí | n/a |
| Comisiones / incentivo producción | sí | sí | sí | sí | sí | n/a |
| Movilidad (supeditada a asistencia) | **NO** | no | no | no | no | no |
| Subsidios (DM, maternidad) | no | no | no | no | no | n/a |
| Gratificación (Fiestas Patrias/Navidad) | régimen especial | **no** | **no** | no | sí | n/a (paga BonExtra 9% al trabajador) |
| CTS | no | no | no | no | no | n/a |

> Pendiente de confirmar con RRHH/contador la naturaleza exacta de: asignación familiar en
> la base de descuento, e incentivos por producción (si son regulares → remunerativos).

## 9.3 Cálculo correcto de horas extras (reemplaza fórmula `T/30/10`)

```
valor_hora        = remuneración_mensual_computable / 30 / horas_jornada (8)
HE primeras 2h    = valor_hora × 1.25 × horas
HE desde la 3ra h = valor_hora × 1.35 × horas
```
Las horas provienen de `marcaciones` (salida real − salida esperada del turno), no de un conteo de días.

## 9.4 SCTR y Seguro Vida Ley — por póliza, riesgo y vigencia (no tasas universales)

> ⚠️ El Excel usa SCTR 2.14% y Vida Ley 0.54% como **tasas fijas universales**. Es incorrecto.

- **SCTR (Seguro Complementario de Trabajo de Riesgo):** la tasa depende de la **póliza
  contratada** (aseguradora/EPS), el **nivel de riesgo de la actividad** y su **vigencia**.
  Tiene componente **Salud** y componente **Pensión**, con tasas distintas. Solo aplica a
  trabajadores en actividades de riesgo.
  → Tabla `polizas_sctr` (aseguradora, tasa_salud, tasa_pension, actividad/riesgo, vigencia).
- **Seguro Vida Ley (DL 688):** prima según **póliza** y base (remuneración asegurable), no un
  0.54% universal. Obligatorio desde el primer día de trabajo (Ley 29549).
  → Tabla `polizas_vida_ley` (aseguradora, tasa/prima, base, vigencia).

El cálculo toma la póliza vigente asignada al trabajador/empresa en el periodo, no una constante.

## 9.5 Días trabajados / subsidiados / licencia (reemplaza `15 − faltas`)

> ⚠️ El Excel usa `días = 15 − faltas`, que ignora subsidios (DM), licencias y vacaciones.

Modelo correcto por trabajador y periodo, derivado de `attendance`:
```
días_calendario_periodo (15 en quincena)
 = días_efectivos_laborados      (asistió y trabajó)
 + días_subsidiados              (DM, maternidad — los paga EsSalud, no el empleador)
 + días_licencia_con_goce        (pagados)
 + días_licencia_sin_goce        (no pagados)
 + días_vacaciones               (pagados, base distinta)
 + días_falta_injustificada      (descuento)
 + días_falta_justificada
 + descansos/feriados            (no descuentan)
```
Cada categoría afecta de forma distinta la remuneración y las bases. El "día trabajado" para
remuneración ≠ "15 − faltas". Definir el desglose con RRHH/contador.

---

## 10. Casos de validación (golden tests) — cálculo correcto (no copia del Excel)

| Trabajador | Básico | Días trab. | Faltas | AJ | Sistema | Retención | Neto (BJ) |
|------------|--------|-----------|--------|-----|---------|-----------|-----------|
| CISNEROS PACOTAYPE KEV | 4500 | 8 | 7 | 1200 | INTEGRA MIXTA | 136.44 | 1138.56 |

> ⚠️ Recalcular cada caso con las **reglas correctas** (§9.1–9.3) antes de fijarlo como
> golden test. El valor del Excel para CISNEROS (neto 1138.56) puede contener los errores
> auditados (HE por días, base de aportes incompleta); usar solo tras verificación manual.
