<?php
declare(strict_types=1);
namespace Tajdid\Core\Database;
use PDO;
use Tajdid\Core\Config;
final class Connection
{
    private PDO $pdo;
    public function __construct(Config $config)
    {
        $this->pdo = new PDO($config->get('database.dsn'), $config->get('database.username'), $config->get('database.password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    public function select(string $sql, array $bindings = []): array { $statement = $this->pdo->prepare($sql); $statement->execute($bindings); return $statement->fetchAll(); }
    public function execute(string $sql, array $bindings = []): int { $statement = $this->pdo->prepare($sql); $statement->execute($bindings); return $statement->rowCount(); }
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try { $result = $callback($this); $this->pdo->commit(); return $result; }
        catch (\Throwable $exception) { $this->pdo->rollBack(); throw $exception; }
    }
}
