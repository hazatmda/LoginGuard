<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/plugins/user/loginguard/src/Extension/LoginGuard.php');
if ($source === false) {
    throw new RuntimeException('Unable to read User LoginGuard source');
}

if (!str_contains($source, 'final class LoginGuard extends CMSPlugin') || str_contains($source, 'SubscriberInterface')) {
    throw new RuntimeException('User LoginGuard must retain the v0.2.20 legacy CMSPlugin event surface');
}

$signatures = [
    'public function onUserAuthorisation($response = null, $options = [])',
    'public function onUserLogin($user = [], $options = []): bool',
    'public function onUserAfterLogin($options = []): void',
    'public function onUserLoginFailure($response = []): void',
    'public function onUserLogout($user = [], $options = []): bool',
    'public function onUserAfterLogout($options = []): void',
];
foreach ($signatures as $signature) {
    if (!str_contains($source, $signature)) {
        throw new RuntimeException("Missing known-good lifecycle signature: {$signature}");
    }
}

foreach (['onUserLogin', 'onUserLogout'] as $method) {
    $start = strpos($source, "public function {$method}");
    $bodyStart = strpos($source, '{', $start);
    $end = strpos($source, '}', $bodyStart);
    if (trim(substr($source, $bodyStart + 1, $end - $bodyStart - 1)) !== 'return true;') {
        throw new RuntimeException("{$method} must remain exactly neutral");
    }
}

$afterStart = strpos($source, 'public function onUserAfterLogin');
$failureStart = strpos($source, 'public function onUserLoginFailure', $afterStart);
$afterLogin = substr($source, $afterStart, $failureStart - $afterStart);
if (substr_count($afterLogin, "'status' => 'SUCCESS_LOGIN'") !== 1 || substr_count($afterLogin, '$this->storeAttempt(') !== 1) {
    throw new RuntimeException('Accepted primary login must record SUCCESS_LOGIN exactly once');
}

$authorisation = substr($source, strpos($source, 'public function onUserAuthorisation'), strpos($source, 'public function onUserLogin') - strpos($source, 'public function onUserAuthorisation'));
if (!str_contains($authorisation, 'enforceBlockedIp') || !str_contains($authorisation, 'markAuthenticationResponseDenied')) {
    throw new RuntimeException('Legacy authorisation listener must preserve intentional blocked-IP denial');
}

foreach (['bootComponent(', 'redirect(', 'route(', 'com_users.mfa_checked'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        throw new RuntimeException("User login lifecycle contains routing/MFA side effect: {$forbidden}");
    }
}

echo "User LoginGuard retains the routing-neutral v0.2.20 lifecycle surface.\n";
