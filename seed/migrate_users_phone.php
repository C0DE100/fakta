<?php
/*
|------------------------------------------------------------------------------
| Migration: add `phone` to `users` (idempotent)
|------------------------------------------------------------------------------
| - phone : user phone number (plaintext, optional)
|
| Run from CLI:  php seed/migrate_users_phone.php
*/

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db  = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS);
$pdo = $db->getConnection();

/** Run a statement, ignoring "already exists / duplicate" errors so re-runs are safe. */
function safe(PDO $pdo, string $sql): void
{
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        $msg = strtolower($e->getMessage());
        foreach (['duplicate column', 'duplicate key', 'already exists', 'check that column'] as $needle) {
            if (str_contains($msg, $needle)) {
                return;
            }
        }
        throw $e;
    }
}

echo "Migrating users table...\n";

safe($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL AFTER email");

echo "  - phone ready\n";
echo "Done.\n";
