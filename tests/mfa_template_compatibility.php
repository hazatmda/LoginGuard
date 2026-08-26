<?php

define('_JEXEC', 1);

require_once __DIR__ . '/../administrator/components/com_loginguard/src/Service/AuditAlertService.php';

use LoginGuard\Component\LoginGuard\Administrator\Service\AuditAlertService;

$service = new AuditAlertService();
$reflection = new ReflectionClass($service);
$include = $reflection->getMethod('includeMissingMfaTemplateRows');
$replace = $reflection->getMethod('replaceAlertTemplateVariables');
$html = $reflection->getMethod('buildAlertHtmlBody');

$variables = [
    'mfa_method' => 'Email',
    'mfa_status' => 'MFA SUCCESS',
    'mfa_reason' => 'MFA Completed',
    'status' => 'SUCCESS LOGIN',
    'site_name' => 'Test Site',
];

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$cases = [
    'old success template' => ["Custom intro.\n\nUsername: {username}\n\nCustom footer.", $variables],
    'old failure template' => ["Failure intro.\n\nUsername: {username}\n\nFailure footer.", array_replace($variables, ['mfa_status' => 'MFA FAILED', 'mfa_reason' => 'Invalid MFA Code'])],
    'partial failure template' => ["Failure intro.\n\nCustom Method: {mfa_method}\nMFA Result: {mfa_reason}\n\nFailure footer.", array_replace($variables, ['mfa_status' => 'MFA FAILED', 'mfa_reason' => 'Invalid MFA Code'])],
    'complete template' => ["MFA Result: {mfa_reason}\nCustom Status: {mfa_status}\nCustom Method: {mfa_method}", $variables],
];

foreach ($cases as $name => [$savedTemplate, $caseVariables]) {
    $renderTemplate = $include->invoke($service, $savedTemplate, $caseVariables);
    $plain = $replace->invoke($service, $renderTemplate, $caseVariables);
    $renderedHtml = $html->invoke($service, 'TEST', $renderTemplate, $caseVariables, 'SUCCESS_LOGIN');

    foreach (['mfa_method', 'mfa_status', 'mfa_reason'] as $variable) {
        $value = $caseVariables[$variable];
        $assert(substr_count($plain, $value) === 1, "{$name} must include {$value} exactly once in plain text");
        $assert(substr_count($renderedHtml, $value) === 1, "{$name} must include {$value} exactly once in HTML");
    }
    $assert($savedTemplate === $cases[$name][0], "{$name} saved parameter must remain unchanged");
}

$nonMfaTemplate = "Custom intro.\n\nUsername: {username}\n\nCustom footer.";
$nonMfaVariables = array_fill_keys(array_keys($variables), '');
$assert(
    $include->invoke($service, $nonMfaTemplate, $nonMfaVariables) === $nonMfaTemplate,
    'non-MFA alerts must not gain synthetic MFA rows'
);

echo "MFA template compatibility checks passed.\n";
