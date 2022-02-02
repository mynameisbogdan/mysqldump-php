# Usage
```php
chdir(__DIR__);
$dump = new \MNIB\MysqlDump($dbname, $host, $port, $user, $password);

// Create dump
$dump->run([
    'mysqldump_bin' => '/usr/bin/mysqldump',
    'file' => __DIR__ . '/dump.sql',
    'archive' => __DIR__ . '/dump.sql.bz2',
    'archive_pattern' => '/usr/bin/pbzip2 --compress --best -c %1$s > %2$s',
    'defaults_extra_file' => __DIR__ . '/custom-my-file.cnf',
    'max_allowed_packet' => '512M',
    'dump_type' => null, // Valid options: null, "schema" or "data"
    'selected_tables' => [
        'product',
    ],
    'ignored_tables' => [
        'orders',
    ],
]);
```
