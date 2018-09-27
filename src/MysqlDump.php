<?php

declare(strict_types=1);

namespace MNIB;

use Symfony\Component\Process\Process;
use function dirname;

/**
 * Generates an archived db backup using mysqldump and pbzip2 (parallel bzip2).
 */
class MysqlDump
{
    /** @var string */
    private $dbname;

    /** @var string|null */
    private $host;

    /** @var int|null */
    private $port;

    /** @var string|null */
    private $user;

    /** @var string|null */
    private $password;

    public function __construct(
        string $dbname,
        ?string $host = null,
        ?int $port = null,
        ?string $user = null,
        ?string $password = null
    ) {
        $this->dbname = $dbname;
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * @param mixed[] $options
     *
     * @return bool
     */
    public function run(array $options): bool
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

        if ($this->host !== null && $this->host) {
            $command .= sprintf(' --host=%s', escapeshellarg($this->host));
        }

        if ($this->port !== null && $this->port) {
            $command .= sprintf(' --port=%s', escapeshellarg($this->port));
        }

        if ($this->user !== null && $this->user) {
            $command .= sprintf(' --user=%s', escapeshellarg($this->user));
        }

        if ($this->password !== null && $this->password) {
            $command .= sprintf(' --password=%s', escapeshellarg($this->password));
        }

        $command .= sprintf(' --single-transaction --routines --triggers %s', escapeshellarg($this->dbname));

        if (isset($options['dump_type']) && $options['dump_type']) {
            switch ($options['dump_type']) {
                case 'schema':
                    $command .= ' --no-data';
                    break;
                case 'data':
                    $command .= ' --no-create-info --no-create-db --skip-triggers --skip-routines';
                    break;
                default:
                    throw new \UnexpectedValueException(sprintf(
                        'Dump type "%s" not valid. Valid options: null, "schema" or "data".',
                        $options['dump_type']
                    ));
            }
        }

        if (isset($options['selected_tables']) && is_iterable($options['selected_tables'])) {
            foreach ($options['selected_tables'] as $table) {
                $command .= ' ' . escapeshellarg($table);
            }
        }

        if (isset($options['ignored_tables']) && is_iterable($options['ignored_tables'])) {
            foreach ($options['ignored_tables'] as $table) {
                $command .= sprintf(' --ignore-table=%s.%s', escapeshellarg($this->dbname), escapeshellarg($table));
            }
        }

        // Remove DEFINER clause
        $command .= ' | sed \'s/DEFINER=[^ |\*]*//g\'';

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

    protected function execute(string $command): string
    {
        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        return $process->getOutput();
    }
}
