# CW Invoice Mutability for WHMCS 9.x

Free WHMCS addon created by **Codigoweb.dev** to help administrators manage WHMCS 9 invoice immutability without manually editing files.

WHMCS 9 introduced invoice immutability for non-draft invoices. This module provides a practical admin interface for the current official compatibility flag, lets you optionally hide the warning banner, and includes an advanced audited workflow to convert eligible internal `Unpaid` invoices back to `Draft`.

> This project is community-oriented, free to use, and not affiliated with WHMCS.

## Donations

If this module helps you or your business, you can support continued development with a PayPal donation:

**https://paypal.me/hostingsupremo**

## Main features

- Enable or disable WHMCS 9 invoice editing without manually editing `configuration.php`.
- Automatically adds or removes the current official WHMCS compatibility flag:

```php
$allow_adminarea_invoice_mutation = true;
```

- Creates a backup of `configuration.php` before writing changes.
- Shows read/write status for `configuration.php`.
- Optionally hides the WHMCS admin warning banner about invoice immutability being disabled.
- Adds an advanced emergency mode to convert eligible `Unpaid` invoices to `Draft`.
- Saves an audit snapshot before changing invoice status.
- Logs invoice, invoice items, transactions, admin ID, IP address, reason, and timestamp.
- Adds a helper button in the admin invoice view when the advanced Draft mode is enabled.
- Allows restoring only the previous status when a valid conversion log exists.

## Why this module exists

WHMCS 9 changed the previous invoice editing workflow. WHMCS recommends using credit/debit notes and preserving accounting traceability for already-issued invoices. However, some WHMCS administrators still need a controlled way to manage internal invoices before payment or before external fiscal authorization.

This module does **not** try to bypass proper accounting rules. It gives administrators a safer interface, backups, validations, confirmations, and logs around actions that many users were otherwise doing manually through code or direct database changes.

## Installation

### Option A: Clone directly into WHMCS

From your WHMCS installation:

```bash
cd /path/to/whmcs/modules/addons
git clone https://github.com/RicRey1988/cw-invoice-mutability-whmcs-9.x.git cw_invoice_mutability
```

The final path must be:

```text
/path/to/whmcs/modules/addons/cw_invoice_mutability/
```

### Option B: Manual upload

1. Download this repository as ZIP from GitHub.
2. Extract it.
3. Rename the extracted folder to:

```text
cw_invoice_mutability
```

4. Upload it to:

```text
/modules/addons/cw_invoice_mutability/
```

## Activation in WHMCS

1. Log in to the WHMCS Admin Area.
2. Go to:

```text
Configuration > System Settings > Addon Modules
```

3. Activate **CW Invoice Mutability**.
4. Assign access permissions to the administrator role that should use the module.
5. Go to:

```text
Addons > CW Invoice Mutability
```

6. Configure the desired options and click **Guardar y aplicar**.

## Configuration options

### Safe/current official mode

Use this mode while WHMCS still supports the compatibility flag.

- **Permitir edición de facturas publicadas/no Draft**  
  Enables invoice editing behavior.

- **Usar configuración oficial persistente en configuration.php**  
  Adds or removes the current WHMCS compatibility flag in `configuration.php`.

- **Ocultar banner de advertencia del Admin Area**  
  Hides only the warning banner that says invoice immutability is disabled.

### Advanced/emergency Draft mode

- **Permitir convertir facturas Unpaid a Draft**  
  Enables the advanced audited tool to convert eligible invoices from `Unpaid` to `Draft`.

This mode is intentionally restricted and should only be used for internal invoices that have not been paid, fiscalized, externally authorized, or synchronized with a tax/e-invoicing provider.

## Advanced Draft mode rules

The module blocks automatic conversion to `Draft` when:

- The invoice is not in `Unpaid` status.
- The invoice is already `Paid`, `Cancelled`, `Refunded`, `Collections`, or another non-eligible status.
- The invoice has transactions in `tblaccounts`.
- The invoice has a payment date.
- The invoice contains detected gateway or transaction references.
- Protected fiscal keywords are found in invoice notes, invoice number, or invoice items.

Default protected keywords:

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

You can edit these keywords from the module settings.

## Using the advanced Draft mode

1. Go to:

```text
Addons > CW Invoice Mutability
```

2. Search the invoice ID.
3. Review the validation results.
4. If the invoice is eligible, enter a required reason.
5. Type the required confirmation exactly:

```text
CONVERTIR A DRAFT
```

6. Click **Convertir factura a Draft**.

The module stores a snapshot before applying the change.

## Restoring previous status

If an invoice was converted by this module and remains in `Draft`, the module can restore the previous status using the saved conversion log.

You must type:

```text
RESTAURAR ESTADO
```

Important: this only restores the invoice status. It does **not** reverse item, amount, due date, tax, or total changes made after the invoice became `Draft`.

## Audit table

The module creates and uses this table:

```text
mod_cw_invoice_mutability_logs
```

It stores:

- Invoice ID
- Admin ID
- Action
- Old status
- New status
- Required reason
- Invoice snapshot JSON
- Invoice items snapshot JSON
- Transactions snapshot JSON
- IP address
- User agent
- Created date/time

Logs are intentionally preserved when the module is deactivated.

## Uninstall / deactivate

Before disabling the module, open the addon and click:

```text
Desactivar y remover bandera
```

Then deactivate the addon from WHMCS.

The audit logs are preserved by design.

## Important accounting and legal note

Editing published invoices can affect accounting records, tax compliance, audit trails, and payment reconciliation depending on your country and business process.

For Ecuador/SRI or any other e-invoicing/tax authority flow: do **not** use Draft conversion on invoices already authorized, fiscalized, reported, or synchronized externally. Use credit notes, cancellation, voiding, or re-issuance according to your local regulations and your accounting advisor.

## Compatibility

- WHMCS 9.x
- PHP versions supported by your WHMCS 9 installation
- Admin Area addon module

Always test first in a staging WHMCS installation before using it in production.

## Author

Created by **Codigoweb.dev**

- Website: https://codigoweb.dev
- Donations: https://paypal.me/hostingsupremo

## License

MIT License. See `LICENSE`.
