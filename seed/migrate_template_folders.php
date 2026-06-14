<?php
/*
|------------------------------------------------------------------------------
| Migration: template folders (idempotent)
|------------------------------------------------------------------------------
| - Creates `template_folders` (optional grouping for templates).
| - Adds nullable `folder_id` to `templates`. NULL = ungrouped (root level).
| - Deleting a folder ungroups its templates (folder_id -> NULL); handled in the
|   API rather than a DB cascade so templates are never lost.
|
| Run from CLI:  php seed/migrate_template_folders.php
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

echo "Migrating template folders...\n";

safe($pdo, "
    CREATE TABLE IF NOT EXISTS template_folders (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id INT UNSIGNED NOT NULL,
        name       VARCHAR(255) NOT NULL,
        color      VARCHAR(20) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_template_folders_company (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "  - template_folders table ready\n";

safe($pdo, "ALTER TABLE templates ADD COLUMN IF NOT EXISTS folder_id INT UNSIGNED NULL AFTER company_id");
safe($pdo, "ALTER TABLE templates ADD INDEX IF NOT EXISTS idx_templates_folder (folder_id)");
echo "  - templates.folder_id ready\n";

echo "Done.\n";
