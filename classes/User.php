<?php

require_once __DIR__ . '/Database.php';

class User
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create a company user. Throws if the email already exists.
     * $role must be 'admin' or 'employee' (super-admins are seeded, not created here).
     */
    public function create(int $companyId, string $name, string $email, string $password, string $role): int
    {
        if (!in_array($role, ['admin', 'employee', 'praktikant'], true)) {
            throw new InvalidArgumentException('Невалидна улога.');
        }
        if ($this->getByEmail($email)) {
            throw new RuntimeException('Корисник со таа е-пошта веќе постои.');
        }

        $sql = "INSERT INTO users (company_id, name, email, password_hash, role, created_at)
                VALUES (:company_id, :name, :email, :hash, :role, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':company_id' => $companyId,
            ':name'       => $name,
            ':email'      => $email,
            ':hash'       => password_hash($password, PASSWORD_DEFAULT),
            ':role'       => $role,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.*, c.name AS company_name
             FROM users u
             LEFT JOIN companies c ON c.id = u.company_id
             WHERE u.email = :email"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /** Searchable, paginated users of a single company. Returns ['data','total','pages','page']. */
    public function getByCompanyPaged(int $companyId, string $search, int $page, int $perPage = 15): array
    {
        $where  = "WHERE company_id = :cid";
        $params = [':cid' => $companyId];
        if ($search !== '') {
            // Single placeholder (PDO has emulation off, so it can't be reused).
            $where .= " AND CONCAT_WS(' ', name, email) LIKE :s";
            $params[':s'] = '%' . $search . '%';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM users {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page   = max(1, $page);
        $pages  = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT id, name, email, role, created_at
             FROM users {$where}
             ORDER BY FIELD(role,'admin','employee','praktikant'), created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return ['data' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** Count all non-super-admin users (optionally filtered by role). */
    public function countUsers(?string $role = null): int
    {
        if ($role !== null) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role = :r");
            $stmt->execute([':r' => $role]);
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role <> 'super_admin'");
            $stmt->execute();
        }
        return (int) $stmt->fetchColumn();
    }

    /** All non-super-admin users, with their company name, for the admin panel. */
    public function getAllWithCompany(): array
    {
        $sql = "SELECT u.id, u.name, u.email, u.role, u.created_at,
                       u.company_id, c.name AS company_name
                FROM users u
                LEFT JOIN companies c ON c.id = u.company_id
                WHERE u.role <> 'super_admin'
                ORDER BY c.name ASC, u.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
