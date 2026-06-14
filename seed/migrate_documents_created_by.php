<?php
/*
|------------------------------------------------------------------------------
| Migration: add `created_by` to `documents` (idempotent)
|------------------------------------------------------------------------------
| - created_by : id of the user who created the document (FK → users.id)
|   Used for per-user permissions (e.g. praktikant may only edit/delete own).
|
| Run from CLI:  php seed/migrate_documents_created_by.php
*/

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db  = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS);
$pdo = $db->getConnection();

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

echo "Migrating documents table...\n";

safe($pdo, "ALTER TABLE documents ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL AFTER template_id");
safe($pdo, "ALTER TABLE documents ADD INDEX IF NOT EXISTS idx_documents_created_by (created_by)");

echo "  - created_by ready\n";
echo "Done.\n";
