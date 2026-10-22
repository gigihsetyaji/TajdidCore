<?php
declare(strict_types=1);
use Tajdid\Core\Database\{Migrator, Connection};
use Tajdid\Core\Config;
$db->transaction(function ($db): void {
    $db->execute('INSERT INTO notes (title) VALUES (?)', ['outer']);
    try {
        $db->transaction(function ($db): void {
            $db->execute('INSERT INTO notes (title) VALUES (?)', ['inner']);
            throw new RuntimeException('rollback inner');
        });
    } catch (RuntimeException) {}
    check(count($db->select('SELECT * FROM notes')) === 2, 'Inner rollback preserves outer transaction');
});
check(count($db->select('SELECT * FROM notes')) === 2, 'Outer transaction commits after inner rollback');
try {
    $db->transaction(function ($db): void {
        $db->transaction(fn ($db) => $db->execute('INSERT INTO notes (title) VALUES (?)', ['nested']));
        throw new RuntimeException('rollback outer');
    });
} catch (RuntimeException) {}
check(count($db->select('SELECT * FROM notes')) === 2, 'Outer rollback includes successful nested transaction');
$page = $db->paginate('SELECT * FROM notes ORDER BY id', [], 2, 1);
check(count($page['data']) === 1 && $page['total'] === 2 && $page['last_page'] === 2, 'Pagination counts and bounds data');
try { $db->paginate('SELECT * FROM notes', [], 0); throw new LogicException('Invalid page accepted'); }
catch (InvalidArgumentException) { check(true, 'Pagination rejects invalid bounds'); }
$app->container->instance(Config::class, new Config(['database' => ['dsn' => 'sqlite::memory:']]));
check($app->container->get(Connection::class) === $app->container->get(Connection::class), 'Application shares one database connection');
$directory = sys_get_temp_dir() . '/tajdid-migrations-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$file = $directory . '/001_create.php';
file_put_contents($file, '<?php return fn ($db) => $db->execute("CREATE TABLE migrated (id INTEGER PRIMARY KEY)");');
try {
    $migrator = new Migrator($db);
    check($migrator->migrate($directory) === ['001_create.php'], 'Migration applies schema');
    check($migrator->migrate($directory) === [], 'Migration is idempotent');
    file_put_contents($file, '<?php return fn ($db) => null;');
    try { $migrator->migrate($directory); throw new LogicException('Changed migration accepted'); }
    catch (RuntimeException $e) { check(str_contains($e->getMessage(), 'modified'), 'Migration checksum detects edits'); }
    unlink($file);
    file_put_contents($file, '<?php return function ($db) { $db->execute("CREATE TABLE broken (id INTEGER)"); throw new RuntimeException("fail"); };');
    // Use a new filename so it is not already registered.
    rename($file, $directory . '/002_broken.php');
    try { $migrator->migrate($directory); throw new LogicException('Broken migration accepted'); }
    catch (RuntimeException $e) { check($e->getMessage() === 'fail', 'Migration failure propagates'); }
    check($db->select("SELECT name FROM sqlite_master WHERE name = 'broken'") === [], 'Failed migration rolls back DDL');
    check(count($db->select('SELECT * FROM tajdid_migrations')) === 1, 'Failed migration not recorded');
} finally {
    foreach (glob($directory . '/*') ?: [] as $path) { unlink($path); }
    rmdir($directory);
}
