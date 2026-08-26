<?php

$source = file_get_contents(dirname(__DIR__) . '/plugins/user/loginguard/src/Extension/LoginGuard.php');
$start = strpos($source, 'private function normaliseFailureReason');
$end = strpos($source, 'private function detectBrowser', $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Failure-reason normalizer could not be located');
}
$normalizer = substr($source, $start, $end - $start);
if (!str_contains($normalizer, "'IP_BLOCKED'")) {
    throw new RuntimeException('IP_BLOCKED is not preserved by failure-reason normalization');
}

$enforcementStart = strpos($source, 'private function enforceBlockedIp');
$enforcementEnd = strpos($source, 'private function findActiveBlock', $enforcementStart);
$enforcement = substr($source, $enforcementStart, $enforcementEnd - $enforcementStart);
if (!str_contains($enforcement, "'status' => 'BLOCKED_LOGIN'") || !str_contains($enforcement, "'reason' => 'IP_BLOCKED'")) {
    throw new RuntimeException('Blocked-IP denial no longer emits BLOCKED_LOGIN with IP_BLOCKED');
}
foreach (['MFA_FAILED', 'MFA_TRY_LIMIT', 'MFA_INVALID_METHOD'] as $removed) {
    if (str_contains($normalizer, $removed)) {
        throw new RuntimeException("Retired MFA reason remains allowed: {$removed}");
    }
}
echo "Blocked-IP enforcement reason remains normalized as IP_BLOCKED.\n";
