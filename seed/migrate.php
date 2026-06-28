<?php
/*
|==============================================================================
| Факта — single, idempotent schema migration
|==============================================================================
| One file that builds the whole database up to the current schema. Safe to
| re-run any number of times: every table uses CREATE TABLE IF NOT EXISTS and
| later column additions go through safe() (duplicate/exists errors swallowed).
|
| Run from CLI:        php seed/migrate.php
| (No CLI on the host? Paste the CREATE TABLE statements into phpMyAdmin.)
|
| Tables, grouped:
|   Tenancy     companies, users
|   Clients     clients
|   Invoices    invoices, invoice_items
|   Documents   template_folders, templates, documents, generated_documents
|   Cases       cases, case_parties, case_assignees, case_admin_numbers,
|               case_counters, case_notes, case_todos, case_hearings, case_files
|   Audit       audit_log
|
| case_hearings carries calendar events of three kinds (kind column):
|   hearing = рочиште · trial = судење · meeting = состанок
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
        foreach (['duplicate column', 'duplicate key', 'already exists', 'check that column', 'duplicate index', "can't drop", 'unknown column'] as $needle) {
            if (str_contains($msg, $needle)) {
                return;
            }
        }
        throw $e;
    }
}

echo "Migrating database…\n";

/* ============================================================ Tenancy */

safe($pdo, "
CREATE TABLE IF NOT EXISTS companies (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NULL,
    address    TEXT NULL,
    phone      VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

safe($pdo, "
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id    INT UNSIGNED NULL,
    name          VARCHAR(255) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    phone         VARCHAR(50) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('super_admin','admin','employee','praktikant') NOT NULL DEFAULT 'employee',
    created_at    DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_company (company_id),
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

/* ============================================================ Clients */

safe($pdo, "
CREATE TABLE IF NOT EXISTS clients (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type           ENUM('company','individual') NOT NULL,
    company_name   TEXT NULL,
    headquarters   TEXT NULL,
    embs           TEXT NULL,
    edb            TEXT NULL,
    manager        TEXT NULL,
    email          VARCHAR(255) NULL,
    phone          VARCHAR(50) NULL,
    full_name      TEXT NULL,
    address        TEXT NULL,
    embg           TEXT NULL,
    id_card_number TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT current_timestamp(),
    company_id     INT UNSIGNED NOT NULL,
    created_by     INT UNSIGNED NULL,
    deleted_at     DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_clients_company (company_id),
    KEY idx_clients_created_by (created_by),
    KEY idx_clients_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ============================================================ Invoices */

safe($pdo, "
CREATE TABLE IF NOT EXISTS invoices (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    number     VARCHAR(20) NOT NULL,
    client_id  INT UNSIGNED NOT NULL,
    date       DATE NOT NULL DEFAULT curdate(),
    status     ENUM('испратена','платена') NOT NULL DEFAULT 'испратена',
    created_at DATETIME NOT NULL DEFAULT current_timestamp(),
    company_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY client_id (client_id),
    KEY idx_invoices_company (company_id),
    CONSTRAINT invoices_ibfk_1 FOREIGN KEY (client_id) REFERENCES clients (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

safe($pdo, "
CREATE TABLE IF NOT EXISTS invoice_items (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id INT UNSIGNED NOT NULL,
    name       VARCHAR(255) NOT NULL,
    price      DECIMAL(10,2) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY invoice_id (invoice_id),
    KEY idx_invoice_items_company (company_id),
    CONSTRAINT invoice_items_ibfk_1 FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

/* ============================================================ Documents */

safe($pdo, "
CREATE TABLE IF NOT EXISTS template_folders (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NULL,
    name       VARCHAR(255) NOT NULL,
    color      VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    updated_at TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_template_folders_company (company_id),
    KEY idx_template_folders_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

safe($pdo, "
CREATE TABLE IF NOT EXISTS templates (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    updated_at  TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    color       VARCHAR(20) NULL,
    company_id  INT UNSIGNED NOT NULL,
    created_by  INT UNSIGNED NULL,
    folder_id   INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_templates_company (company_id),
    KEY idx_templates_folder (folder_id),
    KEY idx_templates_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

safe($pdo, "
CREATE TABLE IF NOT EXISTS documents (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    kind        ENUM('editor','imported') NOT NULL DEFAULT 'editor',
    created_by  INT UNSIGNED NULL,
    name        VARCHAR(255) NOT NULL,
    pages       LONGTEXT NOT NULL COMMENT 'JSON array of page objects',
    variables   TEXT NOT NULL DEFAULT '[]',
    file_path   VARCHAR(255) NULL,
    orig_path   VARCHAR(255) NULL,
    file_ext    VARCHAR(8) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    updated_at  TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    company_id  INT UNSIGNED NOT NULL,
    is_split    INT NULL,
    PRIMARY KEY (id),
    KEY idx_template_id (template_id),
    KEY idx_documents_company (company_id),
    KEY idx_documents_created_by (created_by),
    CONSTRAINT fk_documents_template FOREIGN KEY (template_id) REFERENCES templates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

safe($pdo, "
CREATE TABLE IF NOT EXISTS generated_documents (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id    INT UNSIGNED NOT NULL,
    client_id     INT UNSIGNED NOT NULL,
    case_id       INT UNSIGNED NULL,
    template_id   INT UNSIGNED NULL,
    document_id   INT UNSIGNED NULL,
    doc_name      VARCHAR(255) NOT NULL,
    template_name VARCHAR(255) NULL,
    kind          ENUM('editor','imported') NOT NULL DEFAULT 'editor',
    values_json   TEXT NULL,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_gen_company (company_id),
    KEY idx_gen_client (client_id),
    KEY idx_gd_case (company_id, case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

/* ============================================================ Cases */

safe($pdo, "
CREATE TABLE IF NOT EXISTS cases (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id     INT UNSIGNED NOT NULL,
    case_seq       INT UNSIGNED NOT NULL,                       -- yearly running number (1,2,3…)
    case_year      SMALLINT UNSIGNED NOT NULL,                  -- full year, e.g. 2026 (displayed as 26)
    archive_seq    INT UNSIGNED NULL,                           -- /N suffix once archived (per-company)
    basis          VARCHAR(255) NULL,                           -- основ (title of the case)
    value_amount   DECIMAL(15,2) NULL,                          -- вредност
    value_currency ENUM('ден','евра') NOT NULL DEFAULT 'ден',
    search_text    TEXT NULL,                                   -- denormalized blob for LIKE search
    created_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT current_timestamp(),
    updated_at     DATETIME NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    archived_at    DATETIME NULL,
    deleted_at     DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_case_number  (company_id, case_year, case_seq),
    UNIQUE KEY uq_case_archive (company_id, archive_seq),
    KEY idx_cases_list       (company_id, deleted_at, archived_at, id),
    KEY idx_cases_basis      (company_id, basis),
    KEY idx_cases_created_by (company_id, created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
// Upgrade path: case lifecycle phase collapsed into the existing archived_at flag.
safe($pdo, "ALTER TABLE cases DROP KEY idx_cases_status");
safe($pdo, "ALTER TABLE cases DROP COLUMN status");
// Re-introduced as a lightweight in-progress flag (Активен / Во чекање), independent
// of archiving (archived_at still wins for the Архивиран state).
safe($pdo, "ALTER TABLE cases ADD COLUMN status ENUM('active','waiting') NOT NULL DEFAULT 'active' AFTER value_currency");
// Card header colour, picked from a fixed palette (see CaseFile::COLORS).
safe($pdo, "ALTER TABLE cases ADD COLUMN color VARCHAR(20) NULL AFTER status");

safe($pdo, "
CREATE TABLE IF NOT EXISTS case_parties (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id              INT UNSIGNED NOT NULL,
    case_id                 INT UNSIGNED NOT NULL,
    side                    ENUM('client','opponent') NOT NULL,
    client_id               INT UNSIGNED NULL,                  -- set when side='client' (FK → clients.id)
    name                    VARCHAR(255) NULL,                  -- opponent name (case-specific, not a client)
    opposing_representative VARCHAR(255) NULL,                  -- застапник на спротивната странка
    role                    VARCHAR(120) NOT NULL,              -- својство во предметот (freetext)
    is_primary              TINYINT(1) NOT NULL DEFAULT 0,      -- the party shown on the grid card
    created_at              DATETIME NOT NULL DEFAULT current_timestamp(),
    deleted_at              DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cp_case    (case_id, side),
    KEY idx_cp_client  (company_id, client_id),
    KEY idx_cp_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
// Upgrade path: dropped the opponent entity-type selector; адвокат → застапник.
safe($pdo, "ALTER TABLE case_parties DROP COLUMN entity_type");
safe($pdo, "ALTER TABLE case_parties CHANGE COLUMN opposing_lawyer opposing_representative VARCHAR(255) NULL");
safe($pdo, "ALTER TABLE case_parties ADD COLUMN deleted_at DATETIME NULL");

safe($pdo, "
CREATE TABLE IF NOT EXISTS case_assignees (
    company_id  INT UNSIGNED NOT NULL,
    case_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT current_timestamp(),
    deleted_at  DATETIME NULL,
    PRIMARY KEY (case_id, user_id),
    KEY idx_ca_user (company_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
safe($pdo, "ALTER TABLE case_assignees ADD COLUMN deleted_at DATETIME NULL");

safe($pdo, "
CREATE TABLE IF NOT EXISTS case_admin_numbers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    case_id         INT UNSIGNED NOT NULL,
    admin_number    VARCHAR(190) NOT NULL,                      -- административен број (official institution no.)
    is_current      TINYINT(1) NOT NULL DEFAULT 1,
    official_person VARCHAR(255) NULL,                          -- службено лице (судија/нотар/извршител/службеник)
    created_at      DATETIME NOT NULL DEFAULT current_timestamp(),
    deleted_at      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_can_case    (case_id, is_current),
    KEY idx_can_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
// Upgrade path: the admin-number note is now used for службено лице.
safe($pdo, "ALTER TABLE case_admin_numbers CHANGE COLUMN note official_person VARCHAR(255) NULL");
safe($pdo, "ALTER TABLE case_admin_numbers ADD COLUMN deleted_at DATETIME NULL");

safe($pdo, "
CREATE TABLE IF NOT EXISTS case_counters (
    company_id   INT UNSIGNED NOT NULL,
    counter_year SMALLINT UNSIGNED NOT NULL,
    last_seq     INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (company_id, counter_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

safe($pdo, "
CREATE TABLE IF NOT EXISTS case_notes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    case_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL,
    body       TEXT NOT NULL,
    note_type  VARCHAR(20) NOT NULL DEFAULT 'general',
    is_pinned  TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT current_timestamp(),
    updated_at DATETIME NULL ON UPDATE current_timestamp(),
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cn_case    (case_id, created_at),
    KEY idx_cn_company (company_id),
    KEY idx_cn_pinned  (case_id, is_pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
safe($pdo, "ALTER TABLE case_notes ADD COLUMN deleted_at DATETIME NULL");

safe($pdo, "
CREATE TABLE IF NOT EXISTS case_todos (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id   INT UNSIGNED NOT NULL,
    case_id      INT UNSIGNED NOT NULL,
    title        VARCHAR(500) NOT NULL,
    status       VARCHAR(20) NOT NULL DEFAULT 'open',
    is_done      TINYINT(1) NOT NULL DEFAULT 0,
    due_date     DATE NULL,
    assigned_to  INT UNSIGNED NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT current_timestamp(),
    completed_at DATETIME NULL,
    deleted_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ct_case     (case_id, is_done),
    KEY idx_ct_company  (company_id),
    KEY idx_ct_assignee (company_id, assigned_to),
    KEY idx_ct_status   (case_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
safe($pdo, "ALTER TABLE case_todos ADD COLUMN deleted_at DATETIME NULL");

// Calendar events on a case: рочишта, судења и состаноци (see `kind`).
safe($pdo, "
CREATE TABLE IF NOT EXISTS case_hearings (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    case_id    INT UNSIGNED NOT NULL,
    kind       ENUM('hearing','trial','meeting','other') NOT NULL DEFAULT 'hearing',
    title      VARCHAR(255) NOT NULL,
    hearing_at DATETIME NOT NULL,
    ends_at    DATETIME NULL,
    location   VARCHAR(255) NULL,
    note       TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT current_timestamp(),
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ch_case    (case_id, hearing_at),
    KEY idx_ch_company (company_id, hearing_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
// Upgrade path for databases created before the calendar (kind column).
safe($pdo, "ALTER TABLE case_hearings ADD COLUMN kind ENUM('hearing','trial','meeting','other') NOT NULL DEFAULT 'hearing' AFTER case_id");
safe($pdo, "ALTER TABLE case_hearings MODIFY COLUMN kind ENUM('hearing','trial','meeting','other') NOT NULL DEFAULT 'hearing'");
safe($pdo, "ALTER TABLE case_hearings ADD COLUMN deleted_at DATETIME NULL");
safe($pdo, "ALTER TABLE case_hearings ADD COLUMN ends_at DATETIME NULL AFTER hearing_at");

// Personal calendar events (NOT tied to a case): client meetings, reminders,
// anything the user names. Owned by user_id; only the owner manages them.
safe($pdo, "
CREATE TABLE IF NOT EXISTS calendar_events (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,                                  -- owner / creator
    kind       ENUM('hearing','trial','meeting','other','private') NOT NULL DEFAULT 'meeting',
    title      VARCHAR(255) NOT NULL,
    starts_at  DATETIME NOT NULL,
    ends_at    DATETIME NULL,
    location   VARCHAR(255) NULL,
    note       TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_ce_company (company_id, starts_at),
    KEY idx_ce_user    (company_id, user_id, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
safe($pdo, "ALTER TABLE calendar_events ADD COLUMN ends_at DATETIME NULL AFTER starts_at");
safe($pdo, "ALTER TABLE calendar_events MODIFY COLUMN kind ENUM('hearing','trial','meeting','other','private') NOT NULL DEFAULT 'meeting'");

safe($pdo, "
CREATE TABLE IF NOT EXISTS case_files (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id  INT UNSIGNED NOT NULL,
    case_id     INT UNSIGNED NOT NULL,
    orig_name   VARCHAR(255) NOT NULL,
    stored_rel  VARCHAR(255) NOT NULL,
    ext         VARCHAR(12) NULL,
    size_bytes  INT UNSIGNED NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT current_timestamp(),
    deleted_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cf_case    (case_id),
    KEY idx_cf_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
safe($pdo, "ALTER TABLE case_files ADD COLUMN deleted_at DATETIME NULL");

/* ============================================================ Audit */

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
    created_at DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_audit_company (company_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

echo "Done. Schema is up to date.\n";
