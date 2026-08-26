<?php

$script = file_get_contents(dirname(__DIR__) . '/pkg_loginguard/script.php');
$disable = strpos($script, '$db->quoteName(\'enabled\') . \' = 0\'');
$detach = strpos($script, '$this->setPackageId($db, $extensionId, 0)', $disable);
$uninstall = strpos($script, "uninstall('plugin', \$extensionId)");
if ($disable === false || $detach === false || $uninstall === false || !($disable < $detach && $detach < $uninstall)) throw new RuntimeException('Legacy plugin must be disabled and detached before uninstall');
if (!str_contains($script, "getExtensionId(\$db, 'plugin', 'loginguardmfa', 'system')")) throw new RuntimeException('Cleanup must resolve only the exact legacy plugin');
if (substr_count(substr($script, $disable, $uninstall - $disable), "'extension_id'") < 1) throw new RuntimeException('Cleanup updates must be extension-ID scoped');
if (substr_count($script, '$this->removeLegacyMfaPlugin();') < 2) throw new RuntimeException('Cleanup must run before and after package update');
if (substr_count($script, 'catch (\\Throwable $exception)') < 3) throw new RuntimeException('Cleanup must remain fail-safe');
echo "Legacy system plugin cleanup is disable-first, detach-first, idempotent, and fail-safe.\n";
