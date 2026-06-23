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
     *   'parties'        => [ ['side','client_id','name','entity_type','opposing_lawyer','role'], … ],
     *   'assignees'      => [userId, …],
     * ]
     * Returns the new case id. Throws on a missing client party.
     */
    public function create(int $companyId, array $data, ?int $createdBy): int
    {
        $parties = $this->normalizeParties($data['parties'] ?? []);
        if (!$this->hasClientParty($parties)) {
            throw new InvalidArgumentException('Предметот мора да има барем една странка-клиент.');
        }

        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
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
                $this->addAdminNumberRaw($companyId, $caseId, $adminNo, $data['admin_note'] ?? null);
            }

            $this->rebuildSearchText($companyId, $caseId);

            $pdo->commit();
            return $caseId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
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
                    u.name AS created_by_name
                FROM cases c
                LEFT JOIN case_parties pc ON pc.case_id = c.id AND pc.side = 'client'   AND pc.is_primary = 1
                LEFT JOIN clients cl      ON cl.id = pc.client_id
                LEFT JOIN case_parties po ON po.case_id = c.id AND po.side = 'opponent' AND po.is_primary = 1
                LEFT JOIN case_admin_numbers an ON an.case_id = c.id AND an.is_current = 1
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
            "SELECT p.id, p.side, p.client_id, p.entity_type, p.opposing_lawyer, p.role, p.is_primary,
                    COALESCE(NULLIF(p.name, ''),
                             CASE WHEN cl.type = 'company' THEN cl.company_name ELSE cl.full_name END
                    ) AS name,
                    cl.type AS client_type
             FROM case_parties p
             LEFT JOIN clients cl ON cl.id = p.client_id
             WHERE p.case_id = :id AND p.company_id = :cid
             ORDER BY p.side, p.is_primary DESC, p.id"
        );
        $pstmt->execute([':id' => $caseId, ':cid' => $companyId]);
        $case['parties'] = $pstmt->fetchAll();

        // Assignees.
        $astmt = $this->db->prepare(
            "SELECT u.id, u.name FROM case_assignees ca
             JOIN users u ON u.id = ca.user_id
             WHERE ca.case_id = :id AND ca.company_id = :cid
             ORDER BY u.name"
        );
        $astmt->execute([':id' => $caseId, ':cid' => $companyId]);
        $case['assignees'] = $astmt->fetchAll();

        // Admin-number history (current first).
        $nstmt = $this->db->prepare(
            "SELECT id, admin_number, is_current, note, created_at
             FROM case_admin_numbers
             WHERE case_id = :id AND company_id = :cid
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

    public function softDelete(int $companyId, int $caseId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE cases SET deleted_at = NOW()
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $caseId, ':cid' => $companyId]);
        return $stmt->rowCount() > 0;
    }

    public function restore(int $companyId, int $caseId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE cases SET deleted_at = NULL
             WHERE id = :id AND company_id = :cid AND deleted_at IS NOT NULL"
        );
        $stmt->execute([':id' => $caseId, ':cid' => $companyId]);
        return $stmt->rowCount() > 0;
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

    public function forceDelete(int $companyId, int $caseId): bool
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare("DELETE FROM cases WHERE id = :id AND company_id = :cid AND deleted_at IS NOT NULL");
            $del->execute([':id' => $caseId, ':cid' => $companyId]);
            $ok = $del->rowCount() > 0;
            if ($ok) {
                foreach (['case_parties', 'case_assignees', 'case_admin_numbers'] as $t) {
                    $pdo->prepare("DELETE FROM {$t} WHERE case_id = :id AND company_id = :cid")
                        ->execute([':id' => $caseId, ':cid' => $companyId]);
                }
            }
            $pdo->commit();
            return $ok;
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
            $this->forceDelete($companyId, (int) $id);
        }
        return count($rows);
    }

    /* =====================================================================
     | Административен број history
     * =================================================================== */

    /** Add a new admin number and make it the current one. */
    public function addAdminNumber(int $companyId, int $caseId, string $number, ?string $note = null): bool
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
            $this->addAdminNumberRaw($companyId, $caseId, $number, $note);
            $this->rebuildSearchText($companyId, $caseId);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function addAdminNumberRaw(int $companyId, int $caseId, string $number, ?string $note): void
    {
        $this->db->prepare("UPDATE case_admin_numbers SET is_current = 0 WHERE case_id = :id AND company_id = :cid")
            ->execute([':id' => $caseId, ':cid' => $companyId]);
        $this->db->prepare(
            "INSERT INTO case_admin_numbers (company_id, case_id, admin_number, is_current, note, created_at)
             VALUES (:cid, :id, :num, 1, :note, NOW())"
        )->execute([
            ':cid'  => $companyId,
            ':id'   => $caseId,
            ':num'  => $number,
            ':note' => $this->nz($note),
        ]);
    }

    /** Edit one admin-number entry (number + note). Tenant- and case-scoped. */
    public function updateAdminNumber(int $companyId, int $caseId, int $adminId, string $number, ?string $note): bool
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
            "UPDATE case_admin_numbers SET admin_number = :num, note = :note
             WHERE id = :aid AND case_id = :id AND company_id = :cid"
        )->execute([
            ':num'  => $number,
            ':note' => $this->nz($note),
            ':aid'  => $adminId,
            ':id'   => $caseId,
            ':cid'  => $companyId,
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
                    'name'            => $name,
                    'entity_type'     => ($p['entity_type'] ?? '') === 'company' ? 'company' : 'individual',
                    'opposing_lawyer' => trim((string) ($p['opposing_lawyer'] ?? '')),
                    'role'            => $role,
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
                (company_id, case_id, side, client_id, name, entity_type, opposing_lawyer, role, is_primary, created_at)
             VALUES (:cid, :case, :side, :client, :name, :etype, :lawyer, :role, :primary, NOW())"
        );
        foreach (['client', 'opponent'] as $side) {
            foreach ($normalized[$side] as $i => $p) {
                $stmt->execute([
                    ':cid'     => $companyId,
                    ':case'    => $caseId,
                    ':side'    => $side,
                    ':client'  => $side === 'client' ? $p['client_id'] : null,
                    ':name'    => $side === 'opponent' ? $p['name'] : null,
                    ':etype'   => $side === 'opponent' ? $p['entity_type'] : null,
                    ':lawyer'  => $side === 'opponent' ? $this->nz($p['opposing_lawyer']) : null,
                    ':role'    => $p['role'] !== '' ? $p['role'] : ($side === 'client' ? 'Странка' : 'Спротивна странка'),
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
             WHERE ca.company_id = ? AND ca.case_id IN ({$in})
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

    /**
     * Rebuild the denormalized search blob for one case: number + basis +
     * party names/roles + opposing lawyers + every admin number it ever had.
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
            "SELECT p.role, p.name, p.opposing_lawyer,
                    CASE WHEN cl.type = 'company' THEN cl.company_name ELSE cl.full_name END AS client_name
             FROM case_parties p LEFT JOIN clients cl ON cl.id = p.client_id
             WHERE p.case_id = :id AND p.company_id = :cid"
        );
        $p->execute([':id' => $caseId, ':cid' => $companyId]);
        foreach ($p->fetchAll() as $row) {
            $bits[] = $row['role'];
            $bits[] = $row['name'];
            $bits[] = $row['client_name'];
            $bits[] = $row['opposing_lawyer'];
        }

        $n = $this->db->prepare("SELECT admin_number FROM case_admin_numbers WHERE case_id = :id AND company_id = :cid");
        $n->execute([':id' => $caseId, ':cid' => $companyId]);
        foreach ($n->fetchAll(PDO::FETCH_COLUMN) as $num) {
            $bits[] = $num;
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
