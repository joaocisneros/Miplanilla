# Plan Técnico — Sistema RRHH + Planillas + Biométrico (MiPlanilla)

> Documento maestro de arquitectura. Complementa a
> [`ESPECIFICACION_PLANILLA.md`](ESPECIFICACION_PLANILLA.md) (reglas de cálculo extraídas del Excel real).
> Este documento define stack, módulos, modelo de datos, seguridad y hoja de ruta.

---

## 1. Stack tecnológico (decisiones)

| Capa | Elección | Motivo |
|------|----------|--------|
| Backend | **Laravel 12 / PHP 8.2+** | Versión instalada: Laravel 12.62. (El prompt dice "PHP 10+"; la versión actual de PHP es 8.x — Laravel 12 sobre PHP 8.2 es lo correcto). ⚠️ El PHP CLI por defecto del equipo es 8.0.30; se debe usar explícitamente el binario PHP 8.2.31 de Laragon. |
| Frontend | **Vue 3 + Inertia.js** | SPA sin construir API doble; un solo despliegue. Alternativa: Vue/React + API REST pura si se requiere desacople total. |
| Base de datos | **MySQL 8** | Disponible en Laragon; el entorno ya usa MySQL. PostgreSQL viable sin cambios de código relevantes. |
| Autenticación | **Laravel Breeze/Fortify + Sanctum** | Sesión web (Inertia) + tokens para API biométrica. |
| Roles/permisos | **spatie/laravel-permission** | Estándar de facto. |
| PDF boletas | **barryvdh/laravel-dompdf** | Boletas y reportes en PDF. |
| Excel | **maatwebsite/excel** | Import/export de asistencia y reportes. |
| Auditoría | **owen-it/laravel-auditing** | `audit_logs` automáticos por modelo. |
| Colas | **Laravel Queue (database/redis)** | Sincronización biométrica e import en background. |

---

## 2. Roles y permisos

| Rol | Capacidades |
|-----|-------------|
| **ADMIN** | Todo: usuarios, roles, configuración de reglas (horarios, tolerancias, tasas AFP/ONP, UIT, RMV), dispositivos, auditoría. |
| **RRHH** | Empleados, contratos, planillas, sincronización/import biométrico, justificación de incidencias, boletas, reportes. |
| **SUPERVISOR** | Ver asistencia del personal a su cargo, registrar/solicitar justificación de tardanzas e incidencias, validar asistencia. |
| **EMPLEADO** (opcional) | Ver su asistencia, descargar boletas, solicitar correcciones. |

Implementación con Policies + `spatie/laravel-permission`. Permisos granulares
(ej. `payroll.create`, `attendance.justify`, `device.sync`, `audit.view`).

---

## 3. Arquitectura de módulos

```
app/
├── Domain/
│   ├── Empleados/        (Employee, Contract, servicios)
│   ├── Asistencia/       (Marcacion, Asistencia, Turno, ImportarMarcaciones, ProcesarAsistencia)
│   ├── Planilla/         (Periodo, Planilla, PlanillaDetalle, CalculadoraPlanillaService, Conceptos/*)
│   ├── Pensiones/        (TasaAfp, calculadora AFP/ONP)
│   ├── Tributario/       (Renta5taService, tramos UIT)
│   ├── Boletas/          (BoletaPdfService)
│   └── Biometrico/       (Device, conectores ZKTeco/CSV/API)
├── Http/ (Controllers, Requests, Resources)
└── Policies/
```

**Patrón clave:** cada concepto de planilla es una **Strategy** testeable
(`CalculaDescuentoFalta`, `CalculaAfp`, `CalculaRenta5ta`, ...), orquestada por
`CalculadoraPlanillaService`. Las tasas vienen de **maestros versionados por periodo**.

---

## 4. Modelo de datos (esquema)

### 4.1 Usuarios / seguridad
- `users` (id, name, email, password, employee_id?, activo)
- `roles`, `permissions`, `model_has_roles` (spatie)
- `audit_logs` (auditable_type, auditable_id, event, old_values, new_values, user_id, ip, created_at)

### 4.2 Maestros versionados (críticos para cálculo histórico)
- `parametros_periodo` (anio, uit, rmv, asignacion_familiar, dias_base=30, vigente_desde)
- `tasas_afp` (afp, tipo[mixta|sueldo], comision_flujo, comision_saldo, prima_seguro, aporte_obligatorio=0.10, rem_max, vigente_desde)
- `tasas_aportes` (essalud=0.09, senati, vigente_desde)
- `polizas_sctr` (aseguradora, actividad_riesgo, tasa_salud, tasa_pension, vigente_desde) — por póliza, NO universal
- `polizas_vida_ley` (aseguradora, prima/tasa, base, vigente_desde) — por póliza, NO 0.54% universal
- `parametros_periodo` (anio, uit, rmv=1130(2026), asignacion_familiar=113, dias_base=30, vigente_desde)
- `retenciones_5ta` (employee_id, anio, mes, monto_retenido) — estado acumulado para 5ta categoría SUNAT
- `tramos_renta_5ta` (orden, hasta_uit, tasa, vigente_desde)

### 4.3 Empleados
- `employees` (id, dni, codigo_biometrico, apellidos, nombres, fecha_nacimiento, genero,
  fecha_ingreso, fecha_cese, area_id, cargo_id, supervisor_id)
- `contracts` (employee_id, tipo, sueldo_basico, asignacion_familiar, movilidad, por_fuera,
  sistema_pensiones[ONP|AFP], afp, tipo_afp, afecto_onp, sctr, senati, categoria_ocupacional,
  vigente_desde, vigente_hasta)
- `areas`, `cargos`
- `turnos` (nombre, hora_entrada, hora_salida, refrigerio_min, tolerancia_min, minutos_esperados)
- `employee_turno` (asignación de turno por empleado/vigencia)

### 4.4 Asistencia (biométrico)
- `devices` (nombre, marca, modelo, tipo_conexion[db|csv|api], config_json, ultima_sync, activo)
- `marcaciones` (employee_id, device_id, fecha_hora, tipo[entrada|salida], origen, raw_payload) — **inmutable**
- `attendance` (employee_id, fecha, turno_id, estado[NORMAL|FALTA|VACACIONES|LICENCIA|JUSTIFICADO|...],
  hora_entrada_real, hora_salida_real, minutos_tarde, horas_extra, derivado_de_marcacion, observacion)
- `incidencias` (attendance_id, tipo, motivo, solicitado_por, justificado_por, estado, adjunto)

### 4.5 Planilla
- `periodos` (anio, mes, quincena[1|2|null=mensual], fecha_pago, estado[borrador|cerrado])
- `conceptos` (codigo, nombre, tipo[ingreso|descuento|aporte_empleador], afecta_base_pension,
  afecta_essalud, formula_strategy)
- `payrolls` (periodo_id, empresa_id, estado, total_neto, total_aportes, generado_por, cerrado_at)
- `payroll_details` (payroll_id, employee_id, concepto_id, cantidad, monto) — formato **largo**
- `payroll_resumen` (vista materializada por empleado: AJ, AR, BA, BC, BD, BE, BG, BJ)

### 4.6 Boletas
- `boletas` (payroll_id, employee_id, pdf_path, hash, generada_at) + historial por empleado.

> Regla del prompt cumplida: `marcaciones` y `attendance` derivada **no se editan a mano**;
> cualquier corrección pasa por `incidencias` (justificación de RRHH) y queda en `audit_logs`.

---

## 5. Integración biométrica

Conector abstracto `BiometricoConnector` con 3 implementaciones:

1. **API REST** — endpoint `POST /api/biometrico/marcaciones` (auth Sanctum + firma/token de
   dispositivo) que recibe push del reloj o de un middleware. Valida y encola a `marcaciones`.
2. **Import CSV/Excel** — `maatwebsite/excel`, mapeo de columnas configurable por dispositivo.
3. **Base de datos compartida** — lectura directa de la BD del software del reloj
   (típico ZKTeco: SQL Server/Access), job programado de sincronización.

Pipeline:
```
[Reloj] → marcaciones (crudo, inmutable)
        → ProcesarAsistenciaJob (cruza marcas + turno + tolerancia)
        → attendance (estado/tardanza/horas extra derivados)
        → incidencias (justificaciones manuales superpuestas)
        → insumo del cálculo de planilla
```
Sincronización **manual** (botón RRHH) y **automática** (scheduler).

> Marca/modelo del reloj: **por confirmar**. El conector queda abstraído para no bloquear
> el desarrollo; cuando se confirme se implementa el driver específico.

---

## 6. Cálculo de planilla

Toda la lógica de cálculo está especificada en
[`ESPECIFICACION_PLANILLA.md`](ESPECIFICACION_PLANILLA.md) §4–§7. Resumen del motor:

```
AJ (base afecta) = (básico+asigfam)/30 × días − desc.tardanza + subsidios + extras no-movil
AR (adicionales) = movilidad prorrateada + sábados + h.extra + domingos/feriados + incentivos
Retención pensión = ONP 13% | AFP (10% fondo + comisión + prima seguro)
Renta 5ta = escala progresiva sobre proyección anual − 7 UIT, /12
Neto = AJ + AR − retención pensión − renta 5ta − adelantos + reintegros
Aportes empleador = ESSALUD 9% + SCTR pensión 2.14% + SVL 0.54% + Senati
```

**Validación obligatoria:** golden tests que reproduzcan el Excel fila por fila
(ver §10 de la especificación).

---

## 7. Boletas y reportes

- **Boletas PDF** (dompdf): plantilla con datos del trabajador, conceptos de ingreso,
  descuentos, aportes y neto; hash de integridad; historial por empleado.
- **Reportes**: planilla general, costo total por mes/empresa, asistencia, exportables a
  Excel y PDF.

---

## 8. Seguridad y auditoría

- `marcaciones` y `attendance` derivada **inmutables** (sin endpoints de edición directa).
- Correcciones solo vía `incidencias` justificadas por RRHH.
- `audit_logs` automático en todos los modelos sensibles (laravel-auditing).
- Autorización por Policy + permisos granulares.
- Periodos de planilla **cerrables** (estado `cerrado` bloquea recálculo).
- Validación de entrada (FormRequests), rate limiting en API biométrica.

---

## 9. Hoja de ruta por fases

| Fase | Entregable | Depende de |
|------|-----------|------------|
| **0. Scaffold** | Proyecto Laravel + Vue/Inertia, auth, roles, migraciones base, seeders de maestros (tasas, UIT, RMV) | — |
| **1. Empleados** | CRUD empleados, contratos, áreas/cargos, turnos | 0 |
| **2. Motor de cálculo** | Servicios de cálculo + **golden tests vs Excel** (sin UI) | 0 |
| **3. Planilla** | Generación de planilla quincenal/mensual, detalle, neto | 1, 2 |
| **4. Asistencia manual** | Carga CSV/Excel + derivación de asistencia + incidencias | 1 |
| **5. Biométrico** | Conector real (según marca confirmada) + sync auto | 4 |
| **6. Boletas** | PDF + historial | 3 |
| **7. Reportes** | Reportes + export Excel/PDF | 3, 4 |
| **8. Portal empleado** | Vista de asistencia y descarga de boletas | 6 |

> Recomendación: **Fase 2 (motor + golden tests) es la de mayor riesgo** y debe validarse
> contra el Excel antes de construir UI encima. Es donde se juega la confiabilidad del sistema.

---

## 10. Cumplimiento del prompt (checklist)

- [x] Backend Laravel / Frontend Vue / BD MySQL — definido §1
- [x] API REST para biométrico — §5
- [x] PDF de boletas — §7
- [x] 4 roles con permisos — §2
- [x] Asistencia inmutable + solo auditoría — §4.4, §8
- [x] Cálculo automático AFP/ONP/tardanzas/faltas/h.extra/bonos — §6 + especificación
- [x] Planilla quincenal y mensual + resumen + neto — §4.5, §6
- [x] Tablas users/roles/employees/attendance/payrolls/payroll_details/devices/audit_logs — §4
- [x] Solo RRHH justifica incidencias — §2, §4.4
- [x] Todo cambio en audit_logs — §8
- [x] Multiusuario con permisos — §2
- [x] Integración API / CSV / BD compartida + sync auto/manual — §5
