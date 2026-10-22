<?php
declare(strict_types=1);
namespace Tajdid\Core\Database;
final class Migrator
{
    public function __construct(private Connection $db) {}
    /** Each trusted PHP file returns a callable(Connection $db): void. Forward-only. */
    public function migrate(string $directory): array
    {
        if (!in_array($this->db->driver(), ['sqlite', 'pgsql'], true)) {
            throw new \LogicException('Atomic migrations currently support SQLite and PostgreSQL only');
        }
        if (!is_dir($directory)) { throw new \InvalidArgumentException('Migration directory not found'); }
        $this->db->execute('CREATE TABLE IF NOT EXISTS tajdid_migrations (name VARCHAR(255) PRIMARY KEY, checksum VARCHAR(64) NOT NULL)');
        $applied = [];
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);
            $this->db->transaction(function (Connection $db) use ($file, $name, $checksum, &$applied): void {
                $rows = $db->select('SELECT checksum FROM tajdid_migrations WHERE name = ?', [$name]);
                if ($rows) {
                    if (!hash_equals($rows[0]['checksum'], $checksum)) { throw new \RuntimeException("Applied migration was modified: $name"); }
                    return;
                }
                $migration = require $file;
                if (!is_callable($migration)) { throw new \RuntimeException("Migration must return a callable: $name"); }
                $migration($db);
                $db->execute('INSERT INTO tajdid_migrations (name, checksum) VALUES (?, ?)', [$name, $checksum]);
                $applied[] = $name;
            });
        }
        return $applied;
    }
}
