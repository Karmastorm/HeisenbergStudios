<?php
/**
 * Run from CLI to generate a password hash for inserting test users:
 *   php make_hash.php yourpassword
 */
if ($argc < 2) {
    echo "Usage: php make_hash.php <password>\n";
    exit(1);
}
echo password_hash($argv[1], PASSWORD_DEFAULT) . "\n";
