<?php

require_once __DIR__ . '/Database.php';

/**
 * In-app notifications (Facebook-style bell in the top nav).
 *
 * One row per recipient. Kept deliberately generic via `type` so new kinds can
 * be added later; today the only producer is to-do assignment ('todo.assigned').
 * The displayed case number / основ is resolved live on read (so it stays
 * correct if the case is renumbered), while the to-do title and actor name are
 * snapshotted at creation time.
 */
class Notification
{
    private Database $db;

    /** Most recent notifications shown in the dropdown. */
    private const FEED_LIMIT = 20;

    /** SQL fragment: the displayed предмет број, mirrors CaseFile::CASE_NUMBER_SQL. */
    private const CASE_NUMBER_SQL =
        "CONCAT(c.case_seq, '/', LPAD(MOD(c.case_year, 100), 2, '0'),
                IF(c.archive_seq IS NULL, '', CONCAT('/', c.archive_seq)))";

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create a notification for one recipient. No-ops (returns null) when there
     * is no recipient or the recipient is the actor (never notify yourself).
     */
    public function create(
        int $companyId,
        int $userId,
        ?int $actorId,
        ?string $actorName,
        string $type,
        ?int $caseId,
        ?int $todoId,
        ?string $title
    ): ?int {
        if ($userId <= 0 || ($actorId !== null && $userId === $actorId)) {
            return null;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO notifications (company_id, user_id, actor_id, actor_name, type, case_id, todo_id, title, created_at)
             VALUES (:cid, :uid, :aid, :aname, :type, :case, :todo, :title, NOW())"
        );
        $stmt->execute([
            ':cid'   => $companyId,
            ':uid'   => $userId,
            ':aid'   => $actorId,
            ':aname' => $actorName !== null ? mb_substr($actorName, 0, 255) : null,
            ':type'  => $type,
            ':case'  => $caseId ?: null,
            ':todo'  => $todoId ?: null,
            ':title' => $title !== null ? mb_substr($title, 0, 500) : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Number of unread notifications for a user. */
    public function unreadCount(int $companyId, int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM notifications
             WHERE company_id = :cid AND user_id = :uid AND is_read = 0"
        );
        $stmt->execute([':cid' => $companyId, ':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Recent notifications for a user (newest first), each carrying the live
     * case number + основ so the row can render its link and subtext.
     */
    public function getForUser(int $companyId, int $userId, int $limit = self::FEED_LIMIT): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->db->prepare(
            "SELECT n.id, n.actor_id, n.actor_name, n.type, n.case_id, n.todo_id, n.title,
                    n.is_read, n.created_at,
                    " . self::CASE_NUMBER_SQL . " AS case_number,
                    c.basis AS case_basis, c.deleted_at AS case_deleted_at
             FROM notifications n
             LEFT JOIN cases c ON c.id = n.case_id
             WHERE n.company_id = :cid AND n.user_id = :uid
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([':cid' => $companyId, ':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** Mark one notification read — scoped to its owner. */
    public function markRead(int $companyId, int $userId, int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE id = :id AND company_id = :cid AND user_id = :uid AND is_read = 0"
        );
        $stmt->execute([':id' => $id, ':cid' => $companyId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /** Mark every unread notification read for a user. Returns how many changed. */
    public function markAllRead(int $companyId, int $userId): int
    {
        $stmt = $this->db->prepare(
            "UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE company_id = :cid AND user_id = :uid AND is_read = 0"
        );
        $stmt->execute([':cid' => $companyId, ':uid' => $userId]);
        return $stmt->rowCount();
    }
}
