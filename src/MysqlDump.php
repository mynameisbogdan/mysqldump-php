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

    /**
     * Constructor.
     *
     * @param string $dbname
     * @param null $host
     * @param null $port
     * @param null $user
     * @param null $password
     */
    public function __construct($dbname, $host = null, $port = null, $user = null, $password = null)
    {
        $this->dbname = $dbname;
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * @param array $options
     * @return bool
     */
    public function run(array $options = [])
    {
        if (!isset($options['file']) || !$options['file']) {
            throw new \RuntimeException('Required parameter "$options[\'file\']" is not set.');
        }

        if (!is_dir(dirname($options['file']))) {
            throw new \RuntimeException(sprintf(
                'Directory %s does not exist for file value of %s',
                dirname($options['file']),
                $options['file']
            ));
        }

        $command = 'mysqldump';

        if (isset($options['defaults_extra_file']) && $options['defaults_extra_file']) {
            if (!file_exists($options['defaults_extra_file'])) {
                throw new \RuntimeException(
                    sprintf('Defaults extra file missing: %s', $options['defaults_extra_file'])
                );
            }

            $command .= sprintf(' --defaults-extra-file=%s', escapeshellarg($options['defaults_extra_file']));
        }

        if (isset($options['max_allowed_packet']) && $options['max_allowed_packet']) {
            $command .= sprintf(' --max_allowed_packet=%s', escapeshellarg($options['max_allowed_packet']));
        }

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

        $command .= sprintf(' --no-data --single-transaction --routines --triggers %s', escapeshellarg($this->dbname));

        if (isset($options['selected_tables']) && is_array($options['selected_tables'])) {
            foreach ($options['selected_tables'] as $table) {
                $command .= ' ' . escapeshellarg($table);
            }
        }

        if (isset($options['ignored_tables']) && is_array($options['selected_tables'])) {
            foreach ($options['ignored_tables'] as $table) {
                $command .= sprintf(' --ignore-table=%s.%s', escapeshellarg($this->dbname), escapeshellarg($table));
            }
        }

        // Remove DEFINER clause
        $command .= ' | sed \'s/DEFINER=[^*]*\*/\*/g\'';

        // Save to file
        $command .= sprintf(' > %s', escapeshellarg($options['file']));

        if (isset($options['archive']) && $options['archive']) {
            $command .= sprintf(
                ' && pbzip2 --compress --best -c %s > %s',
                escapeshellarg($options['file']),
                escapeshellarg($options['archive'])
            );
        }

        $this->execute($command);

        return true;
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
