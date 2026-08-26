<?php

define('_JEXEC', 1);
require __DIR__ . '/../administrator/components/com_loginguard/src/Service/CsvCellNeutralizer.php';

use LoginGuard\Component\LoginGuard\Administrator\Service\CsvCellNeutralizer;

$cases = [
    '=HYPERLINK("https://invalid")' => "'=HYPERLINK(\"https://invalid\")",
    '+SUM(1,2)' => "'+SUM(1,2)",
    '-1+1' => "'-1+1",
    '@SUM(1,2)' => "'@SUM(1,2)",
    ' =2+2' => "' =2+2",
    "\t+SUM(1,2)" => "'\t+SUM(1,2)",
    'ordinary value' => 'ordinary value',
    'example@example.test' => 'example@example.test',
    '  ordinary value' => '  ordinary value',
];
foreach ($cases as $raw => $expected) {
    $actual = CsvCellNeutralizer::neutralize($raw);
    if ($actual !== $expected || array_key_first([$raw => true]) !== $raw) {
        fwrite(STDERR, "CSV neutralization regression failed for " . json_encode($raw) . "\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$attempts = file_get_contents($root . '/administrator/components/com_loginguard/src/Controller/AttemptsController.php');
$blocked = file_get_contents($root . '/administrator/components/com_loginguard/src/Controller/BlockedipsController.php');
$audit = file_get_contents($root . '/administrator/components/com_loginguard/src/Service/AdminAuditService.php');
$cleanup = file_get_contents($root . '/administrator/components/com_loginguard/src/Service/CleanupService.php');
$dashboard = file_get_contents($root . '/administrator/components/com_loginguard/src/Controller/DashboardController.php');
$manifest = file_get_contents($root . '/administrator/components/com_loginguard/loginguard.xml');
$runtimeBaseline = file_get_contents($root . '/tests/v020_runtime_baseline.php');

foreach (['login_attempt.delete', 'login_attempt.export'] as $action) {
    if (!str_contains($attempts, $action)) throw new RuntimeException("Missing audit action $action");
}
foreach (['blocked_ip.create', 'blocked_ip.update', 'blocked_ip.delete', 'blocked_ip.enable', 'blocked_ip.disable', 'blocked_ip.unblock'] as $action) {
    if (!str_contains($blocked, $action)) throw new RuntimeException("Missing audit action $action");
}
foreach ([$attempts, $blocked] as $controller) {
    if (!str_contains($controller, 'requirePermission') || !str_contains($controller, 'checkToken()')) {
        throw new RuntimeException('Permission or CSRF guard missing');
    }
}
if (str_contains($cleanup, '#__loginguard_admin_audit')) throw new RuntimeException('Cleanup must exclude admin audit');
if (!str_contains($dashboard, "'cleanup.execute'") || !str_contains($dashboard, 'transactionStart()')) throw new RuntimeException('Administrator cleanup must be transactionally audited');
if (!str_contains($audit, 'FORBIDDEN_DETAIL_KEYS') || !str_contains($audit, 'JSON_THROW_ON_ERROR')) throw new RuntimeException('Audit detail filtering missing');
if (!str_contains($manifest, '<menu view="adminaudit">') || is_file($root . '/administrator/components/com_loginguard/src/Controller/AdminauditController.php')) {
    throw new RuntimeException('Audit must have a read-only view and no mutation controller');
}
if (str_contains($runtimeBaseline, "'plugins/user/loginguard/src/Extension/LoginGuard.php',") || str_contains($runtimeBaseline, "'plugins/user/loginguard/src/Service/IpResolver.php',")) throw new RuntimeException('Runtime baseline guard was widened');

echo "Issue #80 security regressions completed successfully\n";
