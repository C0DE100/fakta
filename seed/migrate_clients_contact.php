<?php
/*
|------------------------------------------------------------------------------
| Migration: add contact + ownership columns to `clients` (idempotent)
|------------------------------------------------------------------------------
| - email      : client e-mail (plaintext, optional)
| - phone      : client phone (plaintext, optional)
| - created_by : id of the user who created the client (FK → users.id)
|
| Run from CLI:  php seed/migrate_clients_contact.php
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

echo "Migrating clients table...\n";

safe($pdo, "ALTER TABLE clients ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER manager");
safe($pdo, "ALTER TABLE clients ADD COLUMN IF NOT EXISTS phone VARCHAR(50) NULL AFTER email");
safe($pdo, "ALTER TABLE clients ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL");
safe($pdo, "ALTER TABLE clients ADD INDEX IF NOT EXISTS idx_clients_created_by (created_by)");

echo "  - email, phone, created_by ready\n";
echo "Done.\n";
