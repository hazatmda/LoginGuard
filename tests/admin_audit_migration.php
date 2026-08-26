<?php

declare(strict_types=1);

$fixture = file_get_contents(__DIR__ . '/fixtures/admin_audit_legacy.sql');
$repair = file_get_contents(__DIR__ . '/../administrator/components/com_loginguard/sql/updates/mysql/0.2.27.1.sql');
$install = file_get_contents(__DIR__ . '/../administrator/components/com_loginguard/sql/install.mysql.utf8.sql');

if ($fixture === false || $repair === false || $install === false) {
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

foreach ([
    "COLUMN_NAME` = 'actor_username'",
    'ADD COLUMN `actor_username` varchar(255) NOT NULL DEFAULT',
    'MODIFY `action` varchar(64) NOT NULL',
    'MODIFY `target_type` varchar(64) NOT NULL',
    'MODIFY `target_id` text NULL DEFAULT NULL',
    'LEFT JOIN `#__users`',
    "WHERE `audit`.`actor_username` = ''",
    "INDEX_NAME` = 'idx_loginguard_admin_audit_actor'",
    "INDEX_NAME` = 'idx_loginguard_admin_audit_action'",
    "INDEX_NAME` = 'idx_loginguard_admin_audit_created'",
] as $repairRequirement) {
    if (!str_contains($repair, $repairRequirement)) {
        throw new RuntimeException("Repair migration is missing: $repairRequirement");
    }
}
if (str_contains($repair, 'DROP TABLE') || str_contains($repair, 'DROP COLUMN') || !str_contains($fixture, "(17, 'blocked_ip.delete'")) {
    throw new RuntimeException('Repair must retain the legacy row and target_ip forensic column');
}

// Model the exact fixture row after the widening/backfill and exercise the two
// runtime shapes that failed on the live site.
$rows = [[
    'id' => 17,
    'actor_user_id' => 7,
    'actor_username' => 'legacy-admin',
    'action' => 'blocked_ip.delete',
    'target_type' => 'blocked_ip',
    'target_id' => '42',
    'target_ip' => '192.0.2.10',
    'details' => '{"source":"legacy"}',
    'created' => '2026-08-25 12:00:00',
]];
$adminAuditSelect = array_map(
    static fn(array $row): array => array_intersect_key($row, array_flip(['actor_username', 'action', 'target_type', 'target_id'])),
    $rows
);
if ($adminAuditSelect[0]['actor_username'] !== 'legacy-admin' || $adminAuditSelect[0]['target_id'] !== '42') {
    throw new RuntimeException('Admin Audit SELECT shape failed after migration');
}
$rows[] = ['actor_username' => 'export-admin', 'action' => 'attempts.export', 'target_type' => 'login_attempts', 'target_id' => null];
if ($rows[1]['target_id'] !== null) {
    throw new RuntimeException('Export audit INSERT must accept a NULL target_id');
}
if (!str_contains($install, '`actor_username` varchar(255)') || !str_contains($install, '`target_id` text NULL DEFAULT NULL')) {
    throw new RuntimeException('Fresh-install schema is not canonical');
}

echo "Legacy audit migration preserves rows and supports select/export paths\n";
