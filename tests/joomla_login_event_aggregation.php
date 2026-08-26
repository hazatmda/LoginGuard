<?php

declare(strict_types=1);

/**
 * Match Joomla 5.4.6 CMSApplication::login()'s onUserLogin acceptance gate.
 *
 * @param array<int, mixed> $results
 */
function joomlaAcceptsUserLoginResults(array $results): bool
{
    return !in_array(false, $results, true);
}

$cases = [
    'listener absent' => [[], true],
    'explicit true' => [[true], true],
    'null result' => [[null], true],
    'non-strict false values' => [[0, '', null], true],
    'strict false' => [[true, false], false],
];

foreach ($cases as $name => [$results, $expected]) {
    $actual = joomlaAcceptsUserLoginResults($results);

    if ($actual !== $expected) {
        fwrite(STDERR, sprintf("%s: expected %s, got %s\n", $name, $expected ? 'accepted' : 'rejected', $actual ? 'accepted' : 'rejected'));
        exit(1);
    }
}

if (joomlaAcceptsUserLoginResults([]) !== joomlaAcceptsUserLoginResults([true])) {
    fwrite(STDERR, "An absent result and explicit true must be equivalent at Joomla's strict-false gate.\n");
    exit(1);
}

echo "Joomla user-login event aggregation validation completed successfully\n";
