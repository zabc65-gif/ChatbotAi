<?php
/**
 * Classe Database
 * Gestion de la connexion et des requêtes MySQL
 */

class Database
{
    private static ?PDO $instance = null;
    private PDO $pdo;

    public function __construct()
    {
        $this->connect();
    }

    /**
     * Établit la connexion à la base de données
     */
    private function connect(): void
    {
        if (self::$instance !== null) {
            $this->pdo = self::$instance;
            return;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->pdo = self::$instance;

        } catch (PDOException $e) {
            $this->logError('Database connection failed: ' . $e->getMessage());
            throw new Exception('Erreur de connexion à la base de données');
        }
    }

    /**
     * Retourne l'instance PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Exécute une requête préparée
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError('Query error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw new Exception('Erreur lors de l\'exécution de la requête');
        }
    }

    /**
     * Récupère toutes les lignes
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Récupère une seule ligne
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Insère une ligne et retourne l'ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Log des erreurs
     */
    private function logError(string $message): void
    {
        if (defined('LOG_FILE') && LOG_FILE) {
            $logDir = dirname(LOG_FILE);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents(LOG_FILE, "[{$timestamp}] {$message}\n", FILE_APPEND);
        }
    }
}
