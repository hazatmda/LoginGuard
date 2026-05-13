#!/usr/bin/env bash
set -e

find plugins -name "*.php" -exec php -l {} \;

echo "Validation completed successfully"
