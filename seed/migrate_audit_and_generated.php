<?php
/*
|------------------------------------------------------------------------------
| Migration: audit_log + generated_documents (idempotent)
|------------------------------------------------------------------------------
| - audit_log: accountability trail (who did what, when). Viewed by admins +
|   employees (not praktikant).
| - generated_documents: history of documents generated for a client (option a —
|   re-generatable records, values stored, no output file kept).
|
| Run from CLI:  php seed/migrate_audit_and_generated.php
| (On a host without CLI, run the two CREATE TABLE statements in phpMyAdmin.)
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
        foreach (['duplicate', 'already exists', 'check that column'] as $needle) {
            if (str_contains($msg, $needle)) {
                return;
            }
        }
        throw $e;
    }
}

echo "Migrating audit_log + generated_documents...\n";

safe($pdo, "
    CREATE TABLE IF NOT EXISTS audit_log (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id INT UNSIGNED NOT NULL,
        user_id    INT UNSIGNED NULL,
        user_name  VARCHAR(255) NULL,
        action     VARCHAR(64) NOT NULL,
        entity     VARCHAR(64) NULL,
        entity_id  INT UNSIGNED NULL,
        detail     VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_audit_company (company_id),
        KEY idx_audit_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "  - audit_log ready\n";

safe($pdo, "
    CREATE TABLE IF NOT EXISTS generated_documents (
        id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id    INT UNSIGNED NOT NULL,
        client_id     INT UNSIGNED NOT NULL,
        template_id   INT UNSIGNED NULL,
        document_id   INT UNSIGNED NULL,
        doc_name      VARCHAR(255) NOT NULL,
        template_name VARCHAR(255) NULL,
        kind          ENUM('editor','imported') NOT NULL DEFAULT 'editor',
        values_json   TEXT NULL,
        created_by    INT UNSIGNED NULL,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_gen_company (company_id),
        KEY idx_gen_client (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "  - generated_documents ready\n";

echo "Done.\n";
