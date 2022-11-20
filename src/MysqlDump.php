<?php

declare(strict_types=1);

namespace MNIB;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Process\Process;
use function dirname;
use function escapeshellarg;
use function file_exists;
use function is_dir;
use function is_iterable;
use function sprintf;

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
        $resolver = new OptionsResolver();
        $resolver
            ->setDefined([
                'defaults_extra_file',
                'max_allowed_packet',
                'dump_type',
                'selected_tables',
                'ignored_tables',
            ])
            ->setDefault('file', null)
            ->setDefault('mysqldump_bin', 'mysqldump')
            ->setDefault('archive', null)
            ->setDefault('archive_pattern', 'pbzip2 --compress --best -c %1$s > %2$s')
            ->setDefault('archive_pipe', false)
            ->setDefault('hex_blob', false)
            ->setAllowedTypes('file', ['null', 'string'])
            ->setAllowedTypes('defaults_extra_file', ['string'])
            ->setAllowedTypes('dump_type', ['null', 'string'])
            ->setAllowedTypes('selected_tables', ['string[]'])
            ->setAllowedTypes('ignored_tables', ['string[]'])
            ->setAllowedTypes('archive', ['null', 'string'])
            ->setAllowedTypes('archive_pipe', ['bool'])
            ->setAllowedTypes('hex_blob', ['bool'])
            ->setAllowedValues('dump_type', ['schema', 'data'])
            ->setNormalizer('file', function(Options $options, $file) {
                if ($file === null && $options['archive'] === null && !$options['archive_pipe']) {
                    throw new InvalidArgumentException('"file" option must be set when "archive" is not set and "archive_pipe" is false.');
                }

                return $file;
            })
            ->setNormalizer('archive', function(Options $options, $archive) {
                if ($archive === null && $options['file'] === null) {
                    throw new InvalidArgumentException('"archive" option must be set when "file" is not set.');
                }

                return $archive;
            })
        ;
        $options = $resolver->resolve($options);

        if ($options['file'] !== null && !is_dir(dirname($options['file']))) {
            throw new RuntimeException(sprintf(
                'Directory "%s" does not exist for file value of "%s".',
                dirname($options['file']),
                $options['file']
            ));
        }

        $command = escapeshellarg($options['mysqldump_bin']);

        if (isset($options['defaults_extra_file']) && $options['defaults_extra_file']) {
            if (!file_exists($options['defaults_extra_file'])) {
                throw new RuntimeException(
                    sprintf('Defaults extra file missing "%s".', $options['defaults_extra_file'])
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
            $command .= sprintf(' --port=%s', $this->port);
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
            }
        }

        if ($options['hex_blob']) {
            $command .= ' --hex-blob';
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
        if ($options['file'] && !$options['archive_pipe']) {
            $command .= sprintf(' > %s', escapeshellarg($options['file']));
        }

        if ($options['archive'] && $options['archive_pattern']) {
            $command .= $options['archive_pipe'] ? ' | ' : ' && ';

            $command .= sprintf(
                $options['archive_pattern'],
                escapeshellarg((string)$options['file']),
                escapeshellarg((string)$options['archive'])
            );
        }

        $this->execute($command);

        return true;
    }

    protected function execute(string $command): string
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }

        return $process->getOutput();
    }
}
