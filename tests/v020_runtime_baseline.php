<?php

declare(strict_types=1);

$baseline = '9e94e922f5c66ea1b4b4feecafe340dad7f43b19';
$allowed = [
    'CHANGELOG.md', 'README.md', 'VERSION',
    'administrator/components/com_loginguard/loginguard.xml',
    'administrator/components/com_loginguard/language/en-GB/en-GB.com_loginguard.ini',
    'administrator/components/com_loginguard/language/en-GB/en-GB.com_loginguard.sys.ini',
    'administrator/components/com_loginguard/src/Controller/AttemptsController.php',
    'administrator/components/com_loginguard/src/Controller/BlockedipsController.php',
    'administrator/components/com_loginguard/src/Model/AdminauditModel.php',
    'administrator/components/com_loginguard/src/Service/AdminAuditService.php',
    'administrator/components/com_loginguard/src/Service/CsvCellNeutralizer.php',
    'administrator/components/com_loginguard/src/View/Adminaudit/HtmlView.php',
    'administrator/components/com_loginguard/tmpl/adminaudit/default.php',
    'administrator/components/com_loginguard/tmpl/about/default.php',
    'administrator/components/com_loginguard/tmpl/dashboard/default.php',
    'pkg_loginguard/pkg_loginguard.xml',
    'plugins/task/loginguardcleanup/loginguardcleanup.xml',
    'plugins/user/loginguard/loginguard.xml',
    'plugins/user/loginguard/sql/install.mysql.utf8.sql',
    'plugins/user/loginguard/sql/uninstall.mysql.utf8.sql',
    'plugins/user/loginguard/sql/updates/mysql/0.2.26.sql',
    'plugins/user/loginguard/sql/updates/mysql/0.2.27.sql',
    'scripts/validate.sh', 'tests/security_regressions.php', 'tests/v020_runtime_baseline.php',
    'updates/loginguard.xml',
];

exec('git cat-file -e ' . escapeshellarg($baseline . '^{commit}') . ' 2>&1', $unused, $status);
if ($status !== 0) {
    exec('git remote get-url origin 2>&1', $remoteOutput, $remoteStatus);
    if ($remoteStatus !== 0) {
        fwrite(STDERR, "Missing v0.2.20 baseline commit {$baseline} and origin is unavailable\n");
        exit(1);
    }

    passthru(
        'git fetch --no-tags --depth=1 origin ' . escapeshellarg($baseline) . ' 2>&1',
        $fetchStatus
    );
    if ($fetchStatus !== 0) {
        fwrite(STDERR, "Unable to fetch v0.2.20 baseline commit {$baseline}\n");
        exit(1);
    }
}

exec('git rev-parse ' . escapeshellarg($baseline . '^{commit}') . ' 2>&1', $resolved, $status);
if ($status !== 0 || ($resolved[0] ?? '') !== $baseline) {
    fwrite(STDERR, "Unable to verify exact v0.2.20 baseline commit {$baseline}\n");
    exit(1);
}

exec('git diff --name-only --diff-filter=ACDMRTUXB ' . escapeshellarg($baseline) . ' --', $changed, $status);
if ($status !== 0) {
    fwrite(STDERR, "Unable to compare the working tree with v0.2.20\n");
    exit(1);
}

$unexpected = array_values(array_diff($changed, $allowed));
if ($unexpected !== []) {
    fwrite(STDERR, "Non-dashboard runtime drift from v0.2.20:\n - " . implode("\n - ", $unexpected) . "\n");
    exit(1);
}

$dashboard = file_get_contents('administrator/components/com_loginguard/tmpl/dashboard/default.php');
foreach (['healthStatus', 'OperationalAudit', '#__loginguard_health'] as $forbidden) {
    if (str_contains($dashboard, $forbidden)) {
        fwrite(STDERR, "Dashboard introduces post-v0.2.20 backend dependency: {$forbidden}\n");
        exit(1);
    }
}

echo "v0.2.20 runtime baseline preserved; only metadata and dashboard presentation differ.\n";
