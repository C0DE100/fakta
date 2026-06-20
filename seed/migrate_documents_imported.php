<?php
/*
|------------------------------------------------------------------------------
| Migration: imported documents (Типски Документи → imported [placeholder] files)
|------------------------------------------------------------------------------
| Adds columns to `documents` so a document can be an uploaded file (.docx/.doc/
| .pdf) holding [name]/[number] placeholders, instead of a Quill editor doc:
|
| - kind      : 'editor' (normal Quill doc) | 'imported' (uploaded file)
| - file_path : relative path of the fillable .docx MASTER (under uploads/)
| - orig_path : relative path of the original upload (esp. for .doc/.pdf)
| - file_ext  : desired OUTPUT extension ('docx' | 'doc' | 'pdf')
|
| The existing `variables` JSON column is reused to store the detected
| placeholder list for imported docs; `pages` stays '[]'.
|
| Run from CLI:  php seed/migrate_documents_imported.php
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

echo "Migrating documents table (imported files)...\n";

safe($pdo, "ALTER TABLE documents ADD COLUMN IF NOT EXISTS kind ENUM('editor','imported') NOT NULL DEFAULT 'editor' AFTER template_id");
safe($pdo, "ALTER TABLE documents ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) NULL AFTER variables");
safe($pdo, "ALTER TABLE documents ADD COLUMN IF NOT EXISTS orig_path VARCHAR(255) NULL AFTER file_path");
safe($pdo, "ALTER TABLE documents ADD COLUMN IF NOT EXISTS file_ext VARCHAR(8) NULL AFTER orig_path");

echo "  - kind / file_path / orig_path / file_ext ready\n";
echo "Done.\n";
