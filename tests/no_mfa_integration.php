<?php

$root = dirname(__DIR__);
$runtimeRoots = [
    $root . '/administrator',
    $root . '/plugins/user',
    $root . '/plugins/task',
];
$forbidden = ['MFA_PENDING', 'MFA_FAILED', 'MFA_SUCCESS', 'MFA_TRY_LIMIT', '#__user_mfa', 'record_id', 'com_users.mfa_checked', 'com_users.mandatory_mfa_setup', 'com_users.return_url'];
foreach ($runtimeRoots as $runtimeRoot) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($runtimeRoot));
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        $contents = file_get_contents($file->getPathname());
        foreach ($forbidden as $token) {
            if (str_contains($contents, $token)) throw new RuntimeException("Active MFA token remains: {$token} in {$file->getPathname()}");
        }
    }
}
if (is_dir($root . '/plugins/system/loginguardmfa')) throw new RuntimeException('Retired system plugin directory remains');
$plugin = file_get_contents($root . '/plugins/user/loginguard/src/Extension/LoginGuard.php');
if (!preg_match("/'status' => 'SUCCESS_LOGIN'/", $plugin)) throw new RuntimeException('Immediate success audit missing');
echo "No active LoginGuard MFA integration remains; primary success is audited immediately.\n";
