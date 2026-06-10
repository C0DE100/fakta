<?php

require_once __DIR__ . '/Database.php';

class Company
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function create(string $name, ?string $email, ?string $address, ?string $phone): int
    {
        $sql = "INSERT INTO companies (name, email, address, phone, created_at)
                VALUES (:name, :email, :address, :phone, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name'    => $name,
            ':email'   => $email !== '' ? $email : null,
            ':address' => $address !== '' ? $address : null,
            ':phone'   => $phone !== '' ? $phone : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getAll(): array
    {
        $sql = "SELECT c.*, COUNT(u.id) AS user_count
                FROM companies c
                LEFT JOIN users u ON u.company_id = c.id
                GROUP BY c.id
                ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Searchable, paginated list of companies with their user counts.
     * Returns ['data', 'total', 'pages', 'page'].
     */
    public function getPaged(string $search, int $page, int $perPage = 20): array
    {
        $where  = '';
        $params = [];
        if ($search !== '') {
            // Single placeholder (PDO has emulation off, so it can't be reused).
            $where = "WHERE CONCAT_WS(' ', c.name, c.email, c.phone) LIKE :s";
            $params[':s'] = '%' . $search . '%';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM companies c {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page   = max(1, $page);
        $pages  = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT c.*, COUNT(u.id) AS user_count
                FROM companies c
                LEFT JOIN users u ON u.company_id = c.id
                {$where}
                GROUP BY c.id
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public function countAll(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM companies");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM companies WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $company = $stmt->fetch();
        return $company ?: null;
    }

    public function update(int $id, string $name, ?string $email, ?string $address, ?string $phone): bool
    {
        $sql = "UPDATE companies SET name = :name, email = :email, address = :address, phone = :phone
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':name'    => $name,
            ':email'   => $email !== '' ? $email : null,
            ':address' => $address !== '' ? $address : null,
            ':phone'   => $phone !== '' ? $phone : null,
            ':id'      => $id,
        ]);
    }

    /**
     * Permanently delete a company and ALL of its tenant data, in one transaction.
     * Users cascade via FK, but the data tables have no FK, so delete them explicitly.
     */
    public function delete(int $id): void
    {
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        try {
            foreach (['invoice_items', 'invoices', 'documents', 'templates', 'clients', 'users'] as $table) {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE company_id = :id");
                $stmt->execute([':id' => $id]);
            }
            $stmt = $pdo->prepare("DELETE FROM companies WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
