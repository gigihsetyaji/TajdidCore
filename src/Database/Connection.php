<?php
declare(strict_types=1);
namespace Tajdid\Core\Database;
use PDO;
use Tajdid\Core\Config;
final class Connection
{
    private PDO $pdo;
    private int $depth = 0;
    public function __construct(Config $config)
    {
        $this->pdo = new PDO($config->get('database.dsn'), $config->get('database.username'), $config->get('database.password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    public function select(string $sql, array $bindings = []): array { $statement = $this->pdo->prepare($sql); $statement->execute($bindings); return $statement->fetchAll(); }
    public function execute(string $sql, array $bindings = []): int { $statement = $this->pdo->prepare($sql); $statement->execute($bindings); return $statement->rowCount(); }
    public function transaction(callable $callback): mixed
    {
        $level = $this->depth;
        if ($level === 0) { $this->pdo->beginTransaction(); }
        else { $this->pdo->exec('SAVEPOINT tajdid_' . $level); }
        $this->depth++;
        try {
            $result = $callback($this);
            if ($level === 0) { $this->pdo->commit(); }
            else { $this->pdo->exec('RELEASE SAVEPOINT tajdid_' . $level); }
            return $result;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                if ($level === 0) { $this->pdo->rollBack(); }
                else {
                    $this->pdo->exec('ROLLBACK TO SAVEPOINT tajdid_' . $level);
                    $this->pdo->exec('RELEASE SAVEPOINT tajdid_' . $level);
                }
            }
            throw $exception;
        } finally { $this->depth = $level; }
    }
    public function driver(): string { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME); }
    /** SQL must be a trusted SELECT without a trailing semicolon. Bind all input values. */
    public function paginate(string $sql, array $bindings = [], int $page = 1, int $perPage = 20): array
    {
        if ($page < 1 || $perPage < 1 || $perPage > 100 || $page > intdiv(PHP_INT_MAX, $perPage)) {
            throw new \InvalidArgumentException('Invalid pagination range (perPage: 1..100)');
        }
        $total = (int) $this->select('SELECT COUNT(*) AS total FROM (' . $sql . ') AS tajdid_count', $bindings)[0]['total'];
        $offset = ($page - 1) * $perPage;
        return ['data' => $this->select($sql . " LIMIT $perPage OFFSET $offset", $bindings),
            'total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }
}
