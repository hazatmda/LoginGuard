<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/plugins/user/loginguard/src/Extension/LoginGuard.php');
if ($source === false) {
    throw new RuntimeException('Unable to read User LoginGuard source');
}

if (!str_contains($source, 'implements SubscriberInterface')) {
    throw new RuntimeException('User LoginGuard must use Joomla typed subscriber registration');
}

$expectedSubscriptions = [
    'onUserAuthorisation',
    'onUserAfterLogin',
    'onUserLoginFailure',
    'onUserAfterLogout',
];
foreach ($expectedSubscriptions as $eventName) {
    if (!preg_match("/'{$eventName}'\\s*=>\\s*'{$eventName}'/", $source)) {
        throw new RuntimeException("Missing typed subscription for {$eventName}");
    }
    if (!preg_match('/public function ' . $eventName . '\\(Event \\$event\\): void/', $source)) {
        throw new RuntimeException("{$eventName} must consume the typed event and return void");
    }
}

if (preg_match('/function\\s+onUserLogin\\s*\\(/', $source)) {
    throw new RuntimeException('LoginGuard must not contribute a result to Joomla onUserLogin aggregation');
}

$afterStart = strpos($source, 'public function onUserAfterLogin');
$failureStart = strpos($source, 'public function onUserLoginFailure', $afterStart);
$afterLogin = substr($source, $afterStart, $failureStart - $afterStart);
if (substr_count($afterLogin, "'status' => 'SUCCESS_LOGIN'") !== 1 || substr_count($afterLogin, '$this->storeAttempt(') !== 1) {
    throw new RuntimeException('Accepted primary login must record SUCCESS_LOGIN exactly once');
}
if (str_contains($afterLogin, 'addResult(')) {
    throw new RuntimeException('Post-login auditing must not alter event results');
}

foreach (['bootComponent(', 'redirect(', 'route(', 'com_users.mfa_checked'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        throw new RuntimeException("User login lifecycle contains routing/MFA side effect: {$forbidden}");
    }
}

echo "User LoginGuard uses routing-neutral Joomla typed event observers.\n";
