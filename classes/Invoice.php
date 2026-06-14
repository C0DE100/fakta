<?php

require_once __DIR__ . '/Database.php';

class Invoice
{
    private Database $db;

    private const PER_PAGE = 10;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Returns a paginated, filtered list of invoices joined with client names.
     *
     * DATABASE INFO NEEDED
     * Assumes table `invoices` with columns: id, number, date, status, client_id (FK → clients.id)
     * Confirm: is the FK column named `client_id`?
     * Confirm: is `date` stored as DATE or DATETIME?
     */
    public function getList(int $companyId, string $search, string $month, int $clientId, int $page): array
    {
        $conditions = ['i.company_id = :company_id'];
        $params     = [':company_id' => $companyId];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $conditions[] = "(i.number LIKE :search_num
                OR CASE WHEN c.type = 'company' THEN c.company_name ELSE c.full_name END LIKE :search_name
                OR i.date LIKE :search_date)";
            $params[':search_num']  = $like;
            $params[':search_name'] = $like;
            $params[':search_date'] = $like;
        }

        if ($month !== '') {
            $conditions[] = "DATE_FORMAT(i.date, '%Y-%m') = :month";
            $params[':month'] = $month;
        }

        if ($clientId > 0) {
            $conditions[] = "i.client_id = :client_id";
            $params[':client_id'] = $clientId;
        }

        $where  = implode(' AND ', $conditions);
        $offset = ($page - 1) * self::PER_PAGE;

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE {$where}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $this->db->prepare(
            "SELECT i.id, i.number, i.date, i.status,
                    CASE WHEN c.type = 'company' THEN c.company_name ELSE c.full_name END AS client_name
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE {$where}
             ORDER BY i.date DESC, i.id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $dataStmt->bindValue($key, $value);
        }
        $dataStmt->bindValue(':limit',  self::PER_PAGE, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset,        PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'data'  => $dataStmt->fetchAll(),
            'total' => $total,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'page'  => $page,
        ];
    }

    /**
     * Aggregate counts for the dashboard: total invoices, how many fall in the
     * given month (YYYY-MM), and a breakdown by status.
     */
    public function getStats(int $companyId, string $month): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(DATE_FORMAT(date, '%Y-%m') = :month), 0) AS this_month,
                COALESCE(SUM(status = 'испратена'), 0) AS sent,
                COALESCE(SUM(status = 'нацрт'), 0)     AS draft,
                COALESCE(SUM(status = 'платена'), 0)   AS paid
             FROM invoices
             WHERE company_id = :company_id"
        );
        $stmt->execute([':company_id' => $companyId, ':month' => $month]);
        $row = $stmt->fetch();

        return [
            'total'      => (int) $row['total'],
            'this_month' => (int) $row['this_month'],
            'sent'       => (int) $row['sent'],
            'draft'      => (int) $row['draft'],
            'paid'       => (int) $row['paid'],
        ];
    }

    /** The newest invoices (joined with client names) for the dashboard list. */
    public function getRecent(int $companyId, int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.id, i.number, i.date, i.status,
                    CASE WHEN c.type = 'company' THEN c.company_name ELSE c.full_name END AS client_name
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             WHERE i.company_id = :company_id
             ORDER BY i.date DESC, i.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',      $limit,     PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
