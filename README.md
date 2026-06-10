# CW Invoice Mutability for WHMCS 9

Addon gratuito para WHMCS 9 que permite activar la edición administrativa de facturas publicadas/no Draft sin editar manualmente `configuration.php`.

Creado por **Codigoweb.dev**.

Donaciones: https://paypal.me/hostingsupremo

## Qué hace

- Agrega o remueve automáticamente la bandera oficial:

```php
$allow_adminarea_invoice_mutation = true;
```

- Permite ocultar el banner del Admin Area que indica que la inmutabilidad de facturas está desactivada.
- Crea un backup de `configuration.php` antes de modificarlo.
- Muestra estado de lectura/escritura de `configuration.php`.
- Incluye modo avanzado opcional para convertir facturas `Unpaid` a `Draft`.
- Guarda snapshot de factura, ítems y transacciones antes de cambiar el estado.
- Registra acciones en `tblactivitylog` y en la tabla propia `mod_cw_invoice_mutability_logs`.
- Agrega un botón desde la vista de factura para revisar/convertir a Draft cuando el modo avanzado está activo.

## Modo avanzado: convertir a Draft

Esta herramienta existe porque WHMCS ha indicado que la opción de mutabilidad por `configuration.php` será eliminada en una versión futura.

El modo avanzado **no edita ítems ni totales**. Solo cambia el estado interno de una factura de `Unpaid` a `Draft`, con validaciones estrictas y auditoría.

La conversión se bloquea si:

- La factura no está en estado `Unpaid`.
- La factura ya está pagada, cancelada, reembolsada, en colección u otro estado no permitido.
- Existen transacciones asociadas en `tblaccounts`.
- Existe fecha de pago registrada.
- Se detectan referencias de transacción/pasarela en la factura.
- Aparecen palabras de protección fiscal/comprobante externo en notas, número o ítems.

Palabras protegidas predeterminadas:

```text
SRI
autorizada
autorizado
clave de acceso
comprobante electronico
comprobante electrónico
factura electronica
factura electrónica
UUID
CUFE
fiscalizada
fiscalizado
```

Puedes editarlas desde el módulo.

## Instalación

1. Sube la carpeta `cw_invoice_mutability` a:

```text
/modules/addons/cw_invoice_mutability/
```

2. En WHMCS Admin, entra a:

```text
Configuration > System Settings > Addon Modules
```

3. Activa **CW Invoice Mutability** y asigna permisos al rol administrador correspondiente.
4. Ve a:

```text
Addons > CW Invoice Mutability
```

5. Marca **Permitir edición de facturas publicadas/no Draft** si quieres usar la vía oficial actual.
6. Mantén activado **Usar configuración oficial persistente en configuration.php** mientras WHMCS lo soporte.
7. Activa **Permitir convertir facturas Unpaid a Draft** solo si quieres usar el modo avanzado.
8. Guarda y aplica.

## Uso del modo avanzado

1. Entra a **Addons > CW Invoice Mutability**.
2. Busca la factura por ID.
3. Revisa la validación.
4. Si la factura es apta, escribe un motivo.
5. Escribe exactamente:

```text
CONVERTIR A DRAFT
```

6. Presiona **Convertir factura a Draft**.

Para devolverla al estado anterior, el módulo mostrará la opción **Restaurar estado anterior** cuando detecte un log válido de conversión. Esta opción restaura solo el estado, no revierte los cambios hechos en ítems o totales después de convertir la factura a Draft.

## Advertencia

WHMCS 9 cambió el flujo de facturas para mantener trazabilidad con notas de crédito/débito. Editar facturas publicadas puede afectar auditoría, contabilidad o cumplimiento fiscal según tu país. Úsalo bajo tu responsabilidad.

Para Ecuador/SRI u otros sistemas fiscales: no uses el modo Draft en facturas ya autorizadas, fiscalizadas o sincronizadas externamente. En esos casos corresponde nota de crédito, anulación o reemisión según la normativa aplicable.

## Desinstalación

Desde el módulo, usa **Desactivar y remover bandera**. Luego puedes desactivar el addon desde WHMCS.

Los logs de auditoría se conservan intencionalmente en `mod_cw_invoice_mutability_logs`.
