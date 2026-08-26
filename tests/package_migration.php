<?php

$package = $argv[1] ?? '';
if ($package === '' || !is_file($package)) {
    fwrite(STDERR, "Usage: php tests/package_migration.php <package.zip>\n");
    exit(2);
}

$outer = new ZipArchive();
if ($outer->open($package) !== true) {
    throw new RuntimeException('Cannot open package artifact');
}

$componentBytes = $outer->getFromName('com_loginguard.zip');
$outer->close();
if ($componentBytes === false) {
    throw new RuntimeException('Package does not contain com_loginguard.zip');
}

$temporary = tempnam(sys_get_temp_dir(), 'loginguard-component-');
file_put_contents($temporary, $componentBytes);
$component = new ZipArchive();
$component->open($temporary);

$required = [
    'loginguard.xml',
    'administrator/components/com_loginguard/sql/install.mysql.utf8.sql',
    'administrator/components/com_loginguard/sql/updates/mysql/0.2.27.sql',
    'administrator/components/com_loginguard/src/Model/AdminauditModel.php',
    'administrator/components/com_loginguard/src/View/Adminaudit/HtmlView.php',
    'administrator/components/com_loginguard/tmpl/adminaudit/default.php',
];
foreach ($required as $entry) {
    if ($component->locateName($entry) === false) {
        throw new RuntimeException("Component artifact missing $entry");
    }
}

$manifest = simplexml_load_string((string) $component->getFromName('loginguard.xml'));
$migration = (string) $component->getFromName('administrator/components/com_loginguard/sql/updates/mysql/0.2.27.sql');
$install = (string) $component->getFromName('administrator/components/com_loginguard/sql/install.mysql.utf8.sql');
$component->close();
unlink($temporary);

if ((string) $manifest->update->schemas->schemapath !== 'sql/updates/mysql'
    || (string) $manifest->install->sql->file !== 'sql/install.mysql.utf8.sql') {
    throw new RuntimeException('Component artifact does not wire fresh-install and upgrade schema paths');
}
foreach ([$migration, $install] as $sql) {
    if (!str_contains($sql, '#__loginguard_admin_audit') || !str_contains($sql, '`target_id` text')) {
        throw new RuntimeException('Component artifact schema does not create the lossless admin audit table');
    }
}

echo "Built package migration wiring verified successfully\n";
