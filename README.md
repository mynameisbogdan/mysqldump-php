# Usage
    chdir(__DIR__);
    $dump = new \MNIB\MysqlDump($dbname, $host, $port, $user, $password);
    ...
    // Create dump
    $dump->run(__DIR__ . '/dump.sql', [
        'file' => __DIR__ . '/dump.sql',
        'archive' => __DIR__ . '/dump.sql.bz2',
        'defaults_extra_file' => __DIR__ . '/custom-my-file.cnf',
        'max_allowed_packet' => '512M',
        'selected_tables' => [
            'product',
        ],
        'ignored_tables' => [
            'orders',
        ],
    ]);
