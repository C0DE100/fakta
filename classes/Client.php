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
        string $companyName,
        string $headquarters,
        string $embs,
        string $edb,
        string $manager
    ): int {
        $sql = "INSERT INTO clients (type, company_name, headquarters, embs, edb, manager, created_at)
                VALUES (:type, :company_name, :headquarters, :embs, :edb, :manager, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':type'         => 'company',
            ':company_name' => $companyName,
            ':headquarters' => $headquarters,
            ':embs'         => $embs,
            ':edb'          => $edb,
            ':manager'      => $manager,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createIndividual(
        string $fullName,
        string $address,
        string $embg,
        string $idCardNumber
    ): int {
        $sql = "INSERT INTO clients (type, full_name, address, embg, id_card_number, created_at)
                VALUES (:type, :full_name, :address, :embg, :id_card_number, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':type'           => 'individual',
            ':full_name'      => $fullName,
            ':address'        => $address,
            ':embg'           => $this->enc->encrypt($embg),
            ':id_card_number' => $this->enc->encrypt($idCardNumber),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getAll(): array
    {
        $sql = "SELECT * FROM clients ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $clients = $stmt->fetchAll();

        return array_map(fn($client) => $this->decryptClient($client), $clients);
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM clients WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $client = $stmt->fetch();

        return $client ? $this->decryptClient($client) : null;
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