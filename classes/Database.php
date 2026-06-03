<?php

class Database
{
    private ?PDO $connection = null;
    private string $host;
    private string $dbName;
    private string $username;
    private string $password;

    public function __construct(
        string $host = 'localhost',
        string $dbName = 'lawyer',
        string $username = 'root',
        string $password = ''
    ) {
        $this->host = $host;
        $this->dbName = $dbName;
        $this->username = $username;
        $this->password = $password;
    }

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4";
                $this->connection = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                die("Грешка при конекција: " . $e->getMessage());
            }
        }

        return $this->connection;
    }

    public function prepare(string $sql): PDOStatement
    {
        return $this->getConnection()->prepare($sql);
    }

    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }
}