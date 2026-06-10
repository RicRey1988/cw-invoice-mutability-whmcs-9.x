<?php
/**
 * CW Invoice Mutability
 * WHMCS addon module to toggle invoice mutability without manually editing code.
 * Created by Codigoweb.dev
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/CwInvoiceMutabilityTools.php';

function cw_invoice_mutability_config()
{
    return [
        'name' => 'CW Invoice Mutability',
        'description' => 'Allows WHMCS 9 administrators to manage invoice mutability without manually editing configuration.php, optionally hide the warning banner, and convert eligible Unpaid invoices to Draft with audit logging. Created by Codigoweb.dev. Donations: https://paypal.me/hostingsupremo',
        'version' => '1.1.0',
        'author' => '<a href="https://codigoweb.dev" target="_blank" rel="noopener">Codigoweb.dev</a>',
        'language' => 'english',
        'fields' => [
            'enable_mutation' => [
                'FriendlyName' => 'Allow invoice editing',
                'Type' => 'yesno',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Enables mutability for published/non-Draft invoices in the admin area.',
            ],
            'write_configuration' => [
                'FriendlyName' => 'Use official persistent configuration',
                'Type' => 'yesno',
                'Size' => '25',
                'Default' => 'on',
                'Description' => 'Automatically adds or removes $allow_adminarea_invoice_mutation = true; in configuration.php. This is the official WHMCS compatibility path while it remains available.',
            ],
            'hide_banner' => [
                'FriendlyName' => 'Hide warning banner',
                'Type' => 'yesno',
                'Size' => '25',
                'Default' => 'on',
                'Description' => 'Only hides the Admin Area warning banner that says invoice immutability is disabled.',
            ],
            'enable_draft_tools' => [
                'FriendlyName' => 'Advanced mode: convert to Draft',
                'Type' => 'yesno',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Allows eligible Unpaid invoices without transactions to be converted to Draft with snapshot and audit log. Use only for internal/non-fiscalized cases.',
            ],
            'draft_guard_keywords' => [
                'FriendlyName' => 'Fiscal protection keywords',
                'Type' => 'textarea',
                'Rows' => '6',
                'Cols' => '70',
                'Default' => CwInvoiceMutabilityTools::defaultGuardKeywords(),
                'Description' => 'If these words appear in invoice notes, invoice number, or invoice items, the module blocks Draft conversion.',
            ],
        ],
    ];
}

function cw_invoice_mutability_activate()
{
    try {
        CwInvoiceMutabilityTools::ensureLogTable();

        if (CwInvoiceMutabilityTools::setting('write_configuration', null) === null) {
            CwInvoiceMutabilityTools::setSetting('write_configuration', 'on');
        }
        if (CwInvoiceMutabilityTools::setting('hide_banner', null) === null) {
            CwInvoiceMutabilityTools::setSetting('hide_banner', 'on');
        }
        if (CwInvoiceMutabilityTools::setting('enable_draft_tools', null) === null) {
            CwInvoiceMutabilityTools::setSetting('enable_draft_tools', '');
        }
        if (CwInvoiceMutabilityTools::setting('draft_guard_keywords', null) === null) {
            CwInvoiceMutabilityTools::setSetting('draft_guard_keywords', CwInvoiceMutabilityTools::defaultGuardKeywords());
        }

        logActivity('CW Invoice Mutability activated');

        return [
            'status' => 'success',
            'description' => 'CW Invoice Mutability activated. Open the addon module to enable invoice editing and, if needed, the advanced Draft mode.',
        ];
    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'description' => 'Activation error: ' . $e->getMessage(),
        ];
    }
}

function cw_invoice_mutability_deactivate()
{
    try {
        // Be conservative: remove the official flag when the addon is deactivated.
        CwInvoiceMutabilityTools::syncConfigurationFlag(false);
        logActivity('CW Invoice Mutability deactivated');

        return [
            'status' => 'success',
            'description' => 'CW Invoice Mutability deactivated. The official flag was removed if present. Audit logs are preserved.',
        ];
    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'description' => 'Deactivation error: ' . $e->getMessage(),
        ];
    }
}

function cw_invoice_mutability_upgrade($vars)
{
    try {
        CwInvoiceMutabilityTools::ensureLogTable();
        if (CwInvoiceMutabilityTools::setting('draft_guard_keywords', null) === null) {
            CwInvoiceMutabilityTools::setSetting('draft_guard_keywords', CwInvoiceMutabilityTools::defaultGuardKeywords());
        }
        logActivity('CW Invoice Mutability upgraded to ' . ($vars['version'] ?? 'unknown'));
    } catch (Throwable $e) {
        logActivity('CW Invoice Mutability upgrade error: ' . $e->getMessage());
    }
}

function cw_invoice_mutability_renderInvoiceSearch(string $moduleLink, string $nonce, int $selectedInvoiceId): void
{
    echo '<div class="panel panel-default" id="cwim-draft-tools">';
    echo '<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-edit"></i> Advanced mode: convert invoice to Draft</h3></div>';
    echo '<div class="panel-body">';

    echo '<div class="alert alert-danger"><strong>Sensitive operation:</strong> this tool changes the internal invoice status directly in the database. It is only allowed for <strong>Unpaid</strong> invoices without transactions and without fiscal protection keyword matches. Do not use it for paid, tax-authorized, fiscalized, or externally synchronized invoices.</div>';

    echo '<form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom:15px;">';
    echo '<input type="hidden" name="module" value="cw_invoice_mutability">';
    echo '<div class="form-group"><label for="cwim_invoice_id">Invoice #</label> ';
    echo '<input type="number" min="1" class="form-control" id="cwim_invoice_id" name="invoice_id" value="' . ($selectedInvoiceId > 0 ? CwInvoiceMutabilityTools::html((string) $selectedInvoiceId) : '') . '" placeholder="e.g. 1234"></div> ';
    echo '<button type="submit" class="btn btn-default"><i class="fa fa-search"></i> Review invoice</button>';
    echo '</form>';

    if ($selectedInvoiceId <= 0) {
        echo '<p class="text-muted">Enter an invoice ID to check whether it can be converted to Draft.</p>';
        echo '</div></div>';
        return;
    }

    $assessment = CwInvoiceMutabilityTools::assessInvoiceForDraft($selectedInvoiceId);

    if (!$assessment['exists']) {
        echo '<div class="alert alert-danger">' . CwInvoiceMutabilityTools::html(implode(' ', $assessment['blocks'])) . '</div>';
        echo '</div></div>';
        return;
    }

    $invoice = $assessment['invoice_data'];
    $clientId = (int) ($invoice['userid'] ?? 0);
    $clientLabel = CwInvoiceMutabilityTools::getClientSummary($clientId);
    $status = (string) ($invoice['status'] ?? '');
    $total = isset($invoice['total']) ? number_format((float) $invoice['total'], 2) : '';
    $balance = isset($invoice['balance']) ? number_format((float) $invoice['balance'], 2) : '';

    echo '<h4>Invoice #' . CwInvoiceMutabilityTools::html((string) $selectedInvoiceId) . '</h4>';
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<table class="table table-striped table-condensed"><tbody>';
    echo '<tr><th>Status</th><td><span class="label label-' . ($status === 'Draft' ? 'info' : ($status === 'Unpaid' ? 'warning' : 'default')) . '">' . CwInvoiceMutabilityTools::html($status) . '</span></td></tr>';
    echo '<tr><th>Client</th><td>' . CwInvoiceMutabilityTools::html($clientLabel) . '</td></tr>';
    echo '<tr><th>Total / Balance</th><td>' . CwInvoiceMutabilityTools::html($total) . ' / ' . CwInvoiceMutabilityTools::html($balance) . '</td></tr>';
    echo '<tr><th>Date / Due Date</th><td>' . CwInvoiceMutabilityTools::html((string) ($invoice['date'] ?? '')) . ' / ' . CwInvoiceMutabilityTools::html((string) ($invoice['duedate'] ?? '')) . '</td></tr>';
    echo '<tr><th>Payment Date</th><td>' . CwInvoiceMutabilityTools::html((string) ($invoice['datepaid'] ?? '')) . '</td></tr>';
    echo '<tr><th>Items / Transactions</th><td>' . count($assessment['items']) . ' / ' . count($assessment['transactions']) . '</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="col-md-6">';
    if ($assessment['allowed']) {
        echo '<div class="alert alert-success"><strong>Validation:</strong> this invoice can be converted to Draft under the current rules.</div>';
    } else {
        echo '<div class="alert alert-warning"><strong>Validation:</strong> this invoice cannot be converted automatically.</div>';
        echo '<ul>';
        foreach ($assessment['blocks'] as $block) {
            echo '<li>' . CwInvoiceMutabilityTools::html($block) . '</li>';
        }
        echo '</ul>';
    }

    if (!empty($assessment['warnings'])) {
        echo '<p><strong>Warnings:</strong></p><ul>';
        foreach ($assessment['warnings'] as $warning) {
            echo '<li>' . CwInvoiceMutabilityTools::html($warning) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
    echo '</div>';

    if (!CwInvoiceMutabilityTools::enabled('enable_draft_tools', false)) {
        echo '<div class="alert alert-info">Advanced mode is disabled. Enable it in the settings above and save before converting invoices to Draft.</div>';
    } elseif ($assessment['allowed']) {
        echo '<form method="post" action="' . CwInvoiceMutabilityTools::html($moduleLink) . '#cwim-draft-tools" onsubmit="return confirm(\'This action will convert the invoice to Draft. A snapshot will be saved, but this is still a sensitive change. Continue?\');">';
        echo '<input type="hidden" name="cwim_token" value="' . CwInvoiceMutabilityTools::html($nonce) . '">';
        echo '<input type="hidden" name="cwim_action" value="convert_to_draft">';
        echo '<input type="hidden" name="invoice_id" value="' . CwInvoiceMutabilityTools::html((string) $selectedInvoiceId) . '">';
        echo '<div class="form-group"><label>Required reason</label><textarea class="form-control" name="reason" rows="3" required placeholder="e.g. Internal correction before payment; invoice is not fiscalized or externally authorized."></textarea></div>';
        echo '<div class="form-group"><label>Confirmation</label><input type="text" class="form-control" name="confirmation" required placeholder="Type: ' . CwInvoiceMutabilityTools::CONFIRM_CONVERT . '"></div>';
        echo '<button type="submit" class="btn btn-warning"><i class="fa fa-edit"></i> Convert invoice to Draft</button>';
        echo '</form>';
    }

    if ($status === 'Draft') {
        $lastLog = CwInvoiceMutabilityTools::latestConversionLog($selectedInvoiceId);
        if ($lastLog) {
            echo '<hr><h4>Restore previous status</h4>';
            echo '<p class="text-muted">Last conversion found: log #' . CwInvoiceMutabilityTools::html((string) $lastLog->id) . ', previous status: <strong>' . CwInvoiceMutabilityTools::html((string) $lastLog->old_status) . '</strong>.</p>';
            if (CwInvoiceMutabilityTools::enabled('enable_draft_tools', false)) {
                echo '<form method="post" action="' . CwInvoiceMutabilityTools::html($moduleLink) . '#cwim-draft-tools" onsubmit="return confirm(\'This will only restore the previous invoice status; it will not revert changes made to invoice items. Continue?\');">';
                echo '<input type="hidden" name="cwim_token" value="' . CwInvoiceMutabilityTools::html($nonce) . '">';
                echo '<input type="hidden" name="cwim_action" value="restore_status">';
                echo '<input type="hidden" name="invoice_id" value="' . CwInvoiceMutabilityTools::html((string) $selectedInvoiceId) . '">';
                echo '<input type="hidden" name="log_id" value="' . CwInvoiceMutabilityTools::html((string) $lastLog->id) . '">';
                echo '<div class="form-group"><label>Required reason</label><textarea class="form-control" name="reason" rows="2" required placeholder="e.g. Editing completed, restore invoice to previous status."></textarea></div>';
                echo '<div class="form-group"><label>Confirmation</label><input type="text" class="form-control" name="confirmation" required placeholder="Type: ' . CwInvoiceMutabilityTools::CONFIRM_RESTORE . '"></div>';
                echo '<button type="submit" class="btn btn-primary"><i class="fa fa-undo"></i> Restore previous status</button>';
                echo '</form>';
            }
        }
    }

    $logs = CwInvoiceMutabilityTools::recentLogs($selectedInvoiceId, 10);
    if (!empty($logs)) {
        echo '<hr><h4>History for this invoice</h4>';
        echo '<div class="table-responsive"><table class="table table-condensed table-striped"><thead><tr><th>ID</th><th>Date</th><th>Admin</th><th>Action</th><th>Status</th><th>Reason</th></tr></thead><tbody>';
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . CwInvoiceMutabilityTools::html((string) ($log['id'] ?? '')) . '</td>';
            echo '<td>' . CwInvoiceMutabilityTools::html((string) ($log['created_at'] ?? '')) . '</td>';
            echo '<td>' . CwInvoiceMutabilityTools::html((string) ($log['admin_id'] ?? '')) . '</td>';
            echo '<td>' . CwInvoiceMutabilityTools::html((string) ($log['action'] ?? '')) . '</td>';
            echo '<td>' . CwInvoiceMutabilityTools::html((string) ($log['old_status'] ?? '')) . ' → ' . CwInvoiceMutabilityTools::html((string) ($log['new_status'] ?? '')) . '</td>';
            echo '<td>' . CwInvoiceMutabilityTools::html(substr((string) ($log['reason'] ?? ''), 0, 160)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    echo '</div></div>';
}

function cw_invoice_mutability_output($vars)
{
    $moduleLink = $vars['modulelink'];
    $nonce = CwInvoiceMutabilityTools::adminNonce();
    $messages = [];
    $selectedInvoiceId = isset($_REQUEST['invoice_id']) ? max(0, (int) $_REQUEST['invoice_id']) : 0;

    try {
        CwInvoiceMutabilityTools::ensureLogTable();
    } catch (Throwable $e) {
        $messages[] = ['danger', 'Could not prepare the audit table: ' . $e->getMessage()];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postedToken = $_POST['cwim_token'] ?? '';
        if (!CwInvoiceMutabilityTools::verifyNonce($postedToken)) {
            $messages[] = ['danger', 'Invalid security token. Reload the page and try again.'];
        } else {
            $action = $_POST['cwim_action'] ?? '';

            if ($action === 'save') {
                $enableMutation = isset($_POST['enable_mutation']) ? 'on' : '';
                $writeConfiguration = isset($_POST['write_configuration']) ? 'on' : '';
                $hideBanner = isset($_POST['hide_banner']) ? 'on' : '';
                $enableDraftTools = isset($_POST['enable_draft_tools']) ? 'on' : '';
                $guardKeywords = trim((string) ($_POST['draft_guard_keywords'] ?? CwInvoiceMutabilityTools::defaultGuardKeywords()));
                if ($guardKeywords === '') {
                    $guardKeywords = CwInvoiceMutabilityTools::defaultGuardKeywords();
                }

                CwInvoiceMutabilityTools::setSetting('enable_mutation', $enableMutation);
                CwInvoiceMutabilityTools::setSetting('write_configuration', $writeConfiguration);
                CwInvoiceMutabilityTools::setSetting('hide_banner', $hideBanner);
                CwInvoiceMutabilityTools::setSetting('enable_draft_tools', $enableDraftTools);
                CwInvoiceMutabilityTools::setSetting('draft_guard_keywords', $guardKeywords);

                $messages[] = ['success', 'Settings saved.'];

                if ($writeConfiguration === 'on') {
                    $result = CwInvoiceMutabilityTools::syncConfigurationFlag($enableMutation === 'on');
                    $messages[] = [$result['success'] ? 'success' : 'danger', $result['message']];
                } else {
                    $messages[] = ['warning', 'Runtime mode active: configuration.php was not modified. If WHMCS does not allow invoice editing, enable the official persistent configuration option.'];
                }

                logActivity('CW Invoice Mutability settings saved. Mutation: ' . ($enableMutation === 'on' ? 'enabled' : 'disabled') . '. Draft tools: ' . ($enableDraftTools === 'on' ? 'enabled' : 'disabled'));
            }

            if ($action === 'sync') {
                $result = CwInvoiceMutabilityTools::syncConfigurationFlag(CwInvoiceMutabilityTools::enabled('enable_mutation', false));
                $messages[] = [$result['success'] ? 'success' : 'danger', $result['message']];
                logActivity('CW Invoice Mutability configuration.php sync requested. Result: ' . $result['message']);
            }

            if ($action === 'remove') {
                CwInvoiceMutabilityTools::setSetting('enable_mutation', '');
                $result = CwInvoiceMutabilityTools::syncConfigurationFlag(false);
                $messages[] = [$result['success'] ? 'success' : 'danger', $result['message']];
                logActivity('CW Invoice Mutability flag removal requested. Result: ' . $result['message']);
            }

            if ($action === 'convert_to_draft') {
                $selectedInvoiceId = max(0, (int) ($_POST['invoice_id'] ?? 0));
                $result = CwInvoiceMutabilityTools::convertInvoiceToDraft($selectedInvoiceId, (string) ($_POST['reason'] ?? ''), (string) ($_POST['confirmation'] ?? ''));
                $messages[] = [$result['success'] ? 'success' : 'danger', $result['message']];
            }

            if ($action === 'restore_status') {
                $selectedInvoiceId = max(0, (int) ($_POST['invoice_id'] ?? 0));
                $logId = max(0, (int) ($_POST['log_id'] ?? 0));
                $result = CwInvoiceMutabilityTools::restoreInvoiceStatus($selectedInvoiceId, $logId, (string) ($_POST['reason'] ?? ''), (string) ($_POST['confirmation'] ?? ''));
                $messages[] = [$result['success'] ? 'success' : 'danger', $result['message']];
            }
        }
    }

    // Apply runtime flag during this request after saving settings.
    CwInvoiceMutabilityTools::applyRuntimeFlag();

    $enableMutation = CwInvoiceMutabilityTools::enabled('enable_mutation', false);
    $writeConfiguration = CwInvoiceMutabilityTools::enabled('write_configuration', true);
    $hideBanner = CwInvoiceMutabilityTools::enabled('hide_banner', true);
    $enableDraftTools = CwInvoiceMutabilityTools::enabled('enable_draft_tools', false);
    $guardKeywords = CwInvoiceMutabilityTools::setting('draft_guard_keywords', CwInvoiceMutabilityTools::defaultGuardKeywords());
    $configStatus = CwInvoiceMutabilityTools::configStatus();
    $runtimeActive = !empty($GLOBALS['allow_adminarea_invoice_mutation']);

    foreach ($messages as $message) {
        echo '<div class="alert alert-' . CwInvoiceMutabilityTools::html($message[0]) . '">' . CwInvoiceMutabilityTools::html($message[1]) . '</div>';
    }

    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title">CW Invoice Mutability <small>v' . CwInvoiceMutabilityTools::html($vars['version']) . '</small></h3></div>';
    echo '<div class="panel-body">';

    echo '<p><strong>Created by <a href="https://codigoweb.dev" target="_blank" rel="noopener">Codigoweb.dev</a>.</strong> This module lets WHMCS 9 administrators enable or disable published invoice editing without manually editing files. It also includes an advanced audited tool to convert internal <code>Unpaid</code> invoices to <code>Draft</code>.</p>';
    echo '<p><a class="btn btn-success" href="' . CwInvoiceMutabilityTools::html(CwInvoiceMutabilityTools::DONATION_URL) . '" target="_blank" rel="noopener"><i class="fa fa-heart"></i> Donate via PayPal</a> <a class="btn btn-default" href="https://docs.whmcs.com/9-0/troubleshooting/troubleshoot-invoices/invoice-immutability-errors/" target="_blank" rel="noopener"><i class="fa fa-book"></i> WHMCS documentation</a></p>';

    echo '<div class="alert alert-warning"><strong>Important:</strong> WHMCS recommends using credit/debit notes and warns that editing published invoices may affect audit trails, accounting, or tax compliance depending on your country. Use this module at your own risk.</div>';

    echo '<form method="post" action="' . CwInvoiceMutabilityTools::html($moduleLink) . '">';
    echo '<input type="hidden" name="cwim_token" value="' . CwInvoiceMutabilityTools::html($nonce) . '">';
    echo '<input type="hidden" name="cwim_action" value="save">';

    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<h4>Safe mode / current official method</h4>';
    echo '<div class="checkbox"><label><input type="checkbox" name="enable_mutation" value="1" ' . ($enableMutation ? 'checked' : '') . '> <strong>Allow editing published/non-Draft invoices</strong></label></div>';
    echo '<div class="checkbox"><label><input type="checkbox" name="write_configuration" value="1" ' . ($writeConfiguration ? 'checked' : '') . '> Use official persistent configuration in configuration.php</label></div>';
    echo '<div class="checkbox"><label><input type="checkbox" name="hide_banner" value="1" ' . ($hideBanner ? 'checked' : '') . '> Hide Admin Area warning banner</label></div>';

    echo '<hr><h4>Advanced / emergency mode</h4>';
    echo '<div class="checkbox"><label><input type="checkbox" name="enable_draft_tools" value="1" ' . ($enableDraftTools ? 'checked' : '') . '> <strong>Allow converting Unpaid invoices to Draft</strong></label></div>';
    echo '<div class="form-group"><label>Fiscal/external document protection keywords</label><textarea class="form-control" name="draft_guard_keywords" rows="6">' . CwInvoiceMutabilityTools::html($guardKeywords) . '</textarea><p class="help-block">One word or phrase per line. If it appears in invoice notes, invoice number, or invoice items, Draft conversion is blocked.</p></div>';
    echo '<p><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save and apply</button></p>';
    echo '</div>';

    echo '<div class="col-md-6">';
    echo '<table class="table table-striped table-condensed">';
    echo '<tbody>';
    echo '<tr><th>Module status</th><td>' . ($enableMutation ? '<span class="label label-success">Editing enabled</span>' : '<span class="label label-default">Editing disabled</span>') . '</td></tr>';
    echo '<tr><th>Advanced Draft mode</th><td>' . ($enableDraftTools ? '<span class="label label-warning">Active</span>' : '<span class="label label-default">Inactive</span>') . '</td></tr>';
    echo '<tr><th>Runtime flag</th><td>' . ($runtimeActive ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>') . '</td></tr>';
    echo '<tr><th>configuration.php</th><td>' . CwInvoiceMutabilityTools::html($configStatus['path']) . '</td></tr>';
    echo '<tr><th>Readable / writable</th><td>' . ($configStatus['readable'] ? 'Yes' : 'No') . ' / ' . ($configStatus['writable'] ? 'Yes' : 'No') . '</td></tr>';
    echo '<tr><th>Official flag detected</th><td>' . ($configStatus['has_enabled_flag'] ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>') . '</td></tr>';
    echo '<tr><th>Codigoweb.dev block</th><td>' . ($configStatus['has_cw_block'] ? '<span class="label label-info">Yes</span>' : '<span class="label label-default">No</span>') . '</td></tr>';
    echo '</tbody></table>';
    if (!empty($configStatus['error'])) {
        echo '<div class="alert alert-danger">' . CwInvoiceMutabilityTools::html($configStatus['error']) . '</div>';
    }
    echo '</div>';
    echo '</div>';
    echo '</form>';

    echo '<hr>';
    echo '<form method="post" action="' . CwInvoiceMutabilityTools::html($moduleLink) . '" style="display:inline-block;margin-right:10px;">';
    echo '<input type="hidden" name="cwim_token" value="' . CwInvoiceMutabilityTools::html($nonce) . '">';
    echo '<input type="hidden" name="cwim_action" value="sync">';
    echo '<button type="submit" class="btn btn-default"><i class="fa fa-refresh"></i> Sync configuration.php</button>';
    echo '</form>';

    echo '<form method="post" action="' . CwInvoiceMutabilityTools::html($moduleLink) . '" style="display:inline-block;" onsubmit="return confirm(\'This will disable editing and remove the official flag if it exists. Continue?\');">';
    echo '<input type="hidden" name="cwim_token" value="' . CwInvoiceMutabilityTools::html($nonce) . '">';
    echo '<input type="hidden" name="cwim_action" value="remove">';
    echo '<button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Disable and remove flag</button>';
    echo '</form>';

    echo '<h4 style="margin-top:25px;">Design notes</h4>';
    echo '<ul>';
    echo '<li>The module uses the official <code>$allow_adminarea_invoice_mutation = true;</code> flag when persistent mode is active.</li>';
    echo '<li>It also sets the flag at runtime from <code>hooks.php</code> as a fallback, but the persistent method is the most compatible while WHMCS allows it.</li>';
    echo '<li>Advanced mode does not edit items or totals: it only changes status <code>Unpaid → Draft</code>, saves an invoice/items/transactions snapshot, and records the action in the Activity Log.</li>';
    echo '<li>Advanced mode blocks paid, cancelled, transaction-linked, payment-dated, or fiscal/external-document invoices.</li>';
    echo '</ul>';

    echo '</div></div>';

    cw_invoice_mutability_renderInvoiceSearch($moduleLink, $nonce, $selectedInvoiceId);
}
