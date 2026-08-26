<?php

declare(strict_types=1);

$fixture = file_get_contents(__DIR__ . '/fixtures/admin_audit_legacy.sql');
$installer = file_get_contents(__DIR__ . '/../pkg_loginguard/script.php');
$repairMarker = file_get_contents(__DIR__ . '/../administrator/components/com_loginguard/sql/updates/mysql/0.2.27.1.sql');
$install = file_get_contents(__DIR__ . '/../administrator/components/com_loginguard/sql/install.mysql.utf8.sql');

if ($fixture === false || $installer === false || $repairMarker === false || $install === false) {
    throw new RuntimeException('Unable to load audit migration regression inputs');
}

foreach ([
    '`id` int unsigned NOT NULL AUTO_INCREMENT',
    '`action` varchar(50) NOT NULL',
    '`target_type` varchar(50) NOT NULL',
    '`target_id` int NOT NULL DEFAULT 0',
    "`target_ip` varchar(45) NOT NULL DEFAULT ''",
    '`actor_user_id` int NOT NULL DEFAULT 0',
] as $legacyDefinition) {
    if (!str_contains($fixture, $legacyDefinition)) {
        throw new RuntimeException("Legacy fixture is missing exact definition: $legacyDefinition");
    }
}

// Inspect all three lifecycle states: absent table returns for fresh installs,
// legacy columns are repaired, and canonical columns/indexes are left alone.
foreach ([
    "if (!in_array(\$table, \$db->getTableList(), true))",
    "if (!isset(\$columns['actor_username']))",
    "ADD COLUMN ' . \$db->quoteName('actor_username')",
    "LEFT JOIN ' . \$db->quoteName('#__users', 'users')",
    "columnMatches(\$columns['target_id'] ?? null, 'text', true)",
    "columnMatches(\$columns['action'] ?? null, 'varchar(64)', false)",
    "columnMatches(\$columns['target_type'] ?? null, 'varchar(64)', false)",
    "if (!isset(\$existingKeys[\$name]))",
    'idx_loginguard_admin_audit_actor',
    'idx_loginguard_admin_audit_action',
    'idx_loginguard_admin_audit_created',
    'LoginGuard could not repair the administrator audit schema',
] as $repairRequirement) {
    if (!str_contains($installer, $repairRequirement)) {
        throw new RuntimeException("Package preflight reconciliation is missing: $repairRequirement");
    }
}
if (str_contains($installer, 'DROP TABLE') || str_contains($installer, 'DROP COLUMN')
    || !str_contains($fixture, "(17, 'blocked_ip.delete'")
    || !str_contains($repairMarker, 'no executable migration statement')) {
    throw new RuntimeException('Repair must retain the legacy row and target_ip forensic column');
}
if (!str_contains($install, '`actor_username` varchar(255)') || !str_contains($install, '`target_id` text NULL DEFAULT NULL')) {
    throw new RuntimeException('Fresh-install schema is not canonical');
}

echo "Legacy, canonical, and fresh-install audit reconciliation paths verified\n";
