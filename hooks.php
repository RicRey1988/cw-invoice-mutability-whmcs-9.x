<?php
/**
 * CW Invoice Mutability - WHMCS hooks
 * Created by Codigoweb.dev
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/CwInvoiceMutabilityTools.php';

// Best-effort runtime activation. The official and persistent mode is managed
// by writing $allow_adminarea_invoice_mutation = true; to configuration.php.
CwInvoiceMutabilityTools::applyRuntimeFlag();

add_hook('AdminAreaHeadOutput', 1, function ($vars) {
    if (!CwInvoiceMutabilityTools::enabled('hide_banner', true)) {
        return '';
    }

    return <<<'HTML'
<style>
    .cwim-hidden-warning { display: none !important; }
</style>
<script>
(function () {
    'use strict';

    function textMatchesWarning(text) {
        text = (text || '').toLowerCase();
        return text.indexOf('invoice immutability is disabled') !== -1
            || text.indexOf('la inmutabilidad de las facturas está desactivada') !== -1
            || text.indexOf('la inmutabilidad de las facturas esta desactivada') !== -1;
    }

    function hideInvoiceImmutabilityWarning() {
        var nodes = document.querySelectorAll('.alert, .alert-warning, .alert-info, [class*="alert"], [role="alert"]');
        for (var i = 0; i < nodes.length; i++) {
            if (textMatchesWarning(nodes[i].textContent)) {
                nodes[i].classList.add('cwim-hidden-warning');
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideInvoiceImmutabilityWarning);
    } else {
        hideInvoiceImmutabilityWarning();
    }

    setTimeout(hideInvoiceImmutabilityWarning, 300);
    setTimeout(hideInvoiceImmutabilityWarning, 1200);
})();
</script>
HTML;
});

add_hook('AdminInvoicesControlsOutput', 1, function ($vars) {
    $invoiceId = 0;
    foreach (['invoiceid', 'invoiceId', 'id'] as $key) {
        if (!empty($vars[$key])) {
            $invoiceId = (int) $vars[$key];
            break;
        }
    }
    if ($invoiceId <= 0) {
        foreach (['invoiceid', 'id'] as $key) {
            if (!empty($_REQUEST[$key])) {
                $invoiceId = (int) $_REQUEST[$key];
                break;
            }
        }
    }

    $donationUrl = CwInvoiceMutabilityTools::DONATION_URL;
    $html = '';

    if (CwInvoiceMutabilityTools::enabled('enable_mutation', false)) {
        $html .= '<div class="alert alert-info" style="margin-top:10px;">'
            . '<strong>CW Invoice Mutability:</strong> invoice editing enabled by Codigoweb.dev. '
            . '<a href="' . CwInvoiceMutabilityTools::html($donationUrl) . '" target="_blank" rel="noopener">Donate via PayPal</a>.'
            . '</div>';
    }

    if ($invoiceId > 0 && CwInvoiceMutabilityTools::enabled('enable_draft_tools', false)) {
        $html .= '<div class="well well-sm" style="margin-top:10px;">'
            . '<strong>Codigoweb.dev advanced mode:</strong> '
            . '<a class="btn btn-warning btn-sm" href="addonmodules.php?module=cw_invoice_mutability&invoice_id=' . (int) $invoiceId . '#cwim-draft-tools">'
            . '<i class="fa fa-edit"></i> Review/convert to Draft'
            . '</a> '
            . '<span class="text-muted">Only for Unpaid invoices without transactions.</span>'
            . '</div>';
    }

    return $html;
});
