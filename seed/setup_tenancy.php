<?php
/*
|------------------------------------------------------------------------------
| Tenancy setup / migration (idempotent — safe to re-run)
|------------------------------------------------------------------------------
| - Creates `companies` and `users` tables.
| - Adds `company_id` to every data table.
| - WIPES existing data (clients/invoices/invoice_items/templates/documents).
| - Seeds the global super-admin account.
|
| Run from CLI:  php seed/setup_tenancy.php
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
        $ignorable = ['duplicate column', 'duplicate key', 'already exists', 'check that column'];
        foreach ($ignorable as $needle) {
            if (str_contains($msg, $needle)) {
                return;
            }
        }
        throw $e;
    }
}

echo "Setting up tenancy...\n";

/*
|--------------------------------------------------------------------------
| 1. companies
|--------------------------------------------------------------------------
*/
safe($pdo, "
    CREATE TABLE IF NOT EXISTS companies (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name       VARCHAR(255) NOT NULL,
        email      VARCHAR(255) NULL,
        address    TEXT NULL,
        phone      VARCHAR(50) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "  - companies table ready\n";

/*
|--------------------------------------------------------------------------
| 2. users
|--------------------------------------------------------------------------
*/
safe($pdo, "
    CREATE TABLE IF NOT EXISTS users (
        id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id    INT UNSIGNED NULL,
        name          VARCHAR(255) NOT NULL,
        email         VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role          ENUM('super_admin','admin','employee','praktikant') NOT NULL DEFAULT 'employee',
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_users_email (email),
        KEY idx_users_company (company_id),
        CONSTRAINT fk_users_company FOREIGN KEY (company_id)
            REFERENCES companies (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
// Keep the role enum current on installs where `users` already existed.
safe($pdo, "ALTER TABLE users MODIFY role ENUM('super_admin','admin','employee','praktikant') NOT NULL DEFAULT 'employee'");
echo "  - users table ready\n";

/*
|--------------------------------------------------------------------------
| 3. Wipe existing tenant data
|--------------------------------------------------------------------------
*/
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
foreach (['invoice_items', 'invoices', 'clients', 'documents', 'templates'] as $t) {
    safe($pdo, "TRUNCATE TABLE {$t}");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
echo "  - data tables wiped\n";

/*
|--------------------------------------------------------------------------
| 4. Add company_id to every data table
|--------------------------------------------------------------------------
| MariaDB supports ADD COLUMN/INDEX IF NOT EXISTS; safe() also guards re-runs.
*/
foreach (['clients', 'invoices', 'invoice_items', 'templates', 'documents'] as $t) {
    safe($pdo, "ALTER TABLE {$t} ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NOT NULL");
    safe($pdo, "ALTER TABLE {$t} ADD INDEX IF NOT EXISTS idx_{$t}_company (company_id)");
}
echo "  - company_id added to all data tables\n";

/*
|--------------------------------------------------------------------------
| 5. Seed the super-admin
|--------------------------------------------------------------------------
*/
$superEmail    = 'jovanovski.a74@gmail.com';
$superPassword = 'Fakta2026!'; // temporary — change after first login

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$superEmail]);

if ($stmt->fetchColumn()) {
    echo "  - super-admin already exists ({$superEmail})\n";
} else {
    $stmt = $pdo->prepare("
        INSERT INTO users (company_id, name, email, password_hash, role)
        VALUES (NULL, ?, ?, ?, 'super_admin')
    ");
    $stmt->execute([
        'Super Admin',
        $superEmail,
        password_hash($superPassword, PASSWORD_DEFAULT),
    ]);
    echo "  - super-admin created: {$superEmail} / {$superPassword}\n";
}

echo "Done.\n";
