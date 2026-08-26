<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../plugins/user/loginguard/src/Extension/LoginGuard.php');
if ($source === false) {
    throw new RuntimeException('Unable to read User - LoginGuard source');
}

if (!preg_match(
    '/public function onUserAfterLogin\(\$options = \[\]\): void\s*\{(?<body>.*?)\n\s*\}/s',
    $source,
    $match
)) {
    throw new RuntimeException('Unable to locate onUserAfterLogin callback');
}

$bodyWithoutComments = preg_replace('~/\*.*?\*/|//[^\r\n]*~s', '', $match['body']);
if (trim((string) $bodyWithoutComments) !== '') {
    throw new RuntimeException('Isolation Candidate B must bypass all User LoginGuard post-login work');
}

echo "Isolation Candidate B User post-login no-op validation completed successfully\n";
