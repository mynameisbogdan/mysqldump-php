<?php
namespace MNIB;

use Symfony\Component\Process\Process;

/**
 * Generates an archived db backup using mysqldump and pbzip2 (parallel bzip2)
 */
class MysqlDump
{
    /** @var string */
    private $dbname;

    /** @var null|string */
    private $host;

    /** @var null|string */
    private $port;

    /** @var null|string */
    private $user;

    /** @var null|string */
    private $password;

    public function __construct($dbname, $host = null, $port = null, $user = null, $password = null)
    {
        $this->dbname = $dbname;
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * @param string $file
     * @param array $selectedTables
     * @param array $ignoreTables
     */
    public function createBackup($file, array $selectedTables = [], array $ignoreTables = [])
    {
        if (!isset($file) || !$file) {
            throw new \RuntimeException('Required parameter "$file" is not set.');
        }

        if (!is_dir(dirname($file))) {
            throw new \RuntimeException(sprintf(
                'Directory %s does not exist for file value of %s',
                dirname($file),
                $file
            ));
        }

        $command = sprintf(
            'mysqldump --max_allowed_packet=1G --single-transaction --routines --triggers %s',
            escapeshellarg($this->dbname)
        );

        if (null !== $this->host && $this->host) {
            $command .= sprintf(' --host=%s', escapeshellarg($this->host));
        }

        if (null !== $this->port && $this->port) {
            $command .= sprintf(' --port=%s', escapeshellarg($this->port));
        }

        if (null !== $this->user && $this->user) {
            $command .= sprintf(' --user=%s', escapeshellarg($this->user));
        }

        if (null !== $this->password && $this->password) {
            $command .= sprintf(' --password=%s', escapeshellarg($this->password));
        }

        foreach ($selectedTables as $table) {
            $command .= ' ' . escapeshellarg($table);
        }

        foreach ($ignoreTables as $table) {
            $command .= sprintf(' --ignore-table=%s.%s', escapeshellarg($this->dbname), escapeshellarg($table));
        }

        // Remove DEFINER clause
        $command .= ' | sed \'s/DEFINER=[^*]*\*/\*/g\'';

        // Save to file
        $command .= sprintf(' > %s', escapeshellarg($file));

        $this->execute($command);
    }

    /**
     * @param string $file
     * @return string
     */
    public function archiveBackup($file)
    {
        if (!isset($file) || !$file) {
            throw new \RuntimeException('Required parameter "$file" is not set.');
        }

        if (!is_dir(dirname($file))) {
            throw new \RuntimeException(sprintf(
                'Directory %s does not exist for file value of %s',
                dirname($file),
                $file
            ));
        }

        $archive = sprintf('%s.bz2', $file);

        $command = sprintf(
            'pbzip2 --best -f %s > %s',
            escapeshellarg($file),
            escapeshellarg($archive)
        );

        $this->execute($command);

        return $archive;
    }

    /**
     * @param string $command
     * @return string
     */
    protected function execute($command)
    {
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        return $process->getOutput();
    }
}
