<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Encryption.php';

class Client
{
    private Database $db;
    private Encryption $enc;

    private array $encryptedFieldsCompany = [];
    private array $encryptedFieldsIndividual = ['embg', 'id_card_number'];

    public function __construct(Database $db, Encryption $enc)
    {
        $this->db = $db;
        $this->enc = $enc;
    }

    public function createCompany(
        int $companyId,
        string $companyName,
        string $headquarters,
        string $embs,
        string $edb,
        string $manager,
        ?string $email = null,
        ?string $phone = null,
        ?int $createdBy = null
    ): int {
        $sql = "INSERT INTO clients (company_id, type, company_name, headquarters, embs, edb, manager, email, phone, created_by, created_at)
                VALUES (:company_id, :type, :company_name, :headquarters, :embs, :edb, :manager, :email, :phone, :created_by, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':company_id'   => $companyId,
            ':type'         => 'company',
            ':company_name' => $companyName,
            ':headquarters' => $headquarters,
            ':embs'         => $embs,
            ':edb'          => $edb,
            ':manager'      => $manager,
            ':email'        => $email !== '' ? $email : null,
            ':phone'        => $phone !== '' ? $phone : null,
            ':created_by'   => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createIndividual(
        int $companyId,
        string $fullName,
        string $address,
        string $embg,
        string $idCardNumber,
        ?string $email = null,
        ?string $phone = null,
        ?int $createdBy = null
    ): int {
        $sql = "INSERT INTO clients (company_id, type, full_name, address, embg, id_card_number, email, phone, created_by, created_at)
                VALUES (:company_id, :type, :full_name, :address, :embg, :id_card_number, :email, :phone, :created_by, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':company_id'     => $companyId,
            ':type'           => 'individual',
            ':full_name'      => $fullName,
            ':address'        => $address,
            ':embg'           => $this->enc->encrypt($embg),
            ':id_card_number' => $this->enc->encrypt($idCardNumber),
            ':email'          => $email !== '' ? $email : null,
            ':phone'          => $phone !== '' ? $phone : null,
            ':created_by'     => $createdBy,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getAll(int $companyId): array
    {
        $sql = "SELECT c.*, u.name AS created_by_name
                FROM clients c
                LEFT JOIN users u ON u.id = c.created_by
                WHERE c.company_id = :company_id AND c.deleted_at IS NULL
                ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':company_id' => $companyId]);
        $clients = $stmt->fetchAll();

        return array_map(fn($client) => $this->decryptClient($client), $clients);
    }

    public function getById(int $id, int $companyId): ?array
    {
        $sql = "SELECT c.*, u.name AS created_by_name
                FROM clients c
                LEFT JOIN users u ON u.id = c.created_by
                WHERE c.id = :id AND c.company_id = :company_id AND c.deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':company_id' => $companyId]);
        $client = $stmt->fetch();

        return $client ? $this->decryptClient($client) : null;
    }

    /** Update a company client. Tenant-scoped; returns true if a row changed. */
    public function updateCompany(
        int $id,
        int $companyId,
        string $companyName,
        string $headquarters,
        string $embs,
        string $edb,
        string $manager,
        ?string $email = null,
        ?string $phone = null
    ): bool {
        $sql = "UPDATE clients
                SET company_name = :company_name,
                    headquarters = :headquarters,
                    embs         = :embs,
                    edb          = :edb,
                    manager      = :manager,
                    email        = :email,
                    phone        = :phone
                WHERE id = :id AND company_id = :company_id AND type = 'company' AND deleted_at IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':company_name' => $companyName,
            ':headquarters' => $headquarters,
            ':embs'         => $embs,
            ':edb'          => $edb,
            ':manager'      => $manager,
            ':email'        => $email !== '' ? $email : null,
            ':phone'        => $phone !== '' ? $phone : null,
            ':id'           => $id,
            ':company_id'   => $companyId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /** Update an individual client. Encrypts sensitive fields; tenant-scoped. */
    public function updateIndividual(
        int $id,
        int $companyId,
        string $fullName,
        string $address,
        string $embg,
        string $idCardNumber,
        ?string $email = null,
        ?string $phone = null
    ): bool {
        $sql = "UPDATE clients
                SET full_name      = :full_name,
                    address        = :address,
                    embg           = :embg,
                    id_card_number = :id_card_number,
                    email          = :email,
                    phone          = :phone
                WHERE id = :id AND company_id = :company_id AND type = 'individual' AND deleted_at IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':full_name'      => $fullName,
            ':address'        => $address,
            ':embg'           => $this->enc->encrypt($embg),
            ':id_card_number' => $this->enc->encrypt($idCardNumber),
            ':email'          => $email !== '' ? $email : null,
            ':phone'          => $phone !== '' ? $phone : null,
            ':id'             => $id,
            ':company_id'     => $companyId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /** Soft-delete: flag the client as deleted without removing the row. Tenant-scoped. */
    public function softDelete(int $id, int $companyId): bool
    {
        $sql = "UPDATE clients SET deleted_at = NOW()
                WHERE id = :id AND company_id = :company_id AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':company_id' => $companyId]);

        return $stmt->rowCount() > 0;
    }

    private function decryptClient(array $client): array
    {
        $fields = $client['type'] === 'company'
            ? $this->encryptedFieldsCompany
            : $this->encryptedFieldsIndividual;

        foreach ($fields as $field) {
            if (!empty($client[$field])) {
                $client[$field] = $this->enc->decrypt($client[$field]);
            }
        }

        return $client;
    }
}