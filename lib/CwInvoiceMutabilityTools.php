<?php
/**
 * CW Invoice Mutability - helper class
 * Created by Codigoweb.dev
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

if (!class_exists('CwInvoiceMutabilityTools')) {
    class CwInvoiceMutabilityTools
    {
        public const MODULE = 'cw_invoice_mutability';
        public const DONATION_URL = 'https://paypal.me/hostingsupremo';
        public const CONFIG_BEGIN = '/* BEGIN CW Invoice Mutability - Codigoweb.dev */';
        public const CONFIG_END = '/* END CW Invoice Mutability - Codigoweb.dev */';
        public const LOG_TABLE = 'mod_cw_invoice_mutability_logs';
        public const CONFIRM_CONVERT = 'CONVERT TO DRAFT';
        public const CONFIRM_RESTORE = 'RESTORE STATUS';

        /**
         * Return a module setting stored in tbladdonmodules.
         */
        public static function setting(string $key, $default = '')
        {
            try {
                $value = Capsule::table('tbladdonmodules')
                    ->where('module', self::MODULE)
                    ->where('setting', $key)
                    ->value('value');

                return $value === null ? $default : (string) $value;
            } catch (\Throwable $e) {
                return $default;
            }
        }

        /**
         * Save or update a module setting.
         */
        public static function setSetting(string $key, string $value): void
        {
            $query = Capsule::table('tbladdonmodules')
                ->where('module', self::MODULE)
                ->where('setting', $key);

            if ($query->exists()) {
                $query->update(['value' => $value]);
                return;
            }

            Capsule::table('tbladdonmodules')->insert([
                'module' => self::MODULE,
                'setting' => $key,
                'value' => $value,
            ]);
        }

        /**
         * WHMCS yesno fields are commonly stored as "on".
         */
        public static function enabled(string $key, bool $default = false): bool
        {
            $value = strtolower(trim(self::setting($key, $default ? 'on' : '')));
            return in_array($value, ['on', '1', 'yes', 'true', 'checked'], true);
        }

        /**
         * Set the runtime global flag. This helps when the module is loaded early enough
         * and is also harmless when configuration.php already contains the flag.
         */
        public static function applyRuntimeFlag(): void
        {
            if (self::enabled('enable_mutation', false)) {
                $GLOBALS['allow_adminarea_invoice_mutation'] = true;
                global $allow_adminarea_invoice_mutation;
                $allow_adminarea_invoice_mutation = true;
            }
        }

        public static function rootPath(): string
        {
            if (defined('ROOTDIR')) {
                return rtrim(ROOTDIR, DIRECTORY_SEPARATOR);
            }

            return dirname(__DIR__, 4);
        }

        public static function configPath(): string
        {
            return self::rootPath() . DIRECTORY_SEPARATOR . 'configuration.php';
        }

        /**
         * Inspect configuration.php without exposing its contents.
         */
        public static function configStatus(): array
        {
            $path = self::configPath();
            $status = [
                'path' => $path,
                'exists' => is_file($path),
                'readable' => is_readable($path),
                'writable' => is_writable($path),
                'has_enabled_flag' => false,
                'has_disabled_flag' => false,
                'has_cw_block' => false,
                'error' => '',
            ];

            if (!$status['exists']) {
                $status['error'] = 'configuration.php was not found at the expected WHMCS root path.';
                return $status;
            }

            if (!$status['readable']) {
                $status['error'] = 'configuration.php exists but cannot be read by PHP.';
                return $status;
            }

            $content = file_get_contents($path);
            if ($content === false) {
                $status['error'] = 'configuration.php could not be read.';
                return $status;
            }

            $status['has_enabled_flag'] = (bool) preg_match('/^\s*\$allow_adminarea_invoice_mutation\s*=\s*true\s*;/mi', $content);
            $status['has_disabled_flag'] = (bool) preg_match('/^\s*\$allow_adminarea_invoice_mutation\s*=\s*false\s*;/mi', $content);
            $status['has_cw_block'] = strpos($content, self::CONFIG_BEGIN) !== false;

            return $status;
        }

        /**
         * Add or remove the official WHMCS configuration flag.
         * This creates a backup next to configuration.php before writing.
         */
        public static function syncConfigurationFlag(bool $enable): array
        {
            $path = self::configPath();

            if (!is_file($path)) {
                return ['success' => false, 'message' => 'configuration.php was not found in the WHMCS root directory.'];
            }
            if (!is_readable($path)) {
                return ['success' => false, 'message' => 'configuration.php is not readable by PHP.'];
            }
            if (!is_writable($path)) {
                return ['success' => false, 'message' => 'configuration.php is not writable. Temporarily adjust permissions or use hook runtime mode.'];
            }

            $original = file_get_contents($path);
            if ($original === false) {
                return ['success' => false, 'message' => 'Could not read configuration.php.'];
            }

            $updated = self::removeMutationFlagFromConfig($original);

            if ($enable) {
                $block = PHP_EOL
                    . self::CONFIG_BEGIN . PHP_EOL
                    . '// Added by CW Invoice Mutability. Disable from Addons > CW Invoice Mutability to remove it.' . PHP_EOL
                    . '$allow_adminarea_invoice_mutation = true;' . PHP_EOL
                    . self::CONFIG_END . PHP_EOL;
                $updated = rtrim($updated) . PHP_EOL . $block;
            }

            if ($updated === $original) {
                return ['success' => true, 'message' => $enable ? 'The official flag was already enabled.' : 'The official flag was already removed.'];
            }

            $backupPath = $path . '.cwim-' . date('Ymd-His') . '.bak';
            if (!copy($path, $backupPath)) {
                return ['success' => false, 'message' => 'Could not create a backup of configuration.php. No changes were applied.'];
            }
            @chmod($backupPath, 0600);

            $bytes = file_put_contents($path, $updated, LOCK_EX);
            if ($bytes === false) {
                return ['success' => false, 'message' => 'Could not write configuration.php. Backup created at: ' . basename($backupPath)];
            }

            return [
                'success' => true,
                'message' => $enable
                    ? 'Invoice editing enabled using the official WHMCS flag. Backup: ' . basename($backupPath)
                    : 'Invoice editing disabled and flag removed. Backup: ' . basename($backupPath),
            ];
        }

        private static function removeMutationFlagFromConfig(string $content): string
        {
            $begin = preg_quote(self::CONFIG_BEGIN, '/');
            $end = preg_quote(self::CONFIG_END, '/');
            $content = preg_replace('/\R?' . $begin . '.*?' . $end . '\R?/s', PHP_EOL, $content);

            // Remove any standalone official variable line, including a manually-added one.
            // This is intentional when the admin disables this addon.
            $content = preg_replace('/^\s*\$allow_adminarea_invoice_mutation\s*=\s*(true|false)\s*;\s*(?:\/\/.*)?\R?/mi', '', $content);

            return trim($content) . PHP_EOL;
        }

        public static function ensureLogTable(): void
        {
            $schema = Capsule::schema();

            if (!$schema->hasTable(self::LOG_TABLE)) {
                $schema->create(self::LOG_TABLE, function ($table) {
                    $table->increments('id');
                    $table->integer('invoice_id')->unsigned()->index();
                    $table->integer('admin_id')->unsigned()->nullable()->index();
                    $table->string('action', 64)->index();
                    $table->string('old_status', 64)->nullable();
                    $table->string('new_status', 64)->nullable();
                    $table->text('reason')->nullable();
                    $table->mediumText('invoice_snapshot_json')->nullable();
                    $table->mediumText('items_snapshot_json')->nullable();
                    $table->mediumText('transactions_snapshot_json')->nullable();
                    $table->string('ip_address', 45)->nullable();
                    $table->string('user_agent', 255)->nullable();
                    $table->dateTime('created_at')->index();
                });
                return;
            }

            // Lightweight self-healing for upgrades from early builds.
            $columns = [
                'transactions_snapshot_json' => function ($table) {
                    $table->mediumText('transactions_snapshot_json')->nullable();
                },
                'user_agent' => function ($table) {
                    $table->string('user_agent', 255)->nullable();
                },
            ];

            foreach ($columns as $column => $callback) {
                if (!$schema->hasColumn(self::LOG_TABLE, $column)) {
                    $schema->table(self::LOG_TABLE, $callback);
                }
            }
        }

        public static function dropLogTable(): void
        {
            $schema = Capsule::schema();
            if ($schema->hasTable(self::LOG_TABLE)) {
                $schema->drop(self::LOG_TABLE);
            }
        }

        public static function defaultGuardKeywords(): string
        {
            return "SRI
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
fiscalizado";
        }

        public static function guardKeywords(): array
        {
            $raw = self::setting('draft_guard_keywords', self::defaultGuardKeywords());
            $parts = preg_split('/[\r\n,]+/', (string) $raw);
            $keywords = [];

            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $keywords[] = $part;
                }
            }

            return array_values(array_unique($keywords));
        }

        public static function html(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        public static function adminNonce(): string
        {
            $adminId = isset($_SESSION['adminid']) ? (string) $_SESSION['adminid'] : '0';
            return hash('sha256', session_id() . '|' . $adminId . '|cw_invoice_mutability');
        }

        public static function verifyNonce(?string $token): bool
        {
            if (!is_string($token) || $token === '') {
                return false;
            }

            return hash_equals(self::adminNonce(), $token);
        }

        public static function currentAdminId(): int
        {
            return isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
        }

        public static function clientIp(): string
        {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
                if (empty($_SERVER[$key])) {
                    continue;
                }

                $value = (string) $_SERVER[$key];
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $value = trim(explode(',', $value)[0]);
                }

                if ($value !== '') {
                    return substr($value, 0, 45);
                }
            }

            return '';
        }

        public static function findInvoice(int $invoiceId)
        {
            if ($invoiceId <= 0) {
                return null;
            }

            return Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        }

        public static function getClientSummary(int $clientId): string
        {
            if ($clientId <= 0) {
                return '';
            }

            $client = Capsule::table('tblclients')
                ->select('id', 'firstname', 'lastname', 'companyname', 'email')
                ->where('id', $clientId)
                ->first();

            if (!$client) {
                return 'Client #' . $clientId;
            }

            $company = trim((string) ($client->companyname ?? ''));
            $name = trim((string) ($client->firstname ?? '') . ' ' . (string) ($client->lastname ?? ''));
            $email = trim((string) ($client->email ?? ''));
            $label = $company !== '' ? $company : $name;
            if ($label === '') {
                $label = 'Client #' . $clientId;
            }
            if ($email !== '') {
                $label .= ' <' . $email . '>';
            }

            return $label;
        }

        public static function collectionToArray($rows): array
        {
            if ($rows === null) {
                return [];
            }

            if (is_array($rows)) {
                return json_decode(json_encode($rows), true) ?: [];
            }

            if (is_object($rows) && method_exists($rows, 'toArray')) {
                return json_decode(json_encode($rows->toArray()), true) ?: [];
            }

            return json_decode(json_encode($rows), true) ?: [];
        }

        public static function invoiceItems(int $invoiceId): array
        {
            $rows = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->orderBy('id', 'asc')->get();
            return self::collectionToArray($rows);
        }

        public static function invoiceTransactions(int $invoiceId): array
        {
            $rows = Capsule::table('tblaccounts')->where('invoiceid', $invoiceId)->orderBy('id', 'asc')->get();
            return self::collectionToArray($rows);
        }

        private static function jsonEncode($value): string
        {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            return $encoded === false ? '' : $encoded;
        }

        private static function invoiceDatePaidIsSet(array $invoiceData): bool
        {
            $datePaid = trim((string) ($invoiceData['datepaid'] ?? ''));
            return $datePaid !== ''
                && $datePaid !== '0000-00-00'
                && $datePaid !== '0000-00-00 00:00:00'
                && strtolower($datePaid) !== 'null';
        }

        private static function invoiceHasGatewayTransactionReference(array $invoiceData): bool
        {
            foreach (['gatewayid', 'transid', 'transactionid', 'paymentid'] as $field) {
                if (array_key_exists($field, $invoiceData) && trim((string) $invoiceData[$field]) !== '') {
                    return true;
                }
            }

            return false;
        }

        private static function matchedGuardKeywords(array $invoiceData, array $items): array
        {
            $haystack = '';
            foreach (['notes', 'adminnotes', 'invoicenum'] as $field) {
                if (!empty($invoiceData[$field])) {
                    $haystack .= ' ' . (string) $invoiceData[$field];
                }
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    foreach (['description', 'notes'] as $field) {
                        if (!empty($item[$field])) {
                            $haystack .= ' ' . (string) $item[$field];
                        }
                    }
                }
            }

            $haystackLower = strtolower($haystack);
            $matches = [];
            foreach (self::guardKeywords() as $keyword) {
                $keywordLower = strtolower($keyword);
                if ($keywordLower !== '' && strpos($haystackLower, $keywordLower) !== false) {
                    $matches[] = $keyword;
                }
            }

            return $matches;
        }

        public static function assessInvoiceForDraft(int $invoiceId): array
        {
            $invoice = self::findInvoice($invoiceId);
            if (!$invoice) {
                return [
                    'exists' => false,
                    'allowed' => false,
                    'invoice' => null,
                    'invoice_data' => [],
                    'items' => [],
                    'transactions' => [],
                    'blocks' => ['Invoice #' . $invoiceId . ' was not found.'],
                    'warnings' => [],
                    'status' => '',
                ];
            }

            $invoiceData = self::collectionToArray($invoice);
            $items = self::invoiceItems($invoiceId);
            $transactions = self::invoiceTransactions($invoiceId);
            $status = (string) ($invoiceData['status'] ?? '');
            $blocks = [];
            $warnings = [];

            if ($status === 'Draft') {
                $blocks[] = 'The invoice is already in Draft.';
            } elseif ($status !== 'Unpaid') {
                $blocks[] = 'Only Unpaid invoices can be converted to Draft. Current status: ' . ($status !== '' ? $status : 'unknown') . '.';
            }

            if (count($transactions) > 0) {
                $blocks[] = 'The invoice has related transactions in tblaccounts. It will not be changed to avoid affecting payments or audit trails.';
            }

            if (self::invoiceDatePaidIsSet($invoiceData)) {
                $blocks[] = 'The invoice has a payment date recorded.';
            }

            if (self::invoiceHasGatewayTransactionReference($invoiceData)) {
                $blocks[] = 'The invoice contains a transaction/gateway reference in its fields.';
            }

            $keywordMatches = self::matchedGuardKeywords($invoiceData, $items);
            if (!empty($keywordMatches)) {
                $blocks[] = 'The invoice matches fiscal/external document protection keywords: ' . implode(', ', $keywordMatches) . '.';
            }

            if (empty($invoiceData['duedate'] ?? '')) {
                $warnings[] = 'No due date was detected on the invoice.';
            }

            return [
                'exists' => true,
                'allowed' => empty($blocks),
                'invoice' => $invoice,
                'invoice_data' => $invoiceData,
                'items' => $items,
                'transactions' => $transactions,
                'blocks' => $blocks,
                'warnings' => $warnings,
                'status' => $status,
            ];
        }

        public static function latestConversionLog(int $invoiceId)
        {
            self::ensureLogTable();
            return Capsule::table(self::LOG_TABLE)
                ->where('invoice_id', $invoiceId)
                ->where('action', 'convert_to_draft')
                ->orderBy('id', 'desc')
                ->first();
        }

        public static function recentLogs(int $invoiceId = 0, int $limit = 10): array
        {
            self::ensureLogTable();
            $query = Capsule::table(self::LOG_TABLE)->orderBy('id', 'desc');
            if ($invoiceId > 0) {
                $query->where('invoice_id', $invoiceId);
            }

            return self::collectionToArray($query->limit($limit)->get());
        }

        private static function insertLog(int $invoiceId, string $action, string $oldStatus, string $newStatus, string $reason, array $invoiceSnapshot, array $itemsSnapshot, array $transactionsSnapshot): int
        {
            self::ensureLogTable();
            return (int) Capsule::table(self::LOG_TABLE)->insertGetId([
                'invoice_id' => $invoiceId,
                'admin_id' => self::currentAdminId() ?: null,
                'action' => $action,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'invoice_snapshot_json' => self::jsonEncode($invoiceSnapshot),
                'items_snapshot_json' => self::jsonEncode($itemsSnapshot),
                'transactions_snapshot_json' => self::jsonEncode($transactionsSnapshot),
                'ip_address' => self::clientIp(),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        public static function convertInvoiceToDraft(int $invoiceId, string $reason, string $confirmation): array
        {
            if (!self::enabled('enable_draft_tools', false)) {
                return ['success' => false, 'message' => 'Advanced Draft conversion mode is disabled.'];
            }

            if (trim($confirmation) !== self::CONFIRM_CONVERT) {
                return ['success' => false, 'message' => 'Confirmation incorrecta. Debes escribir exactamente: ' . self::CONFIRM_CONVERT];
            }

            $reason = trim($reason);
            if ($reason === '') {
                return ['success' => false, 'message' => 'You must provide a reason for the change.'];
            }

            $assessment = self::assessInvoiceForDraft($invoiceId);
            if (!$assessment['exists']) {
                return ['success' => false, 'message' => 'Invoice #' . $invoiceId . ' was not found.'];
            }
            if (!$assessment['allowed']) {
                return ['success' => false, 'message' => 'Cannot convert to Draft: ' . implode(' ', $assessment['blocks'])];
            }

            $invoiceData = $assessment['invoice_data'];
            $oldStatus = (string) ($invoiceData['status'] ?? '');
            $logId = self::insertLog(
                $invoiceId,
                'convert_to_draft',
                $oldStatus,
                'Draft',
                $reason,
                $invoiceData,
                $assessment['items'],
                $assessment['transactions']
            );

            Capsule::table('tblinvoices')->where('id', $invoiceId)->update(['status' => 'Draft']);

            logActivity('CW Invoice Mutability: invoice #' . $invoiceId . ' converted from ' . $oldStatus . ' to Draft by admin #' . self::currentAdminId() . '. Log ID: ' . $logId);

            return ['success' => true, 'message' => 'Invoice #' . $invoiceId . ' converted to Draft. Snapshot saved in log #' . $logId . '.'];
        }

        public static function restoreInvoiceStatus(int $invoiceId, int $logId, string $reason, string $confirmation): array
        {
            if (!self::enabled('enable_draft_tools', false)) {
                return ['success' => false, 'message' => 'Advanced status restore mode is disabled.'];
            }

            if (trim($confirmation) !== self::CONFIRM_RESTORE) {
                return ['success' => false, 'message' => 'Confirmation incorrecta. Debes escribir exactamente: ' . self::CONFIRM_RESTORE];
            }

            $reason = trim($reason);
            if ($reason === '') {
                return ['success' => false, 'message' => 'You must provide a reason to restore the status.'];
            }

            self::ensureLogTable();
            $log = Capsule::table(self::LOG_TABLE)
                ->where('id', $logId)
                ->where('invoice_id', $invoiceId)
                ->where('action', 'convert_to_draft')
                ->first();

            if (!$log) {
                return ['success' => false, 'message' => 'No valid conversion log was found for this invoice.'];
            }

            $invoice = self::findInvoice($invoiceId);
            if (!$invoice) {
                return ['success' => false, 'message' => 'Invoice #' . $invoiceId . ' was not found.'];
            }

            $invoiceData = self::collectionToArray($invoice);
            $currentStatus = (string) ($invoiceData['status'] ?? '');
            if ($currentStatus !== 'Draft') {
                return ['success' => false, 'message' => 'Solo se puede restaurar desde Draft. Current status: ' . $currentStatus . '.'];
            }

            $targetStatus = (string) ($log->old_status ?? '');
            if (!in_array($targetStatus, ['Unpaid'], true)) {
                return ['success' => false, 'message' => 'Previous status is not allowed for automatic restore: ' . $targetStatus . '.'];
            }

            $items = self::invoiceItems($invoiceId);
            $transactions = self::invoiceTransactions($invoiceId);
            if (count($transactions) > 0) {
                return ['success' => false, 'message' => 'The invoice now has related transactions. It will not be restored automatically.'];
            }

            $restoreLogId = self::insertLog(
                $invoiceId,
                'restore_status',
                $currentStatus,
                $targetStatus,
                $reason,
                $invoiceData,
                $items,
                $transactions
            );

            Capsule::table('tblinvoices')->where('id', $invoiceId)->update(['status' => $targetStatus]);

            logActivity('CW Invoice Mutability: invoice #' . $invoiceId . ' restored from Draft to ' . $targetStatus . ' by admin #' . self::currentAdminId() . '. Log ID: ' . $restoreLogId);

            return ['success' => true, 'message' => 'Invoice #' . $invoiceId . ' restored to ' . $targetStatus . '. Log #' . $restoreLogId . '.'];
        }
    }
}
