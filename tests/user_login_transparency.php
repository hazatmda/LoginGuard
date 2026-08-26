<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/plugins/user/loginguard/src/Extension/LoginGuard.php');
if ($source === false) throw new RuntimeException('Unable to read User LoginGuard source');

$afterStart = strpos($source, 'public function onUserAfterLogin');
$failureStart = strpos($source, 'public function onUserLoginFailure', $afterStart);
$afterLogin = substr($source, $afterStart, $failureStart - $afterStart);
if (substr_count($afterLogin, "'status' => 'SUCCESS_LOGIN'") !== 1 || substr_count($afterLogin, '$this->storeAttempt(') !== 1) {
    throw new RuntimeException('Accepted primary login must record SUCCESS_LOGIN exactly once');
}
if (!str_contains($afterLogin, '): void')) {
    throw new RuntimeException('Post-login observer must not contribute an authentication result');
}

$storeStart = strpos($source, 'private function storeAttempt');
$databaseStart = strpos($source, 'private function getDatabase', $storeStart);
$storeAttempt = substr($source, $storeStart, $databaseStart - $storeStart);
if (!str_contains($storeAttempt, 'try {') || !str_contains($storeAttempt, 'catch (Throwable $exception)')) {
    throw new RuntimeException('Post-login telemetry must remain fail-open');
}

foreach (['bootComponent(', 'redirect(', 'route(', 'com_users.mfa_checked'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        throw new RuntimeException("User login lifecycle contains routing/MFA side effect: $forbidden");
    }
}
if (preg_match('/function\s+onUserLogin\s*\(/', $source)) {
    throw new RuntimeException('Neutral onUserLogin observer must not be restored');
}

echo "User LoginGuard accepted-login path is fail-open and routing-neutral.\n";
