# Solicitud de Información — Sistema de Planillas (MiPlanilla)

**Para:** RRHH / Contabilidad — ACS EIRL
**De:** Equipo de desarrollo del sistema
**Fecha:** _______________
**Asunto:** Información necesaria para configurar correctamente el sistema de planillas

Estimados:

Para que el sistema calcule las planillas **exactas y conforme a ley**, necesitamos que nos
confirmen la siguiente información. Marcamos con ⭐ lo más urgente (sin eso no podemos avanzar
con los cálculos). Pueden responder en este mismo documento o adjuntando archivos.

---

## 1. Parámetros legales vigentes ⭐

| Dato | Valor que tenemos | Confírmenos el valor correcto |
|------|-------------------|-------------------------------|
| RMV (Remuneración Mínima Vital) 2026 | S/ 1,130 | _______________ |
| Asignación familiar (10% RMV) | S/ 113.00 | _______________ |
| UIT 2026 | (por confirmar) | _______________ |
| Días base para prorrateo | 30 | _______________ |

---

## 2. Sistema de pensiones (AFP / ONP) ⭐

- ¿Con qué **AFP** trabajan los empleados? (Integra, Prima, Profuturo, Habitat)
- Por cada trabajador en AFP, necesitamos saber si es **comisión por FLUJO** o **MIXTA**.
- ¿Tienen las **tasas vigentes** de cada AFP (aporte, comisión, prima de seguro)? Si no, las
  obtenemos de la SBS, pero confírmennos el mes de devengue.
- ¿Hay trabajadores en **ONP**? (aporte 13%)

> ⚠️ En el Excel actual hay un trabajador **sin AFP/ONP** y otro solo como "PRIMA" sin indicar
> flujo o mixta. Necesitamos el régimen completo de **cada** trabajador.

---

## 3. Seguros: SCTR y Vida Ley ⭐

**SCTR (Seguro Complementario de Trabajo de Riesgo):**
- ¿Qué **aseguradora/EPS** tienen contratada?
- Tasa de **SCTR Salud**: ______ %
- Tasa de **SCTR Pensión**: ______ %
- ¿Qué **actividades/áreas** son de riesgo (a quiénes aplica)?
- Vigencia de la póliza: desde _______ hasta _______

**Seguro Vida Ley (DL 688):**
- Aseguradora: _______________
- Prima o tasa: _______________
- ¿Sobre qué base se calcula?

---

## 4. Reloj biométrico ⭐

- **Marca y modelo** del reloj: _______________
- ¿Qué **software** usan para verlo? (ej. ZKTime, ZKBio, Hikvision, etc.)
- ¿Cómo podemos obtener los datos? (marquen lo que aplique)
  - [ ] Exportar archivo Excel/CSV/TXT
  - [ ] Acceso a su base de datos
  - [ ] El reloj tiene conexión de red / API
- ¿El reloj registra **un código por trabajador**? ¿Coincide con el DNI o es otro código?
- ¿Pueden enviarnos un **archivo de ejemplo** de marcaciones (un día o una semana real)?

---

## 5. Horarios, turnos y asistencia ⭐

- **Horario(s) de trabajo**: hora de entrada, salida y refrigerio. (Si hay varios turnos,
  indíquenos cada uno.)
- ¿Hay **turnos nocturnos** (que cruzan la medianoche)?
- **Tolerancia** de tardanza (minutos de gracia antes de descontar): ______ min
- ¿Cómo se descuenta la **tardanza** y la **falta**? ¿Sobre el sueldo básico, o también sobre
  asignación familiar y movilidad?
- ¿Los **feriados** y **descansos semanales** son los estándar o tienen calendario propio?

---

## 6. Horas extras y trabajo en días especiales ⭐

- ¿Las horas extras se pagan con el recargo de ley (**+25%** las 2 primeras horas, **+35%**
  desde la 3ra)? ¿O tienen otra política?
- ¿Las horas extras requieren **autorización previa** para pagarse? ¿Quién autoriza?
- ¿Cómo pagan el **trabajo en sábado, domingo y feriado**?
- ¿La **movilidad** es un monto fijo o depende de la asistencia? ¿Es "por condición de
  trabajo" (no remunerativa) o parte del sueldo?

---

## 7. Conceptos de la planilla

- ¿Qué **bonos / incentivos** pagan? (ej. incentivo por producción)
  - ¿Se pagan casi todos los meses (regular) o solo a veces (ocasional)?
- ¿Pagan **gratificaciones** (Fiestas Patrias y Navidad)?
- ¿Manejan **CTS** (mayo y noviembre)?
- ¿Hay **adelantos / préstamos** a trabajadores? ¿Cómo se descuentan?
- ¿Algún otro concepto de ingreso o descuento que usen?

---

## 8. Renta de 5ta categoría

- ¿Tienen trabajadores cuyo sueldo supera el monto afecto a 5ta categoría?
- Como pagan en **quincenas**, ¿la retención de 5ta la descuentan toda en la **2da quincena**
  o la dividen entre ambas?
- ¿Tienen el registro de **retenciones ya hechas en el año** (para el cálculo acumulado)?

---

## 9. Datos maestros de los trabajadores ⭐

Necesitamos la **ficha completa de cada trabajador** (pueden usar la "Ficha de Registro de
Personal" que ya manejan). Por cada uno:

- Apellidos y nombres, DNI, fecha de nacimiento, género, estado civil
- Fecha de ingreso (y cese si aplica), tipo de contrato
- Área, cargo, categoría (empleado/obrero; maestro/oficial/ayudante)
- Sueldo básico, asignación familiar, movilidad, otros
- Sistema de pensiones (AFP+tipo / ONP)
- ¿Aporta SCTR? ¿Senati?
- **Datos bancarios** (banco, tipo y número de cuenta) para el pago
- **Hijos / cónyuge** (para asignación familiar y EsSalud)
- Código del trabajador en el reloj biométrico

---

## 10. Referencias para validación

- Una o dos **planillas reales ya pagadas y correctas** (con sus boletas), para usarlas como
  casos de prueba y verificar que el sistema da los mismos números.
- El formato de **boleta de pago** que usan actualmente (para replicarlo).

---

**Cualquier duda sobre qué significa algún punto, con gusto lo explicamos.**
Mientras tanto, avanzamos con todo lo que no depende de esta información.
