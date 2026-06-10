# CW Invoice Mutability for WHMCS 9.x

Free WHMCS addon created by **Codigoweb.dev** to help administrators manage WHMCS 9 invoice immutability without manually editing files.

WHMCS 9 introduced invoice immutability for non-Draft invoices. This module provides a practical admin interface for the current official compatibility flag, lets you optionally hide the warning banner, and includes an advanced audited workflow to convert eligible internal `Unpaid` invoices back to `Draft`.

> This project is community-oriented, free to use, and not affiliated with WHMCS.

## Donations

If this module helps you or your business, you can support continued development with a PayPal donation:

**https://paypal.me/hostingsupremo**

## Folder name requirement

WHMCS addon modules require the folder name and the main PHP file name to match the module system name.

For this addon, the final folder must be exactly:

```text
cw_invoice_mutability
```

The final structure must be:

```text
WHMCS_ROOT/
└── modules/
    └── addons/
        └── cw_invoice_mutability/
            ├── cw_invoice_mutability.php
            ├── hooks.php
            ├── README.md
            └── lib/
                └── CwInvoiceMutabilityTools.php
```

If the folder is named differently, WHMCS may not detect the addon correctly.

## Main features

- Enable or disable WHMCS 9 invoice editing without manually editing `configuration.php`.
- Automatically adds or removes the current official WHMCS compatibility flag:

```php
$allow_adminarea_invoice_mutation = true;
```

- Creates a backup of `configuration.php` before writing changes.
- Shows read/write status for `configuration.php`.
- Optionally hides the WHMCS Admin Area warning banner about invoice immutability being disabled.
- Adds an advanced emergency mode to convert eligible `Unpaid` invoices to `Draft`.
- Saves an audit snapshot before changing invoice status.
- Logs invoice, invoice items, transactions, admin ID, IP address, reason, and timestamp.
- Adds a helper button in the admin invoice view when the advanced Draft mode is enabled.
- Allows restoring only the previous status when a valid conversion log exists.

## Installation

### Option A: Clone directly into WHMCS

Go to your WHMCS addon modules directory:

```bash
cd /path/to/whmcs/modules/addons
```

Clone the repository and force the folder name to `cw_invoice_mutability`:

```bash
git clone https://github.com/RicRey1988/cw-invoice-mutability-whmcs-9.x.git cw_invoice_mutability
```

After cloning, this file must exist:

```text
/path/to/whmcs/modules/addons/cw_invoice_mutability/cw_invoice_mutability.php
```

### Option B: Download ZIP from GitHub

1. Click **Code > Download ZIP** in GitHub.
2. Extract the ZIP on your computer.
3. Rename the extracted folder to:

```text
cw_invoice_mutability
```

4. Upload that folder to:

```text
/path/to/whmcs/modules/addons/
```

Final result:

```text
/path/to/whmcs/modules/addons/cw_invoice_mutability/
```

### Option C: Use the packaged WHMCS-root ZIP

Some release ZIP files may already include this path:

```text
modules/addons/cw_invoice_mutability/
```

In that case, extract the ZIP directly into the WHMCS root directory. The files will land in the correct addon folder automatically.

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

6. Configure the desired options and click **Save and apply**.

## Configuration options

### Safe mode / current official method

Use this mode while WHMCS still supports the compatibility flag.

- **Allow editing published/non-Draft invoices**  
  Enables invoice editing behavior.

- **Use official persistent configuration in configuration.php**  
  Adds or removes the current WHMCS compatibility flag in `configuration.php`.

- **Hide Admin Area warning banner**  
  Hides only the warning banner that says invoice immutability is disabled.

### Advanced / emergency Draft mode

- **Allow converting Unpaid invoices to Draft**  
  Enables the advanced audited tool to convert eligible invoices from `Unpaid` to `Draft`.

This mode is intentionally restricted and should only be used for internal invoices that have not been paid, fiscalized, externally authorized, or synchronized with a tax/e-invoicing provider.

## Advanced Draft mode rules

The module blocks automatic conversion to `Draft` when:

- The invoice is not in `Unpaid` status.
- The invoice is already `Paid`, `Cancelled`, `Refunded`, `Collections`, or another non-eligible status.
- The invoice has transactions in `tblaccounts`.
- The invoice has a payment date.
- The invoice contains detected gateway or transaction references.
- Protected fiscal/external-document keywords are found in invoice notes, invoice number, or invoice items.

Default protected keywords include both English and Spanish/e-invoicing terms, for example:

```text
SRI
authorized
authorization
access key
electronic invoice
e-invoice
tax authority
fiscalized
external invoice
invoice authorization
UUID
CUFE
autorizada
autorizado
clave de acceso
comprobante electronico
comprobante electrónico
factura electronica
factura electrónica
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
CONVERT TO DRAFT
```

6. Click **Convert invoice to Draft**.

The module stores a snapshot before applying the change.

## Restoring previous status

If an invoice was converted by this module and remains in `Draft`, the module can restore the previous status using the saved conversion log.

You must type:

```text
RESTORE STATUS
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
Disable and remove flag
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
