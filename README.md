# Usage
    chdir(__DIR__);
    $dump = new \MNIB\MysqlDump($dbname, $host, $port, $user, $password);
    ...
    // Generates dump.sql
    $dump->createBackup(__DIR__ . '/dump.sql');
    ...
    // Archive dump.sql as dump.sql.bz2
    $archive = $dump->archiveBackup(__DIR__ . '/dump.sql');
