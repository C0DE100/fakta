<?php
/*
|------------------------------------------------------------------------------
| Migration: add soft-delete column to `clients` (idempotent)
|------------------------------------------------------------------------------
| - deleted_at : when set, the client is hidden from lists but kept in the DB
|                so historical invoices still resolve the client name.
|
| Run from CLI:  php seed/migrate_clients_soft_delete.php
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

echo "Migrating clients table (soft delete)...\n";

safe($pdo, "ALTER TABLE clients ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
safe($pdo, "ALTER TABLE clients ADD INDEX IF NOT EXISTS idx_clients_deleted_at (deleted_at)");

echo "  - deleted_at ready\n";
echo "Done.\n";
