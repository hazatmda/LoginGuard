<?php

$script = file_get_contents(dirname(__DIR__) . '/pkg_loginguard/script.php');
$disable = strpos($script, '$db->quoteName(\'enabled\') . \' = 0\'');
$uninstall = strpos($script, "uninstall('plugin', \$extensionId)");
if ($disable === false || $uninstall === false || $disable >= $uninstall) throw new RuntimeException('Legacy plugin must be disabled before uninstall');
if (substr_count($script, '$this->removeLegacyMfaPlugin();') < 2) throw new RuntimeException('Cleanup must run before and after package update');
if (substr_count($script, 'catch (\\Throwable $exception)') < 3) throw new RuntimeException('Cleanup must remain fail-safe');
echo "Legacy system plugin cleanup is disable-first and fail-safe.\n";
