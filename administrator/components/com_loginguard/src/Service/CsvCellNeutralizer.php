<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Service;

defined('_JEXEC') or die;

final class CsvCellNeutralizer
{
    public static function neutralize(?string $value): string
    {
        $value = (string) $value;

        if (preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }
}
