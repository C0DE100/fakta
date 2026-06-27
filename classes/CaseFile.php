<?php

require_once __DIR__ . '/Database.php';

/**
 * Предмети (legal cases).
 *
 * Number scheme: {case_seq}/{YY}, resetting per year (1/26, 2/26 … 1/27).
 * On archive, an independent per-company /N suffix is appended (5/26 → 5/26/1).
 *
 * Each case has парти (case_parties): at least one of side='client' (linked to
 * a real clients row) and any number of side='opponent' (case-specific, stored
 * inline). One party per side is flagged is_primary — that's what the grid card
 * shows. Search rides on the denormalized cases.search_text column, rebuilt on
 * every write.
 */
class CaseFile
{
    private Database $db;
    private const PER_PAGE = 12;

    /** Allowed to-do workflow statuses. */
    public const TODO_STATUSES = ['open', 'in_progress', 'waiting', 'done', 'declined'];

    /** Allowed note types. */
    public const NOTE_TYPES = ['general', 'call', 'meeting', 'important'];

    /** Calendar event kinds: рочиште / состанок. ('trial' kept only for legacy rows; no longer assignable.) */
    public const HEARING_KINDS = ['hearing', 'meeting'];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /* ---------------------------------------------------------------------
     | SQL fragment: the displayed предмет број, e.g. "5/26" or "5/26/1".
     * ------------------------------------------------------------------- */
    private const CASE_NUMBER_SQL =
        "CONCAT(c.case_seq, '/', LPAD(MOD(c.case_year, 100), 2, '0'),
                IF(c.archive_seq IS NULL, '', CONCAT('/', c.archive_seq)))";

    /* =====================================================================
     | Number generation
     * =================================================================== */

    /**
     * Atomically reserve the next yearly sequence for a company. Uses the
     * INSERT … ON DUPLICATE KEY + LAST_INSERT_ID() trick so concurrent inserts
     * never collide on the same предмет број.
     */
    private function nextCaseSeq(int $companyId, int $year): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO case_counters (company_id, counter_year, last_seq)
             VALUES (:c, :y, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE last_seq = LAST_INSERT_ID(last_seq + 1)"
        );
        $stmt->execute([':c' => $companyId, ':y' => $year]);
        return (int) $this->db->lastInsertId();
    }

    /* =====================================================================
     | Create
     * =================================================================== */

    /**
     * Create a case with its parties, assignees and (optional) admin number.
     *
     * $data = [
     *   'basis'          => string,
     *   'value_amount'   => float|null,
     *   'value_currency' => 'ден'|'евра',
     *   'admin_number'   => string|null,
     *   'parties'        => [ ['side','client_id','name','opposing_representative','role'], … ],
     *   'assignees'      => [userId, …],
     * ]
     * Returns the new case id. Throws on a missing client party.
     */
    public function create(int $companyId, array $data, ?int $createdBy): int
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $caseId = $this->insertCaseTx($companyId, $data, $createdBy);
            $pdo->commit();
            return $caseId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Bulk-create cases inside ONE transaction (CSV import). Rows must already
     * be validated/resolved (client_id set on the client party). All-or-nothing:
     * any failure rolls the whole batch back. Returns ['imported' => int].
     */
    public function importBatch(int $companyId, array $rows, ?int $createdBy): array
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        $imported = 0;
        try {
            foreach ($rows as $data) {
                $this->insertCaseTx($companyId, $data, $createdBy);
                $imported++;
            }
            $pdo->commit();
            return ['imported' => $imported];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Insert one case + parties/assignees/admin number. ASSUMES an active
     * transaction, so it can be shared by create() and importBatch().
     */
    private function insertCaseTx(int $companyId, array $data, ?int $createdBy): int
    {
        $parties = $this->normalizeParties($data['parties'] ?? []);
        if (!$this->hasClientParty($parties)) {
            throw new InvalidArgumentException('Предметот мора да има барем една странка-клиент.');
        }

        $pdo  = $this->db->getConnection();
        $year = (int) date('Y');
        $seq  = $this->nextCaseSeq($companyId, $year);

        $stmt = $pdo->prepare(
            "INSERT INTO cases (company_id, case_seq, case_year, basis, value_amount, value_currency, created_by, created_at)
             VALUES (:cid, :seq, :year, :basis, :amount, :currency, :by, NOW())"
        );
        $stmt->execute([
            ':cid'      => $companyId,
            ':seq'      => $seq,
            ':year'     => $year,
            ':basis'    => $this->nz($data['basis'] ?? null),
            ':amount'   => $this->money($data['value_amount'] ?? null),
            ':currency' => ($data['value_currency'] ?? 'ден') === 'евра' ? 'евра' : 'ден',
            ':by'       => $createdBy,
        ]);
        $caseId = (int) $pdo->lastInsertId();

        $this->insertParties($companyId, $caseId, $parties);
        $this->replaceAssignees($companyId, $caseId, $data['assignees'] ?? []);

        $adminNo = trim((string) ($data['admin_number'] ?? ''));
        if ($adminNo !== '') {
            $this->addAdminNumberRaw($companyId, $caseId, $adminNo, $data['official_person'] ?? null);
        }

        $this->rebuildSearchText($companyId, $caseId);
        return $caseId;
    }

    /* =====================================================================
     | Update (core fields + parties + assignees)
     * =================================================================== */

    public function update(int $companyId, int $caseId, array $data): bool
    {
        $parties = $this->normalizeParties($data['parties'] ?? []);
        if (!$this->hasClientParty($parties)) {
            throw new InvalidArgumentException('Предметот мора да има барем една странка-клиент.');
        }

        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE cases
                    SET basis = :basis, value_amount = :amount, value_currency = :currency
                  WHERE id = :id AND company_id = :cid AND deleted_at IS NULL"
            );
            $stmt->execute([
                ':basis'    => $this->nz($data['basis'] ?? null),
                ':amount'   => $this->money($data['value_amount'] ?? null),
                ':currency' => ($data['value_currency'] ?? 'ден') === 'евра' ? 'евра' : 'ден',
                ':id'       => $caseId,
                ':cid'      => $companyId,
            ]);

            // Replace parties wholesale (simplest correct approach for a small set).
            $pdo->prepare("DELETE FROM case_parties WHERE case_id = :id AND company_id = :cid")
                ->execute([':id' => $caseId, ':cid' => $companyId]);
            $this->insertParties($companyId, $caseId, $parties);

            $this->replaceAssignees($companyId, $caseId, $data['assignees'] ?? []);
            $this->rebuildSearchText($companyId, $caseId);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /* =====================================================================
     | Listing (paginated, filtered)
     * =================================================================== */

    /**
     * Paginated case list for a company.
     * $filters = ['search','status'('active'|'archived'),'assignee_id','created_by','sort']
     * Returns ['data','total','pages','page'].
     */
    public function getListPaged(int $companyId, array $filters): array
    {
        $conds  = ['c.company_id = :cid', 'c.deleted_at IS NULL'];
        $params = [':cid' => $companyId];

        $status = ($filters['status'] ?? 'active') === 'archived' ? 'archived' : 'active';
        $conds[] = $status === 'archived' ? 'c.archived_at IS NOT NULL' : 'c.archived_at IS NULL';

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conds[]        = 'c.search_text LIKE :q';
            $params[':q']   = '%' . $search . '%';
        }

        if (!empty($filters['created_by'])) {
            $conds[]              = 'c.created_by = :cby';
            $params[':cby']       = (int) $filters['created_by'];
        }

        if (!empty($filters['assignee_id'])) {
            $conds[]               = 'EXISTS (SELECT 1 FROM case_assignees ca WHERE ca.case_id = c.id AND ca.user_id = :aid)';
            $params[':aid']        = (int) $filters['assignee_id'];
        }

        $where = implode(' AND ', $conds);

        $order = ($filters['sort'] ?? '') === 'oldest'
            ? 'c.case_year ASC, c.case_seq ASC'
            : 'c.case_year DESC, c.case_seq DESC';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM cases c WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page   = max(1, (int) ($filters['page'] ?? 1));
        $pages  = max(1, (int) ceil($total / self::PER_PAGE));
        $offset = ($page - 1) * self::PER_PAGE;

        $sql = "SELECT
                    c.id, c.case_seq, c.case_year, c.archive_seq, c.basis,
                    c.value_amount, c.value_currency, c.archived_at, c.created_at,
                    " . self::CASE_NUMBER_SQL . " AS case_number,
                    pc.role AS client_role,
                    COALESCE(NULLIF(pc.name, ''),
                             CASE WHEN cl.type = 'company' THEN cl.company_name ELSE cl.full_name END
                    ) AS client_name,
                    pc.client_id AS client_id,
                    po.role AS opponent_role,
                    po.name AS opponent_name,
                    an.admin_number AS admin_number,
                    (SELECT MIN(h.hearing_at) FROM case_hearings h
                       WHERE h.case_id = c.id AND h.deleted_at IS NULL AND h.hearing_at >= NOW()) AS next_hearing,
                    (SELECT COUNT(*) FROM case_files cf WHERE cf.case_id = c.id AND cf.deleted_at IS NULL) AS doc_count,
                    u.name AS created_by_name
                FROM cases c
                LEFT JOIN case_parties pc ON pc.case_id = c.id AND pc.side = 'client'   AND pc.is_primary = 1 AND pc.deleted_at IS NULL
                LEFT JOIN clients cl      ON cl.id = pc.client_id
                LEFT JOIN case_parties po ON po.case_id = c.id AND po.side = 'opponent' AND po.is_primary = 1 AND po.deleted_at IS NULL
                LEFT JOIN case_admin_numbers an ON an.case_id = c.id AND an.is_current = 1 AND an.deleted_at IS NULL
                LEFT JOIN users u         ON u.id = c.created_by
                WHERE {$where}
                ORDER BY {$order}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  self::PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,        PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        // Attach assignee avatars per case in one extra query (avoids N+1).
        $this->attachAssignees($companyId, $rows);
        $this->attachParties($companyId, $rows);

        return ['data' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /* =====================================================================
     | Single case (full detail)
     * =================================================================== */

    public function getById(int $companyId, int $caseId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, " . self::CASE_NUMBER_SQL . " AS case_number, u.name AS created_by_name
             FROM cases c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.id = :id AND c.company_id = :cid AND c.deleted_at IS NULL"
        );
        $stmt->execute([':id' => $caseId, ':cid' => $companyId]);
        $case = $stmt->fetch();
        if (!$case) {
            return null;
        }

        // Parties (resolve linked client names live).
        $pstmt = $this->db->prepare(
            "SELECT p.id, p.side, p.client_id, p.opposing_representative, p.role, p.is_primary,
                    COALESCE(NULLIF(p.name, ''),
                             CASE WHEN cl.type = 'company' THEN cl.company_name ELSE cl.full_name END
                    ) AS name,
                    cl.type AS client_type
             FROM case_parties p
             LEFT JOIN clients cl ON cl.id = p.client_id
             WHERE p.case_id = :id AND p.company_id = :cid AND p.deleted_at IS NULL
             ORDER BY p.side, p.is_primary DESC, p.id"
        );
        $pstmt->execute([':id' => $caseId, ':cid' => $companyId]);
        $case['parties'] = $pstmt->fetchAll();

        // Assignees.
        $astmt = $this->db->prepare(
            "SELECT u.id, u.name FROM case_assignees ca
             JOIN users u ON u.id = ca.user_id
             WHERE ca.case_id = :id AND ca.company_id = :cid AND ca.deleted_at IS NULL
             ORDER BY u.name"
        );
        $astmt->execute([':id' => $caseId, ':cid' => $companyId]);
        $case['assignees'] = $astmt->fetchAll();

        // Admin-number history (current first).
        $nstmt = $this->db->prepare(
            "SELECT id, admin_number, is_current, official_person, created_at
             FROM case_admin_numbers
             WHERE case_id = :id AND company_id = :cid AND deleted_at IS NULL
             ORDER BY is_current DESC, created_at DESC, id DESC"
        );
        $nstmt->execute([':id' => $caseId, ':cid' => $companyId]);
        $case['admin_numbers'] = $nstmt->fetchAll();

        return $case;
    }

    /* =====================================================================
     | Archive / unarchive
     * =================================================================== */

    /** Archive a case: stamp archived_at and assign the next per-company /N suffix. */
    public function archive(int $companyId, int $caseId): bool
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            // Lock the company's archived rows to serialize the suffix allocation.
            $maxStmt = $pdo->prepare(
                "SELECT COALESCE(MAX(archive_seq), 0) + 1
                 FROM cases WHERE company_id = :cid FOR UPDATE"
            );
            $maxStmt->execute([':cid' => $companyId]);
            $next = (int) $maxStmt->fetchColumn();

            $upd = $pdo->prepare(
                "UPDATE cases SET archived_at = NOW(), archive_seq = :seq
                 WHERE id = :id AND company_id = :cid AND deleted_at IS NULL AND archived_at IS NULL"
            );
            $upd->execute([':seq' => $next, ':id' => $caseId, ':cid' => $companyId]);
            $changed = $upd->rowCount() > 0;

            if ($changed) {
                $this->rebuildSearchText($companyId, $caseId);
            }
            $pdo->commit();
            return $changed;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Re-open an archived case. Releases its archive suffix. */
    public function unarchive(int $companyId, int $caseId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE cases SET archived_at = NULL, archive_seq = NULL
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL AND archived_at IS NOT NULL"
        );
        $stmt->execute([':id' => $caseId, ':cid' => $companyId]);
        $changed = $stmt->rowCount() > 0;
        if ($changed) {
            $this->rebuildSearchText($companyId, $caseId);
        }
        return $changed;
    }

    /* =====================================================================
     | Soft delete / trash
     * =================================================================== */

    /** Child tables that carry their own deleted_at, soft-deleted/restored together with the case. */
    private const SOFT_DELETE_CHILD_TABLES = [
        'case_parties', 'case_assignees', 'case_admin_numbers',
        'case_notes', 'case_todos', 'case_hearings', 'case_files',
    ];

    public function softDelete(int $companyId, int $caseId): bool
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE cases SET deleted_at = NOW()
                 WHERE id = :id AND company_id = :cid AND deleted_at IS NULL"
            );
            $stmt->execute([':id' => $caseId, ':cid' => $companyId]);
            $changed = $stmt->rowCount() > 0;
            if ($changed) {
                foreach (self::SOFT_DELETE_CHILD_TABLES as $t) {
                    $pdo->prepare("UPDATE {$t} SET deleted_at = NOW() WHERE case_id = :id AND company_id = :cid AND deleted_at IS NULL")
                        ->execute([':id' => $caseId, ':cid' => $companyId]);
                }
            }
            $pdo->commit();
            return $changed;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function restore(int $companyId, int $caseId): bool
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE cases SET deleted_at = NULL
                 WHERE id = :id AND company_id = :cid AND deleted_at IS NOT NULL"
            );
            $stmt->execute([':id' => $caseId, ':cid' => $companyId]);
            $changed = $stmt->rowCount() > 0;
            if ($changed) {
                foreach (self::SOFT_DELETE_CHILD_TABLES as $t) {
                    $pdo->prepare("UPDATE {$t} SET deleted_at = NULL WHERE case_id = :id AND company_id = :cid")
                        ->execute([':id' => $caseId, ':cid' => $companyId]);
                }
            }
            $pdo->commit();
            return $changed;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function getDeleted(int $companyId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, " . self::CASE_NUMBER_SQL . " AS case_number, c.basis, c.deleted_at
             FROM cases c
             WHERE c.company_id = :cid AND c.deleted_at IS NOT NULL
             ORDER BY c.deleted_at DESC"
        );
        $stmt->execute([':cid' => $companyId]);
        return $stmt->fetchAll();
    }

    /**
     * Permanently delete a (soft-deleted) case and everything tied to it:
     * parties, assignees, admin numbers, notes, to-dos, hearings and uploaded
     * files. Generated documents are kept (they're real documents, not case
     * attachments) but unlinked from the case. Returns the stored_rel paths
     * of removed files so the caller can unlink them from disk.
     */
    public function forceDelete(int $companyId, int $caseId): array
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $filesStmt = $pdo->prepare("SELECT stored_rel FROM case_files WHERE case_id = :id AND company_id = :cid");
            $filesStmt->execute([':id' => $caseId, ':cid' => $companyId]);
            $files = $filesStmt->fetchAll(PDO::FETCH_COLUMN);

            $del = $pdo->prepare("DELETE FROM cases WHERE id = :id AND company_id = :cid AND deleted_at IS NOT NULL");
            $del->execute([':id' => $caseId, ':cid' => $companyId]);
            $ok = $del->rowCount() > 0;
            if ($ok) {
                foreach (self::SOFT_DELETE_CHILD_TABLES as $t) {
                    $pdo->prepare("DELETE FROM {$t} WHERE case_id = :id AND company_id = :cid")
                        ->execute([':id' => $caseId, ':cid' => $companyId]);
                }
                $pdo->prepare("UPDATE generated_documents SET case_id = NULL WHERE case_id = :id AND company_id = :cid")
                    ->execute([':id' => $caseId, ':cid' => $companyId]);
            }
            $pdo->commit();
            return ['ok' => $ok, 'files' => $ok ? $files : []];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Lazy auto-purge of trash older than $days (no cron on shared hosting). */
    public function purgeOld(int $companyId, int $days = 30): int
    {
        $days = max(1, $days);
        $ids = $this->db->prepare(
            "SELECT id FROM cases
             WHERE company_id = :cid AND deleted_at IS NOT NULL
               AND deleted_at < (NOW() - INTERVAL {$days} DAY)"
        );
        $ids->execute([':cid' => $companyId]);
        $rows = $ids->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $id) {
            $res = $this->forceDelete($companyId, (int) $id);
            foreach ($res['files'] as $rel) {
                @unlink(UPLOADS_DIR . '/' . $rel);
            }
        }
        return count($rows);
    }

    /* =====================================================================
     | Административен број history
     * =================================================================== */

    /** Add a new admin number and make it the current one. */
    public function addAdminNumber(int $companyId, int $caseId, string $number, ?string $officialPerson = null): bool
    {
        $number = trim($number);
        if ($number === '') {
            return false;
        }
        // Guard the case belongs to the company.
        $chk = $this->db->prepare("SELECT 1 FROM cases WHERE id = :id AND company_id = :cid AND deleted_at IS NULL");
        $chk->execute([':id' => $caseId, ':cid' => $companyId]);
        if (!$chk->fetchColumn()) {
            return false;
        }

        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $this->addAdminNumberRaw($companyId, $caseId, $number, $officialPerson);
            $this->rebuildSearchText($companyId, $caseId);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function addAdminNumberRaw(int $companyId, int $caseId, string $number, ?string $officialPerson): void
    {
        $this->db->prepare("UPDATE case_admin_numbers SET is_current = 0 WHERE case_id = :id AND company_id = :cid")
            ->execute([':id' => $caseId, ':cid' => $companyId]);
        $this->db->prepare(
            "INSERT INTO case_admin_numbers (company_id, case_id, admin_number, is_current, official_person, created_at)
             VALUES (:cid, :id, :num, 1, :official, NOW())"
        )->execute([
            ':cid'     => $companyId,
            ':id'      => $caseId,
            ':num'     => $number,
            ':official' => $this->nz($officialPerson),
        ]);
    }

    /** Edit one admin-number entry (number + службено лице). Tenant- and case-scoped. */
    public function updateAdminNumber(int $companyId, int $caseId, int $adminId, string $number, ?string $officialPerson): bool
    {
        $number = trim($number);
        if ($number === '') {
            return false;
        }
        $chk = $this->db->prepare(
            "SELECT 1 FROM case_admin_numbers WHERE id = :aid AND case_id = :id AND company_id = :cid"
        );
        $chk->execute([':aid' => $adminId, ':id' => $caseId, ':cid' => $companyId]);
        if (!$chk->fetchColumn()) {
            return false;
        }
        $this->db->prepare(
            "UPDATE case_admin_numbers SET admin_number = :num, official_person = :official
             WHERE id = :aid AND case_id = :id AND company_id = :cid"
        )->execute([
            ':num'     => $number,
            ':official' => $this->nz($officialPerson),
            ':aid'     => $adminId,
            ':id'      => $caseId,
            ':cid'     => $companyId,
        ]);
        $this->rebuildSearchText($companyId, $caseId);
        return true;
    }

    /**
     * Delete one admin-number entry. If it was the current one, the newest
     * remaining entry is promoted to current so the case never loses its
     * displayed number while others still exist.
     */
    public function deleteAdminNumber(int $companyId, int $caseId, int $adminId): bool
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $row = $pdo->prepare(
                "SELECT is_current FROM case_admin_numbers WHERE id = :aid AND case_id = :id AND company_id = :cid"
            );
            $row->execute([':aid' => $adminId, ':id' => $caseId, ':cid' => $companyId]);
            $rec = $row->fetch();
            if (!$rec) {
                $pdo->commit();
                return false;
            }

            $pdo->prepare("DELETE FROM case_admin_numbers WHERE id = :aid AND case_id = :id AND company_id = :cid")
                ->execute([':aid' => $adminId, ':id' => $caseId, ':cid' => $companyId]);

            if ((int) $rec['is_current'] === 1) {
                $newest = $pdo->prepare(
                    "SELECT id FROM case_admin_numbers WHERE case_id = :id AND company_id = :cid
                     ORDER BY created_at DESC, id DESC LIMIT 1"
                );
                $newest->execute([':id' => $caseId, ':cid' => $companyId]);
                $nid = $newest->fetchColumn();
                if ($nid) {
                    $pdo->prepare("UPDATE case_admin_numbers SET is_current = 1 WHERE id = :nid")
                        ->execute([':nid' => $nid]);
                }
            }

            $this->rebuildSearchText($companyId, $caseId);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Short human label for a case, for audit details / activity log:
     * "1/26 · Оштета на возило". Works even for soft-deleted cases.
     */
    public function caseLabel(int $companyId, int $caseId): string
    {
        $stmt = $this->db->prepare(
            "SELECT " . self::CASE_NUMBER_SQL . " AS num, c.basis
             FROM cases c WHERE c.id = :id AND c.company_id = :cid"
        );
        $stmt->execute([':id' => $caseId, ':cid' => $companyId]);
        $r = $stmt->fetch();
        if (!$r) {
            return 'Предмет #' . $caseId;
        }
        return trim($r['num'] . (!empty($r['basis']) ? ' · ' . $r['basis'] : ''));
    }

    /* =====================================================================
     | Белешки (notes)
     * =================================================================== */

    /** Add a note to a case. Returns the new note id, or null if invalid. */
    public function addNote(int $companyId, int $caseId, ?int $userId, string $body, string $type = 'general'): ?int
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }
        $chk = $this->db->prepare("SELECT 1 FROM cases WHERE id = :id AND company_id = :cid AND deleted_at IS NULL");
        $chk->execute([':id' => $caseId, ':cid' => $companyId]);
        if (!$chk->fetchColumn()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO case_notes (company_id, case_id, user_id, body, note_type, created_at)
             VALUES (:cid, :case, :uid, :body, :type, NOW())"
        );
        $stmt->execute([
            ':cid'  => $companyId,
            ':case' => $caseId,
            ':uid'  => $userId,
            ':body' => $body,
            ':type' => in_array($type, self::NOTE_TYPES, true) ? $type : 'general',
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** All notes on a case: pinned first, then newest, with the author's live name. */
    public function getNotes(int $companyId, int $caseId): array
    {
        $stmt = $this->db->prepare(
            "SELECT n.id, n.body, n.note_type, n.is_pinned, n.user_id, n.created_at, n.updated_at, u.name AS author_name
             FROM case_notes n
             LEFT JOIN users u ON u.id = n.user_id
             WHERE n.case_id = :id AND n.company_id = :cid AND n.deleted_at IS NULL
             ORDER BY n.is_pinned DESC, n.created_at DESC, n.id DESC"
        );
        $stmt->execute([':id' => $caseId, ':cid' => $companyId]);
        return $stmt->fetchAll();
    }

    /** Edit a note (body + type) — only the author may. Returns false otherwise. */
    public function updateNote(int $companyId, int $noteId, int $userId, string $body, string $type = 'general'): bool
    {
        $body = trim($body);
        if ($body === '') {
            return false;
        }
        $chk = $this->db->prepare(
            "SELECT 1 FROM case_notes WHERE id = :nid AND company_id = :cid AND user_id = :uid"
        );
        $chk->execute([':nid' => $noteId, ':cid' => $companyId, ':uid' => $userId]);
        if (!$chk->fetchColumn()) {
            return false;
        }
        $this->db->prepare("UPDATE case_notes SET body = :body, note_type = :type WHERE id = :nid AND company_id = :cid")
            ->execute([
                ':body' => $body,
                ':type' => in_array($type, self::NOTE_TYPES, true) ? $type : 'general',
                ':nid'  => $noteId,
                ':cid'  => $companyId,
            ]);
        return true;
    }

    /** Pin/unpin a note — anyone with case access (shared highlight). */
    public function pinNote(int $companyId, int $noteId, bool $pinned): bool
    {
        $stmt = $this->db->prepare("UPDATE case_notes SET is_pinned = :p WHERE id = :nid AND company_id = :cid");
        $stmt->execute([':p' => $pinned ? 1 : 0, ':nid' => $noteId, ':cid' => $companyId]);
        return $stmt->rowCount() > 0
            || (bool) $this->existsNote($companyId, $noteId); // treat no-op (same value) as success
    }

    private function existsNote(int $companyId, int $noteId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM case_notes WHERE id = :nid AND company_id = :cid");
        $stmt->execute([':nid' => $noteId, ':cid' => $companyId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Delete a note — the author or an admin may. */
    public function deleteNote(int $companyId, int $noteId, int $userId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            $stmt = $this->db->prepare("DELETE FROM case_notes WHERE id = :nid AND company_id = :cid");
            $stmt->execute([':nid' => $noteId, ':cid' => $companyId]);
        } else {
            $stmt = $this->db->prepare("DELETE FROM case_notes WHERE id = :nid AND company_id = :cid AND user_id = :uid");
            $stmt->execute([':nid' => $noteId, ':cid' => $companyId, ':uid' => $userId]);
        }
        return $stmt->rowCount() > 0;
    }

    /* =====================================================================
     | Рочишта / настани (hearings)
     * =================================================================== */

    /** Normalize an event kind to one of HEARING_KINDS (defaults to 'hearing'). */
    private function normKind(?string $kind): string
    {
        $kind = trim((string) $kind);
        return in_array($kind, self::HEARING_KINDS, true) ? $kind : 'hearing';
    }

    /** Add a hearing/event. $hearingAt is 'YYYY-MM-DD HH:MM(:SS)'. Returns id or null. */
    public function addHearing(int $companyId, int $caseId, ?int $createdBy, string $title, string $hearingAt, ?string $location, ?string $note, string $kind = 'hearing'): ?int
    {
        $title = trim($title);
        $at = $this->normDateTime($hearingAt);
        if ($title === '' || $at === null) {
            return null;
        }
        $chk = $this->db->prepare("SELECT 1 FROM cases WHERE id = :id AND company_id = :cid AND deleted_at IS NULL");
        $chk->execute([':id' => $caseId, ':cid' => $companyId]);
        if (!$chk->fetchColumn()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO case_hearings (company_id, case_id, kind, title, hearing_at, location, note, created_by, created_at)
             VALUES (:cid, :case, :kind, :title, :at, :loc, :note, :by, NOW())"
        );
        $stmt->execute([
            ':cid'   => $companyId,
            ':case'  => $caseId,
            ':kind'  => $this->normKind($kind),
            ':title' => mb_substr($title, 0, 255),
            ':at'    => $at,
            ':loc'   => $this->nz($location),
            ':note'  => $this->nz($note),
            ':by'    => $createdBy,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Hearings on a case, chronological (earliest first), with creator name. */
    public function getHearings(int $companyId, int $caseId): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.id, h.kind, h.title, h.hearing_at, h.location, h.note, h.created_by, cu.name AS creator_name
             FROM case_hearings h
             LEFT JOIN users cu ON cu.id = h.created_by
             WHERE h.case_id = :id AND h.company_id = :cid AND h.deleted_at IS NULL
             ORDER BY h.hearing_at ASC"
        );
        $stmt->execute([':id' => $caseId, ':cid' => $companyId]);
        return $stmt->fetchAll();
    }

    /**
     * All calendar events for a company within [from, to] (inclusive dates),
     * across every non-deleted case, with the case number + primary client name
     * for labelling. Used by the calendar page.
     */
    public function getCalendarEvents(int $companyId, string $from, string $to, int $assigneeId = 0): array
    {
        $from = $this->nzDate($from);
        $to   = $this->nzDate($to);
        if ($from === null || $to === null) {
            return [];
        }

        $params = [':cid' => $companyId, ':from' => $from, ':to' => $to];
        $assigneeFilter = '';
        if ($assigneeId > 0) {
            $assigneeFilter = 'AND EXISTS (SELECT 1 FROM case_assignees caf
                                          WHERE caf.case_id = c.id AND caf.user_id = :aid)';
            $params[':aid'] = $assigneeId;
        }

        $stmt = $this->db->prepare(
            "SELECT h.id, h.case_id, h.kind, h.title, h.hearing_at, h.location, h.note,
                    h.created_by, cu.name AS creator_name,
                    " . self::CASE_NUMBER_SQL . " AS case_number,
                    c.basis AS case_basis,
                    COALESCE(NULLIF(pc.name, ''),
                             CASE WHEN cl.type = 'company' THEN cl.company_name ELSE cl.full_name END
                    ) AS client_name,
                    (SELECT GROUP_CONCAT(u2.name ORDER BY u2.name SEPARATOR ', ')
                       FROM case_assignees ca2 JOIN users u2 ON u2.id = ca2.user_id
                      WHERE ca2.case_id = c.id) AS assignees
             FROM case_hearings h
             JOIN cases c ON c.id = h.case_id AND c.deleted_at IS NULL
             LEFT JOIN case_parties pc ON pc.case_id = c.id AND pc.side = 'client' AND pc.is_primary = 1 AND pc.deleted_at IS NULL
             LEFT JOIN clients cl ON cl.id = pc.client_id
             LEFT JOIN users cu ON cu.id = h.created_by
             WHERE h.company_id = :cid AND h.deleted_at IS NULL
               AND h.hearing_at >= :from AND h.hearing_at < (:to + INTERVAL 1 DAY)
               {$assigneeFilter}
             ORDER BY h.hearing_at ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateHearing(int $companyId, int $hearingId, int $userId, bool $isAdmin, string $title, string $hearingAt, ?string $location, ?string $note, string $kind = 'hearing'): bool
    {
        $title = trim($title);
        $at = $this->normDateTime($hearingAt);
        if ($title === '' || $at === null || !$this->hearingEditable($companyId, $hearingId, $userId, $isAdmin)) {
            return false;
        }
        $this->db->prepare(
            "UPDATE case_hearings SET kind = :kind, title = :title, hearing_at = :at, location = :loc, note = :note
             WHERE id = :id AND company_id = :cid"
        )->execute([
            ':kind'  => $this->normKind($kind),
            ':title' => mb_substr($title, 0, 255),
            ':at'    => $at,
            ':loc'   => $this->nz($location),
            ':note'  => $this->nz($note),
            ':id'    => $hearingId,
            ':cid'   => $companyId,
        ]);
        return true;
    }

    public function deleteHearing(int $companyId, int $hearingId, int $userId, bool $isAdmin): bool
    {
        if (!$this->hearingEditable($companyId, $hearingId, $userId, $isAdmin)) {
            return false;
        }
        $stmt = $this->db->prepare("DELETE FROM case_hearings WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $hearingId, ':cid' => $companyId]);
        return $stmt->rowCount() > 0;
    }

    private function hearingEditable(int $companyId, int $hearingId, int $userId, bool $isAdmin): bool
    {
        $sql = $isAdmin
            ? "SELECT 1 FROM case_hearings WHERE id = :id AND company_id = :cid"
            : "SELECT 1 FROM case_hearings WHERE id = :id AND company_id = :cid AND created_by = :uid";
        $stmt = $this->db->prepare($sql);
        $params = [':id' => $hearingId, ':cid' => $companyId];
        if (!$isAdmin) {
            $params[':uid'] = $userId;
        }
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /** Normalize a datetime-local value to 'Y-m-d H:i:s' or null. */
    private function normDateTime(string $s): ?string
    {
        $s = trim($s);
        $s = str_replace('T', ' ', $s);
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $s)) {
            return null;
        }
        return strlen($s) === 16 ? $s . ':00' : $s;
    }

    /* =====================================================================
     | Задачи (to-do)
     * =================================================================== */

    /** Add a to-do to a case. $dueDate is 'YYYY-MM-DD' or null. Returns id or null. */
    public function addTodo(int $companyId, int $caseId, ?int $createdBy, string $title, ?string $dueDate, ?int $assignedTo): ?int
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }
        $chk = $this->db->prepare("SELECT 1 FROM cases WHERE id = :id AND company_id = :cid AND deleted_at IS NULL");
        $chk->execute([':id' => $caseId, ':cid' => $companyId]);
        if (!$chk->fetchColumn()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO case_todos (company_id, case_id, title, due_date, assigned_to, created_by, created_at)
             VALUES (:cid, :case, :title, :due, :assignee, :by, NOW())"
        );
        $stmt->execute([
            ':cid'      => $companyId,
            ':case'     => $caseId,
            ':title'    => mb_substr($title, 0, 500),
            ':due'      => $this->nzDate($dueDate),
            ':assignee' => $assignedTo ?: null,
            ':by'       => $createdBy,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** To-dos on a case: active statuses first, then by due date, with names. */
    public function getTodos(int $companyId, int $caseId): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id, t.title, t.status, t.is_done, t.due_date, t.assigned_to, t.created_by,
                    t.created_at, t.completed_at, a.name AS assignee_name, cu.name AS creator_name
             FROM case_todos t
             LEFT JOIN users a  ON a.id = t.assigned_to
             LEFT JOIN users cu ON cu.id = t.created_by
             WHERE t.case_id = :id AND t.company_id = :cid AND t.deleted_at IS NULL
             ORDER BY FIELD(t.status, 'in_progress', 'open', 'waiting', 'done', 'declined'),
                      (t.due_date IS NULL) ASC, t.due_date ASC, t.created_at ASC"
        );
        $stmt->execute([':id' => $caseId, ':cid' => $companyId]);
        return $stmt->fetchAll();
    }

    /** Set a to-do's workflow status — anyone with case access (shared checklist). */
    public function setTodoStatus(int $companyId, int $todoId, string $status): bool
    {
        if (!in_array($status, self::TODO_STATUSES, true)) {
            return false;
        }
        $chk = $this->db->prepare("SELECT 1 FROM case_todos WHERE id = :id AND company_id = :cid");
        $chk->execute([':id' => $todoId, ':cid' => $companyId]);
        if (!$chk->fetchColumn()) {
            return false;
        }
        $this->db->prepare(
            "UPDATE case_todos
                SET status = :st, is_done = :d,
                    completed_at = CASE WHEN :st2 = 'done' THEN NOW() ELSE NULL END
              WHERE id = :id AND company_id = :cid"
        )->execute([
            ':st'  => $status,
            ':d'   => $status === 'done' ? 1 : 0,
            ':st2' => $status,
            ':id'  => $todoId,
            ':cid' => $companyId,
        ]);
        return true;
    }

    /** Edit a to-do (title/due/assignee) — creator or admin only. */
    public function updateTodo(int $companyId, int $todoId, int $userId, bool $isAdmin, string $title, ?string $dueDate, ?int $assignedTo): bool
    {
        $title = trim($title);
        if ($title === '') {
            return false;
        }
        if (!$this->todoEditable($companyId, $todoId, $userId, $isAdmin)) {
            return false;
        }
        $this->db->prepare(
            "UPDATE case_todos SET title = :title, due_date = :due, assigned_to = :assignee
             WHERE id = :id AND company_id = :cid"
        )->execute([
            ':title'    => mb_substr($title, 0, 500),
            ':due'      => $this->nzDate($dueDate),
            ':assignee' => $assignedTo ?: null,
            ':id'       => $todoId,
            ':cid'      => $companyId,
        ]);
        return true;
    }

    /** Delete a to-do — creator or admin only. */
    public function deleteTodo(int $companyId, int $todoId, int $userId, bool $isAdmin): bool
    {
        if (!$this->todoEditable($companyId, $todoId, $userId, $isAdmin)) {
            return false;
        }
        $stmt = $this->db->prepare("DELETE FROM case_todos WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $todoId, ':cid' => $companyId]);
        return $stmt->rowCount() > 0;
    }

    private function todoEditable(int $companyId, int $todoId, int $userId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            $stmt = $this->db->prepare("SELECT 1 FROM case_todos WHERE id = :id AND company_id = :cid");
            $stmt->execute([':id' => $todoId, ':cid' => $companyId]);
        } else {
            $stmt = $this->db->prepare("SELECT 1 FROM case_todos WHERE id = :id AND company_id = :cid AND created_by = :uid");
            $stmt->execute([':id' => $todoId, ':cid' => $companyId, ':uid' => $userId]);
        }
        return (bool) $stmt->fetchColumn();
    }

    private function nzDate(?string $d): ?string
    {
        $d = trim((string) $d);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
    }

    /* =====================================================================
     | Документи (files uploaded from the computer)
     * =================================================================== */

    /** Files attached to a case, newest first, with uploader name. */
    public function getFiles(int $companyId, int $caseId): array
    {
        $stmt = $this->db->prepare(
            "SELECT f.id, f.orig_name, f.ext, f.size_bytes, f.created_at, u.name AS uploaded_by_name
             FROM case_files f
             LEFT JOIN users u ON u.id = f.uploaded_by
             WHERE f.case_id = :case AND f.company_id = :cid AND f.deleted_at IS NULL
             ORDER BY f.id DESC"
        );
        $stmt->execute([':case' => $caseId, ':cid' => $companyId]);
        return $stmt->fetchAll();
    }

    /** Record an uploaded file against a case. Returns the new file id, or null if the case is invalid. */
    public function addFile(int $companyId, int $caseId, ?int $uploadedBy, string $origName, string $storedRel, ?string $ext, int $size): ?int
    {
        $chk = $this->db->prepare("SELECT 1 FROM cases WHERE id = :id AND company_id = :cid AND deleted_at IS NULL");
        $chk->execute([':id' => $caseId, ':cid' => $companyId]);
        if (!$chk->fetchColumn()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO case_files (company_id, case_id, orig_name, stored_rel, ext, size_bytes, uploaded_by, created_at)
             VALUES (:cid, :case, :name, :rel, :ext, :size, :by, NOW())"
        );
        $stmt->execute([
            ':cid'  => $companyId,
            ':case' => $caseId,
            ':name' => mb_substr($origName, 0, 255),
            ':rel'  => $storedRel,
            ':ext'  => $ext ? mb_substr($ext, 0, 12) : null,
            ':size' => $size,
            ':by'   => $uploadedBy,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** One file row (for streaming a download). Tenant-scoped. */
    public function getFile(int $companyId, int $fileId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM case_files WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $fileId, ':cid' => $companyId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Delete a file row. The caller removes the physical file. */
    public function deleteFile(int $companyId, int $fileId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM case_files WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $fileId, ':cid' => $companyId]);
        return $stmt->rowCount() > 0;
    }

    /* =====================================================================
     | основ autocomplete
     * =================================================================== */

    /**
     * Suggest historic основ values for consistency. Substring match (so
     * "Штета" surfaces "Оштета"), ranked by how often each value is used.
     */
    public function suggestBasis(int $companyId, string $term, int $limit = 8): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT basis, COUNT(*) AS cnt
             FROM cases
             WHERE company_id = :cid AND deleted_at IS NULL
               AND basis IS NOT NULL AND basis <> '' AND basis LIKE :q
             GROUP BY basis
             ORDER BY cnt DESC, CHAR_LENGTH(basis) ASC, basis ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':cid', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':q', '%' . $term . '%');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Suggest historic службено лице values (judge/notary/bailiff/clerk) for
     * consistency, ranked by how often each value is used.
     */
    public function suggestOfficial(int $companyId, string $term, int $limit = 8): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT an.official_person, COUNT(*) AS cnt
             FROM case_admin_numbers an
             JOIN cases c ON c.id = an.case_id
             WHERE an.company_id = :cid AND an.deleted_at IS NULL AND c.deleted_at IS NULL
               AND an.official_person IS NOT NULL AND an.official_person <> '' AND an.official_person LIKE :q
             GROUP BY an.official_person
             ORDER BY cnt DESC, CHAR_LENGTH(an.official_person) ASC, an.official_person ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':cid', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':q', '%' . $term . '%');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /* =====================================================================
     | Internal helpers
     * =================================================================== */

    /** Validate / clean the parties payload; force exactly one primary per side. */
    private function normalizeParties(array $parties): array
    {
        $out = ['client' => [], 'opponent' => []];
        foreach ($parties as $p) {
            $side = ($p['side'] ?? '') === 'opponent' ? 'opponent' : 'client';
            $role = trim((string) ($p['role'] ?? ''));
            if ($side === 'client') {
                $clientId = (int) ($p['client_id'] ?? 0);
                if ($clientId <= 0) {
                    continue; // a client party without a linked client is meaningless
                }
                $out['client'][] = ['client_id' => $clientId, 'role' => $role];
            } else {
                $name = trim((string) ($p['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $out['opponent'][] = [
                    'name'                    => $name,
                    'opposing_representative' => trim((string) ($p['opposing_representative'] ?? '')),
                    'role'                    => $role,
                ];
            }
        }
        return $out;
    }

    private function hasClientParty(array $normalized): bool
    {
        return !empty($normalized['client']);
    }

    private function insertParties(int $companyId, int $caseId, array $normalized): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO case_parties
                (company_id, case_id, side, client_id, name, opposing_representative, role, is_primary, created_at)
             VALUES (:cid, :case, :side, :client, :name, :rep, :role, :primary, NOW())"
        );
        foreach (['client', 'opponent'] as $side) {
            foreach ($normalized[$side] as $i => $p) {
                $stmt->execute([
                    ':cid'     => $companyId,
                    ':case'    => $caseId,
                    ':side'    => $side,
                    ':client'  => $side === 'client' ? $p['client_id'] : null,
                    ':name'    => $side === 'opponent' ? $p['name'] : null,
                    ':rep'     => $side === 'opponent' ? $this->nz($p['opposing_representative']) : null,
                    ':role'    => $p['role'],
                    ':primary' => $i === 0 ? 1 : 0,
                ]);
            }
        }
    }

    private function replaceAssignees(int $companyId, int $caseId, array $userIds): void
    {
        $this->db->prepare("DELETE FROM case_assignees WHERE case_id = :id AND company_id = :cid")
            ->execute([':id' => $caseId, ':cid' => $companyId]);
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO case_assignees (company_id, case_id, user_id, assigned_at)
             VALUES (:cid, :case, :uid, NOW())"
        );
        foreach ($userIds as $uid) {
            $stmt->execute([':cid' => $companyId, ':case' => $caseId, ':uid' => $uid]);
        }
    }

    /** Pull assignees for a page of cases in one query and graft them on. */
    private function attachAssignees(int $companyId, array &$rows): void
    {
        if (!$rows) {
            return;
        }
        $ids = array_column($rows, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT ca.case_id, u.id, u.name
             FROM case_assignees ca JOIN users u ON u.id = ca.user_id
             WHERE ca.company_id = ? AND ca.case_id IN ({$in}) AND ca.deleted_at IS NULL
             ORDER BY u.name"
        );
        $stmt->execute(array_merge([$companyId], $ids));
        $byCase = [];
        foreach ($stmt->fetchAll() as $r) {
            $byCase[$r['case_id']][] = ['id' => $r['id'], 'name' => $r['name']];
        }
        foreach ($rows as &$row) {
            $row['assignees'] = $byCase[$row['id']] ?? [];
        }
        unset($row);
    }

    /** Attach ALL parties (both sides, names resolved) to a page of cases. */
    private function attachParties(int $companyId, array &$rows): void
    {
        if (!$rows) {
            return;
        }
        $ids = array_column($rows, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT p.case_id, p.side, p.role,
                    COALESCE(NULLIF(p.name, ''),
                             CASE WHEN cl.type = 'company' THEN cl.company_name ELSE cl.full_name END) AS name
             FROM case_parties p
             LEFT JOIN clients cl ON cl.id = p.client_id
             WHERE p.company_id = ? AND p.case_id IN ({$in}) AND p.deleted_at IS NULL
             ORDER BY p.side, p.is_primary DESC, p.id"
        );
        $stmt->execute(array_merge([$companyId], $ids));
        $byCase = [];
        foreach ($stmt->fetchAll() as $r) {
            $byCase[$r['case_id']][$r['side']][] = ['name' => $r['name'], 'role' => $r['role']];
        }
        foreach ($rows as &$row) {
            $row['client_parties']   = $byCase[$row['id']]['client'] ?? [];
            $row['opponent_parties'] = $byCase[$row['id']]['opponent'] ?? [];
        }
        unset($row);
    }

    /**
     * Rebuild the denormalized search blob for one case: number + basis +
     * party names/roles + opposing representatives + every admin number it ever had.
     */
    private function rebuildSearchText(int $companyId, int $caseId): void
    {
        $stmt = $this->db->prepare(
            "SELECT " . self::CASE_NUMBER_SQL . " AS case_number, c.basis
             FROM cases c WHERE c.id = :id AND c.company_id = :cid"
        );
        $stmt->execute([':id' => $caseId, ':cid' => $companyId]);
        $base = $stmt->fetch() ?: [];

        $bits = [$base['case_number'] ?? '', $base['basis'] ?? ''];

        $p = $this->db->prepare(
            "SELECT p.role, p.name, p.opposing_representative,
                    CASE WHEN cl.type = 'company' THEN cl.company_name ELSE cl.full_name END AS client_name
             FROM case_parties p LEFT JOIN clients cl ON cl.id = p.client_id
             WHERE p.case_id = :id AND p.company_id = :cid AND p.deleted_at IS NULL"
        );
        $p->execute([':id' => $caseId, ':cid' => $companyId]);
        foreach ($p->fetchAll() as $row) {
            $bits[] = $row['role'];
            $bits[] = $row['name'];
            $bits[] = $row['client_name'];
            $bits[] = $row['opposing_representative'];
        }

        $n = $this->db->prepare("SELECT admin_number, official_person FROM case_admin_numbers WHERE case_id = :id AND company_id = :cid AND deleted_at IS NULL");
        $n->execute([':id' => $caseId, ':cid' => $companyId]);
        foreach ($n->fetchAll() as $row) {
            $bits[] = $row['admin_number'];
            $bits[] = $row['official_person'];
        }

        $text = trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($bits, fn($b) => $b !== null && $b !== ''))));
        $this->db->prepare("UPDATE cases SET search_text = :t WHERE id = :id AND company_id = :cid")
            ->execute([':t' => $text, ':id' => $caseId, ':cid' => $companyId]);
    }

    private function nz($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === null || $v === '') ? null : (string) $v;
    }

    private function money($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $v = str_replace([',', ' '], ['.', ''], (string) $v);
        return is_numeric($v) ? (float) $v : null;
    }
}
