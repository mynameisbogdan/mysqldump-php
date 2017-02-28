# Usage
```php
chdir(__DIR__);
$dump = new \MNIB\MysqlDump($dbname, $host, $port, $user, $password);

// Create dump
$dump->run([
    'file' => __DIR__ . '/dump.sql',
    'archive' => __DIR__ . '/dump.sql.bz2',
    'defaults_extra_file' => __DIR__ . '/custom-my-file.cnf',
    'max_allowed_packet' => '512M',
    'dump_type' => '', // Valid options: null, "schema" or "data"
    'selected_tables' => [
        'product',
    ],
    'ignored_tables' => [
        'orders',
    ],
]);
```
