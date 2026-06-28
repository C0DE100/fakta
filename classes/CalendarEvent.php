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

    /** Allowed kinds (shared palette with case events + a neutral 'other').
     *  'private' is calendar-only: visible to its owner alone (see getForRange). */
    public const KINDS = ['hearing', 'trial', 'meeting', 'other', 'private'];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Events for the company within [from, to] (inclusive dates), optional owner filter.
     * $viewerId is the logged-in user viewing the calendar — 'private' events are only
     * ever returned for their own owner, regardless of $userId (the "whose calendar" filter).
     */
    public function getForRange(int $companyId, string $from, string $to, int $userId = 0, int $viewerId = 0): array
    {
        $from = $this->nzDate($from);
        $to   = $this->nzDate($to);
        if ($from === null || $to === null) {
            return [];
        }
        $params = [':cid' => $companyId, ':from' => $from, ':to' => $to, ':viewer' => $viewerId];
        $ownerFilter = '';
        if ($userId > 0) {
            $ownerFilter = 'AND e.user_id = :uid';
            $params[':uid'] = $userId;
        }
        $stmt = $this->db->prepare(
            "SELECT e.id, e.user_id, e.kind, e.title, e.starts_at, e.ends_at, e.location, e.note,
                    u.name AS owner_name
             FROM calendar_events e
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.company_id = :cid
               AND e.starts_at >= :from AND e.starts_at < (:to + INTERVAL 1 DAY)
               AND (e.kind <> 'private' OR e.user_id = :viewer)
               {$ownerFilter}
             ORDER BY e.starts_at ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Default event length when no end time is given. */
    private const DEFAULT_EVENT_MINUTES = 60;

    /** Create an event owned by $userId. Returns the new id or null. */
    public function add(int $companyId, int $userId, string $title, string $startsAt, string $kind, ?string $location, ?string $note, ?string $endsAt = null): ?int
    {
        $title = trim($title);
        $at    = $this->normDateTime($startsAt);
        if ($title === '' || $at === null || $userId <= 0) {
            return null;
        }
        $end = $this->normEndDateTime($at, $endsAt);
        $stmt = $this->db->prepare(
            "INSERT INTO calendar_events (company_id, user_id, kind, title, starts_at, ends_at, location, note, created_at)
             VALUES (:cid, :uid, :kind, :title, :at, :end, :loc, :note, NOW())"
        );
        $stmt->execute([
            ':cid'   => $companyId,
            ':uid'   => $userId,
            ':kind'  => $this->normKind($kind),
            ':title' => mb_substr($title, 0, 255),
            ':at'    => $at,
            ':end'   => $end,
            ':loc'   => $this->nz($location),
            ':note'  => $this->nz($note),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Update an event — owner only. */
    public function update(int $companyId, int $eventId, int $userId, string $title, string $startsAt, string $kind, ?string $location, ?string $note, ?string $endsAt = null): bool
    {
        $title = trim($title);
        $at    = $this->normDateTime($startsAt);
        if ($title === '' || $at === null || !$this->ownedBy($companyId, $eventId, $userId)) {
            return false;
        }
        $end = $this->normEndDateTime($at, $endsAt);
        $this->db->prepare(
            "UPDATE calendar_events SET kind = :kind, title = :title, starts_at = :at, ends_at = :end, location = :loc, note = :note
             WHERE id = :id AND company_id = :cid AND user_id = :uid"
        )->execute([
            ':kind'  => $this->normKind($kind),
            ':title' => mb_substr($title, 0, 255),
            ':at'    => $at,
            ':end'   => $end,
            ':loc'   => $this->nz($location),
            ':note'  => $this->nz($note),
            ':id'    => $eventId,
            ':cid'   => $companyId,
            ':uid'   => $userId,
        ]);
        return true;
    }

    /** Resolve an end time: explicit value if valid, else start + default length. */
    private function normEndDateTime(string $start, ?string $endsAt): string
    {
        $end = $endsAt !== null ? $this->normDateTime($endsAt) : null;
        if ($end !== null && $end > $start) {
            return $end;
        }
        return date('Y-m-d H:i:s', strtotime($start) + self::DEFAULT_EVENT_MINUTES * 60);
    }

    /**
     * Find a personal event of $userId overlapping [startsAt, endsAt), or null if free.
     * Normalizes the window the same way add()/update() do, so callers can pass raw input.
     */
    public function findOverlap(int $companyId, int $userId, string $startsAt, ?string $endsAt, int $excludeEventId = 0): ?array
    {
        $start = $this->normDateTime($startsAt);
        if ($start === null) {
            return null;
        }
        $end = $this->normEndDateTime($start, $endsAt);
        $sql = "SELECT title, starts_at, ends_at FROM calendar_events
                WHERE company_id = :cid AND user_id = :uid
                  AND starts_at < :end AND ends_at > :start";
        $params = [':cid' => $companyId, ':uid' => $userId, ':start' => $start, ':end' => $end];
        if ($excludeEventId > 0) {
            $sql .= ' AND id <> :exid';
            $params[':exid'] = $excludeEventId;
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
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
