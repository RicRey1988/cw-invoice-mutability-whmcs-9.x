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
        'description' => 'Permite activar la edición de facturas en WHMCS 9 sin editar manualmente configuration.php, ocultar el banner de advertencia y, opcionalmente, convertir facturas Unpaid a Draft con auditoría. Creado por Codigoweb.dev. Donaciones: https://paypal.me/hostingsupremo',
        'version' => '1.1.0',
        'author' => '<a href="https://codigoweb.dev" target="_blank" rel="noopener">Codigoweb.dev</a>',
        'language' => 'spanish',
        'fields' => [
            'enable_mutation' => [
                'FriendlyName' => 'Permitir edición de facturas',
                'Type' => 'yesno',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Activa la mutabilidad de facturas publicadas/no Draft en el área administrativa.',
            ],
            'write_configuration' => [
                'FriendlyName' => 'Usar configuración oficial persistente',
                'Type' => 'yesno',
                'Size' => '25',
                'Default' => 'on',
                'Description' => 'Agrega o remueve automáticamente $allow_adminarea_invoice_mutation = true; en configuration.php. Es la vía oficial documentada por WHMCS mientras exista.',
            ],
            'hide_banner' => [
                'FriendlyName' => 'Ocultar banner de advertencia',
                'Type' => 'yesno',
                'Size' => '25',
                'Default' => 'on',
                'Description' => 'Oculta solo el banner del Admin Area que indica que la inmutabilidad de facturas está desactivada.',
            ],
            'enable_draft_tools' => [
                'FriendlyName' => 'Modo avanzado: convertir a Draft',
                'Type' => 'yesno',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Permite convertir facturas Unpaid sin transacciones a Draft con snapshot y log. Úsalo solo para casos internos/no fiscalizados.',
            ],
            'draft_guard_keywords' => [
                'FriendlyName' => 'Palabras de protección fiscal',
                'Type' => 'textarea',
                'Rows' => '6',
                'Cols' => '70',
                'Default' => CwInvoiceMutabilityTools::defaultGuardKeywords(),
                'Description' => 'Si estas palabras aparecen en notas, número o ítems de la factura, el módulo bloquea la conversión a Draft.',
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
            'description' => 'CW Invoice Mutability activado. Ingresa al módulo para habilitar la edición de facturas y, si lo necesitas, el modo avanzado Draft.',
        ];
    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'description' => 'Error al activar: ' . $e->getMessage(),
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
            'description' => 'CW Invoice Mutability desactivado. La bandera oficial fue removida si estaba presente. Los logs de auditoría se conservan.',
        ];
    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'description' => 'Error al desactivar: ' . $e->getMessage(),
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
    echo '<div class="panel-heading"><h3 class="panel-title"><i class="fa fa-edit"></i> Modo avanzado: convertir factura a Draft</h3></div>';
    echo '<div class="panel-body">';

    echo '<div class="alert alert-danger"><strong>Uso delicado:</strong> esta herramienta cambia el estado interno de la factura directamente en base de datos. Solo se permite para facturas <strong>Unpaid</strong>, sin transacciones y no protegidas por palabras fiscales. No debe usarse en facturas pagadas, autorizadas por SRI, fiscalizadas o sincronizadas con un sistema externo.</div>';

    echo '<form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom:15px;">';
    echo '<input type="hidden" name="module" value="cw_invoice_mutability">';
    echo '<div class="form-group"><label for="cwim_invoice_id">Factura #</label> ';
    echo '<input type="number" min="1" class="form-control" id="cwim_invoice_id" name="invoice_id" value="' . ($selectedInvoiceId > 0 ? CwInvoiceMutabilityTools::html((string) $selectedInvoiceId) : '') . '" placeholder="Ej. 1234"></div> ';
    echo '<button type="submit" class="btn btn-default"><i class="fa fa-search"></i> Revisar factura</button>';
    echo '</form>';

    if ($selectedInvoiceId <= 0) {
        echo '<p class="text-muted">Ingresa el ID de una factura para revisar si puede convertirse a Draft.</p>';
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

    echo '<h4>Factura #' . CwInvoiceMutabilityTools::html((string) $selectedInvoiceId) . '</h4>';
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<table class="table table-striped table-condensed"><tbody>';
    echo '<tr><th>Estado</th><td><span class="label label-' . ($status === 'Draft' ? 'info' : ($status === 'Unpaid' ? 'warning' : 'default')) . '">' . CwInvoiceMutabilityTools::html($status) . '</span></td></tr>';
    echo '<tr><th>Cliente</th><td>' . CwInvoiceMutabilityTools::html($clientLabel) . '</td></tr>';
    echo '<tr><th>Total / Balance</th><td>' . CwInvoiceMutabilityTools::html($total) . ' / ' . CwInvoiceMutabilityTools::html($balance) . '</td></tr>';
    echo '<tr><th>Fecha / Vencimiento</th><td>' . CwInvoiceMutabilityTools::html((string) ($invoice['date'] ?? '')) . ' / ' . CwInvoiceMutabilityTools::html((string) ($invoice['duedate'] ?? '')) . '</td></tr>';
    echo '<tr><th>Fecha de pago</th><td>' . CwInvoiceMutabilityTools::html((string) ($invoice['datepaid'] ?? '')) . '</td></tr>';
    echo '<tr><th>Ítems / Transacciones</th><td>' . count($assessment['items']) . ' / ' . count($assessment['transactions']) . '</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="col-md-6">';
    if ($assessment['allowed']) {
        echo '<div class="alert alert-success"><strong>Validación:</strong> esta factura puede convertirse a Draft según las reglas actuales.</div>';
    } else {
        echo '<div class="alert alert-warning"><strong>Validación:</strong> esta factura no puede convertirse automáticamente.</div>';
        echo '<ul>';
        foreach ($assessment['blocks'] as $block) {
            echo '<li>' . CwInvoiceMutabilityTools::html($block) . '</li>';
        }
        echo '</ul>';
    }

    if (!empty($assessment['warnings'])) {
        echo '<p><strong>Advertencias:</strong></p><ul>';
        foreach ($assessment['warnings'] as $warning) {
            echo '<li>' . CwInvoiceMutabilityTools::html($warning) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
    echo '</div>';

    if (!CwInvoiceMutabilityTools::enabled('enable_draft_tools', false)) {
        echo '<div class="alert alert-info">El modo avanzado está desactivado. Actívalo en la configuración superior y guarda para poder convertir facturas a Draft.</div>';
    } elseif ($assessment['allowed']) {
        echo '<form method="post" action="' . CwInvoiceMutabilityTools::html($moduleLink) . '#cwim-draft-tools" onsubmit="return confirm(\'Esta acción convertirá la factura a Draft. Se guardará snapshot, pero el cambio sigue siendo delicado. ¿Continuar?\');">';
        echo '<input type="hidden" name="cwim_token" value="' . CwInvoiceMutabilityTools::html($nonce) . '">';
        echo '<input type="hidden" name="cwim_action" value="convert_to_draft">';
        echo '<input type="hidden" name="invoice_id" value="' . CwInvoiceMutabilityTools::html((string) $selectedInvoiceId) . '">';
        echo '<div class="form-group"><label>Motivo obligatorio</label><textarea class="form-control" name="reason" rows="3" required placeholder="Ej. Corrección interna antes de pago, factura no fiscalizada ni autorizada externamente."></textarea></div>';
        echo '<div class="form-group"><label>Confirmación</label><input type="text" class="form-control" name="confirmation" required placeholder="Escribe: ' . CwInvoiceMutabilityTools::CONFIRM_CONVERT . '"></div>';
        echo '<button type="submit" class="btn btn-warning"><i class="fa fa-edit"></i> Convertir factura a Draft</button>';
        echo '</form>';
    }

    if ($status === 'Draft') {
        $lastLog = CwInvoiceMutabilityTools::latestConversionLog($selectedInvoiceId);
        if ($lastLog) {
            echo '<hr><h4>Restaurar estado anterior</h4>';
            echo '<p class="text-muted">Última conversión encontrada: log #' . CwInvoiceMutabilityTools::html((string) $lastLog->id) . ', estado anterior: <strong>' . CwInvoiceMutabilityTools::html((string) $lastLog->old_status) . '</strong>.</p>';
            if (CwInvoiceMutabilityTools::enabled('enable_draft_tools', false)) {
                echo '<form method="post" action="' . CwInvoiceMutabilityTools::html($moduleLink) . '#cwim-draft-tools" onsubmit="return confirm(\'Esto restaurará únicamente el estado anterior de la factura, no revierte los cambios realizados en los ítems. ¿Continuar?\');">';
                echo '<input type="hidden" name="cwim_token" value="' . CwInvoiceMutabilityTools::html($nonce) . '">';
                echo '<input type="hidden" name="cwim_action" value="restore_status">';
                echo '<input type="hidden" name="invoice_id" value="' . CwInvoiceMutabilityTools::html((string) $selectedInvoiceId) . '">';
                echo '<input type="hidden" name="log_id" value="' . CwInvoiceMutabilityTools::html((string) $lastLog->id) . '">';
                echo '<div class="form-group"><label>Motivo obligatorio</label><textarea class="form-control" name="reason" rows="2" required placeholder="Ej. Edición finalizada, devolver factura a estado anterior."></textarea></div>';
                echo '<div class="form-group"><label>Confirmación</label><input type="text" class="form-control" name="confirmation" required placeholder="Escribe: ' . CwInvoiceMutabilityTools::CONFIRM_RESTORE . '"></div>';
                echo '<button type="submit" class="btn btn-primary"><i class="fa fa-undo"></i> Restaurar estado anterior</button>';
                echo '</form>';
            }
        }
    }

    $logs = CwInvoiceMutabilityTools::recentLogs($selectedInvoiceId, 10);
    if (!empty($logs)) {
        echo '<hr><h4>Historial de esta factura</h4>';
        echo '<div class="table-responsive"><table class="table table-condensed table-striped"><thead><tr><th>ID</th><th>Fecha</th><th>Admin</th><th>Acción</th><th>Estado</th><th>Motivo</th></tr></thead><tbody>';
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
        $messages[] = ['danger', 'No fue posible preparar la tabla de auditoría: ' . $e->getMessage()];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postedToken = $_POST['cwim_token'] ?? '';
        if (!CwInvoiceMutabilityTools::verifyNonce($postedToken)) {
            $messages[] = ['danger', 'Token de seguridad inválido. Recarga la página e intenta nuevamente.'];
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

                $messages[] = ['success', 'Configuración guardada.'];

                if ($writeConfiguration === 'on') {
                    $result = CwInvoiceMutabilityTools::syncConfigurationFlag($enableMutation === 'on');
                    $messages[] = [$result['success'] ? 'success' : 'danger', $result['message']];
                } else {
                    $messages[] = ['warning', 'Modo runtime activo: no se modificó configuration.php. Si WHMCS no permite editar facturas, activa la configuración oficial persistente.'];
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

    echo '<p><strong>Creado por <a href="https://codigoweb.dev" target="_blank" rel="noopener">Codigoweb.dev</a>.</strong> Este módulo permite activar o desactivar la edición administrativa de facturas publicadas en WHMCS 9 sin editar manualmente archivos. También incluye una herramienta avanzada para convertir facturas internas <code>Unpaid</code> a <code>Draft</code> con auditoría.</p>';
    echo '<p><a class="btn btn-success" href="' . CwInvoiceMutabilityTools::html(CwInvoiceMutabilityTools::DONATION_URL) . '" target="_blank" rel="noopener"><i class="fa fa-heart"></i> Donar por PayPal</a> <a class="btn btn-default" href="https://docs.whmcs.com/9-0/troubleshooting/troubleshoot-invoices/invoice-immutability-errors/" target="_blank" rel="noopener"><i class="fa fa-book"></i> Documentación WHMCS</a></p>';

    echo '<div class="alert alert-warning"><strong>Importante:</strong> WHMCS recomienda usar notas de crédito/débito y advierte que editar facturas publicadas puede afectar auditoría, contabilidad o cumplimiento fiscal según el país. Usa este módulo bajo tu responsabilidad.</div>';

    echo '<form method="post" action="' . CwInvoiceMutabilityTools::html($moduleLink) . '">';
    echo '<input type="hidden" name="cwim_token" value="' . CwInvoiceMutabilityTools::html($nonce) . '">';
    echo '<input type="hidden" name="cwim_action" value="save">';

    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<h4>Modo seguro / oficial actual</h4>';
    echo '<div class="checkbox"><label><input type="checkbox" name="enable_mutation" value="1" ' . ($enableMutation ? 'checked' : '') . '> <strong>Permitir edición de facturas publicadas/no Draft</strong></label></div>';
    echo '<div class="checkbox"><label><input type="checkbox" name="write_configuration" value="1" ' . ($writeConfiguration ? 'checked' : '') . '> Usar configuración oficial persistente en configuration.php</label></div>';
    echo '<div class="checkbox"><label><input type="checkbox" name="hide_banner" value="1" ' . ($hideBanner ? 'checked' : '') . '> Ocultar banner de advertencia del Admin Area</label></div>';

    echo '<hr><h4>Modo avanzado / emergencia</h4>';
    echo '<div class="checkbox"><label><input type="checkbox" name="enable_draft_tools" value="1" ' . ($enableDraftTools ? 'checked' : '') . '> <strong>Permitir convertir facturas Unpaid a Draft</strong></label></div>';
    echo '<div class="form-group"><label>Palabras de protección fiscal/comprobante externo</label><textarea class="form-control" name="draft_guard_keywords" rows="6">' . CwInvoiceMutabilityTools::html($guardKeywords) . '</textarea><p class="help-block">Una palabra o frase por línea. Si aparece en notas, número o ítems de la factura, se bloquea la conversión a Draft.</p></div>';
    echo '<p><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar y aplicar</button></p>';
    echo '</div>';

    echo '<div class="col-md-6">';
    echo '<table class="table table-striped table-condensed">';
    echo '<tbody>';
    echo '<tr><th>Estado del módulo</th><td>' . ($enableMutation ? '<span class="label label-success">Edición habilitada</span>' : '<span class="label label-default">Edición deshabilitada</span>') . '</td></tr>';
    echo '<tr><th>Modo Draft avanzado</th><td>' . ($enableDraftTools ? '<span class="label label-warning">Activo</span>' : '<span class="label label-default">Inactivo</span>') . '</td></tr>';
    echo '<tr><th>Bandera runtime</th><td>' . ($runtimeActive ? '<span class="label label-success">Activa</span>' : '<span class="label label-default">Inactiva</span>') . '</td></tr>';
    echo '<tr><th>configuration.php</th><td>' . CwInvoiceMutabilityTools::html($configStatus['path']) . '</td></tr>';
    echo '<tr><th>Legible / escribible</th><td>' . ($configStatus['readable'] ? 'Sí' : 'No') . ' / ' . ($configStatus['writable'] ? 'Sí' : 'No') . '</td></tr>';
    echo '<tr><th>Bandera oficial detectada</th><td>' . ($configStatus['has_enabled_flag'] ? '<span class="label label-success">Sí</span>' : '<span class="label label-default">No</span>') . '</td></tr>';
    echo '<tr><th>Bloque Codigoweb.dev</th><td>' . ($configStatus['has_cw_block'] ? '<span class="label label-info">Sí</span>' : '<span class="label label-default">No</span>') . '</td></tr>';
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
    echo '<button type="submit" class="btn btn-default"><i class="fa fa-refresh"></i> Sincronizar configuration.php</button>';
    echo '</form>';

    echo '<form method="post" action="' . CwInvoiceMutabilityTools::html($moduleLink) . '" style="display:inline-block;" onsubmit="return confirm(\'Esto deshabilitará la edición y removerá la bandera oficial si existe. ¿Continuar?\');">';
    echo '<input type="hidden" name="cwim_token" value="' . CwInvoiceMutabilityTools::html($nonce) . '">';
    echo '<input type="hidden" name="cwim_action" value="remove">';
    echo '<button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Desactivar y remover bandera</button>';
    echo '</form>';

    echo '<h4 style="margin-top:25px;">Notas de diseño</h4>';
    echo '<ul>';
    echo '<li>El módulo usa la bandera oficial <code>$allow_adminarea_invoice_mutation = true;</code> cuando el modo persistente está activo.</li>';
    echo '<li>También define la bandera en runtime desde <code>hooks.php</code> como apoyo, pero el método persistente es el más compatible mientras WHMCS lo permita.</li>';
    echo '<li>El modo avanzado no edita ítems ni totales: solo cambia estado <code>Unpaid → Draft</code>, guarda snapshot de factura/ítems/transacciones y registra la acción en Activity Log.</li>';
    echo '<li>El modo avanzado bloquea facturas pagadas, canceladas, con transacciones, con fecha de pago o con señales de comprobante fiscal/externalizado.</li>';
    echo '</ul>';

    echo '</div></div>';

    cw_invoice_mutability_renderInvoiceSearch($moduleLink, $nonce, $selectedInvoiceId);
}
