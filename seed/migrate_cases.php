<?php
/*
|------------------------------------------------------------------------------
| Migration: Предмети (cases) and related tables  — idempotent
|------------------------------------------------------------------------------
| Tables:
|   cases               one legal case (предмет) per row
|   case_parties        странки — our client(s) + opposing side(s), each with a
|                       role (својство, freetext). At least one 'client' party.
|   case_assignees      зададено на — employees assigned to work the case (m:n)
|   case_admin_numbers  административен број history (current + superseded)
|   case_counters       per (company, year) sequence for the предмет број
|
| Предмет број: {case_seq}/{YY}  → e.g. 1/26, 2/26. Resets each year.
| When archived, an /N suffix is appended: 5/26 → 5/26/1 (archive_seq, a
| per-company counter independent of the year).
|
| Search is served by a denormalized `search_text` column (case number +
| basis + party names/roles + opposing lawyers + current admin number),
| rebuilt on every write — MariaDB has no ngram fulltext, so tenant-scoped
| LIKE on this single column is the pragmatic, fast-enough path.
|
| Run from CLI:  php seed/migrate_cases.php
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
        foreach (['duplicate column', 'duplicate key', 'already exists', 'check that column', 'duplicate index'] as $needle) {
            if (str_contains($msg, $needle)) {
                return;
            }
        }
        throw $e;
    }
}

echo "Migrating cases (Предмети)...\n";

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
    created_by     INT UNSIGNED NULL,                           -- креиран од (employee)
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

safe($pdo, "
CREATE TABLE IF NOT EXISTS case_parties (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id      INT UNSIGNED NOT NULL,
    case_id         INT UNSIGNED NOT NULL,
    side            ENUM('client','opponent') NOT NULL,
    client_id       INT UNSIGNED NULL,                          -- set when side='client' (FK → clients.id)
    name            VARCHAR(255) NULL,                          -- opponent name (case-specific, not a client)
    entity_type     ENUM('individual','company') NULL,         -- opponent: физичко / правно лице
    opposing_lawyer VARCHAR(255) NULL,                          -- адвокат на спротивната странка
    role            VARCHAR(120) NOT NULL,                      -- својство во предметот (freetext)
    is_primary      TINYINT(1) NOT NULL DEFAULT 0,              -- the party shown on the grid card
    created_at      DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_cp_case    (case_id, side),
    KEY idx_cp_client  (company_id, client_id),
    KEY idx_cp_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

safe($pdo, "
CREATE TABLE IF NOT EXISTS case_assignees (
    company_id  INT UNSIGNED NOT NULL,
    case_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (case_id, user_id),
    KEY idx_ca_user (company_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

safe($pdo, "
CREATE TABLE IF NOT EXISTS case_admin_numbers (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id   INT UNSIGNED NOT NULL,
    case_id      INT UNSIGNED NOT NULL,
    admin_number VARCHAR(190) NOT NULL,                         -- административен број (official institution no.)
    is_current   TINYINT(1) NOT NULL DEFAULT 1,
    note         VARCHAR(255) NULL,                             -- optional: phase / institution
    created_at   DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_can_case    (case_id, is_current),
    KEY idx_can_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

safe($pdo, "
CREATE TABLE IF NOT EXISTS case_counters (
    company_id   INT UNSIGNED NOT NULL,
    counter_year SMALLINT UNSIGNED NOT NULL,
    last_seq     INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (company_id, counter_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "  - cases, case_parties, case_assignees, case_admin_numbers, case_counters ready\n";
echo "Done.\n";
