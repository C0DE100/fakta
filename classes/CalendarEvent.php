<?php

require_once __DIR__ . '/Database.php';

/**
 * Personal calendar events — standalone entries a user adds straight from the
 * calendar (client meetings, reminders, anything). NOT tied to a case.
 *
 * Ownership: every event has a user_id (the creator). A user may only create
 * events for themselves and may only edit/delete their own. Events are visible
 * to the whole company on the shared calendar (filterable by owner).
 */
class CalendarEvent
{
    private Database $db;

    /** Allowed kinds (shared palette with case events + a neutral 'other'). */
    public const KINDS = ['hearing', 'trial', 'meeting', 'other'];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /** Events for the company within [from, to] (inclusive dates), optional owner filter. */
    public function getForRange(int $companyId, string $from, string $to, int $userId = 0): array
    {
        $from = $this->nzDate($from);
        $to   = $this->nzDate($to);
        if ($from === null || $to === null) {
            return [];
        }
        $params = [':cid' => $companyId, ':from' => $from, ':to' => $to];
        $ownerFilter = '';
        if ($userId > 0) {
            $ownerFilter = 'AND e.user_id = :uid';
            $params[':uid'] = $userId;
        }
        $stmt = $this->db->prepare(
            "SELECT e.id, e.user_id, e.kind, e.title, e.starts_at, e.location, e.note,
                    u.name AS owner_name
             FROM calendar_events e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.company_id = :cid
               AND e.starts_at >= :from AND e.starts_at < (:to + INTERVAL 1 DAY)
               {$ownerFilter}
             ORDER BY e.starts_at ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Create an event owned by $userId. Returns the new id or null. */
    public function add(int $companyId, int $userId, string $title, string $startsAt, string $kind, ?string $location, ?string $note): ?int
    {
        $title = trim($title);
        $at    = $this->normDateTime($startsAt);
        if ($title === '' || $at === null || $userId <= 0) {
            return null;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO calendar_events (company_id, user_id, kind, title, starts_at, location, note, created_at)
             VALUES (:cid, :uid, :kind, :title, :at, :loc, :note, NOW())"
        );
        $stmt->execute([
            ':cid'   => $companyId,
            ':uid'   => $userId,
            ':kind'  => $this->normKind($kind),
            ':title' => mb_substr($title, 0, 255),
            ':at'    => $at,
            ':loc'   => $this->nz($location),
            ':note'  => $this->nz($note),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Update an event — owner only. */
    public function update(int $companyId, int $eventId, int $userId, string $title, string $startsAt, string $kind, ?string $location, ?string $note): bool
    {
        $title = trim($title);
        $at    = $this->normDateTime($startsAt);
        if ($title === '' || $at === null || !$this->ownedBy($companyId, $eventId, $userId)) {
            return false;
        }
        $this->db->prepare(
            "UPDATE calendar_events SET kind = :kind, title = :title, starts_at = :at, location = :loc, note = :note
             WHERE id = :id AND company_id = :cid AND user_id = :uid"
        )->execute([
            ':kind'  => $this->normKind($kind),
            ':title' => mb_substr($title, 0, 255),
            ':at'    => $at,
            ':loc'   => $this->nz($location),
            ':note'  => $this->nz($note),
            ':id'    => $eventId,
            ':cid'   => $companyId,
            ':uid'   => $userId,
        ]);
        return true;
    }

    /** Delete an event — owner only. */
    public function delete(int $companyId, int $eventId, int $userId): bool
    {
        if (!$this->ownedBy($companyId, $eventId, $userId)) {
            return false;
        }
        $stmt = $this->db->prepare(
            "DELETE FROM calendar_events WHERE id = :id AND company_id = :cid AND user_id = :uid"
        );
        $stmt->execute([':id' => $eventId, ':cid' => $companyId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    private function ownedBy(int $companyId, int $eventId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM calendar_events WHERE id = :id AND company_id = :cid AND user_id = :uid"
        );
        $stmt->execute([':id' => $eventId, ':cid' => $companyId, ':uid' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    private function normKind(?string $kind): string
    {
        $kind = trim((string) $kind);
        return in_array($kind, self::KINDS, true) ? $kind : 'meeting';
    }

    /** Normalize a datetime-local / 'Y-m-d H:i(:s)' value to 'Y-m-d H:i:s' or null. */
    private function normDateTime(string $s): ?string
    {
        $s = str_replace('T', ' ', trim($s));
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $s)) {
            return null;
        }
        return strlen($s) === 16 ? $s . ':00' : $s;
    }

    private function nzDate(?string $d): ?string
    {
        $d = trim((string) $d);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
    }

    private function nz($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === null || $v === '') ? null : (string) $v;
    }
}
