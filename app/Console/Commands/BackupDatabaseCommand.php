<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * A restore point for the development database, before anything risky.
 *
 * The database holds a real course being taught to a real student and no
 * copy of it exists anywhere else (Colin, 2026-09-02). A standing convention
 * keeps destructive commands away from it; this is the answer to the times
 * one is genuinely wanted.
 *
 * It reads the connection Laravel is already configured with rather than
 * taking host and database as arguments, so a backup cannot quietly be taken
 * of the wrong database -- and the credentials stay in `.env`, never on a
 * command line where `ps` would show them.
 *
 * Pre:  the connection is PostgreSQL and `pg_dump` is on the PATH.
 * Post: one plain-SQL dump exists at the reported path, and the database is
 *       untouched -- nothing here opens a write.
 * On failure: no file is left behind, and the exit code is non-zero.
 */
class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup
        {--path= : Write the dump here instead of storage/backups}
        {--connection= : Back up a connection other than the default}';

    protected $description = 'Dump the database to a restorable SQL file before anything destructive';

    /**
     * Long enough that two backups a minute apart do not collide, short
     * enough to read at a glance.
     */
    private const STAMP = 'Y-m-d-His';

    public function handle(): int
    {
        $name = $this->option('connection') ?: config('database.default');
        $config = config("database.connections.{$name}");

        if (! is_array($config) || ($config['driver'] ?? null) !== 'pgsql') {
            $this->components->error("Connection [{$name}] is not PostgreSQL; this command only speaks pg_dump.");

            return self::FAILURE;
        }

        $binary = (new ExecutableFinder)->find('pg_dump');

        if ($binary === null) {
            $this->components->error('pg_dump is not on the PATH. Install the PostgreSQL client tools, or add them to it.');

            return self::FAILURE;
        }

        $path = $this->destination($config['database']);

        $process = new Process([
            $binary,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--username='.$config['username'],
            // Roles and tablespaces belong to the cluster, not to this
            // database, and a restore into a fresh one should not need them.
            '--no-owner',
            '--no-privileges',
            '--file='.$path,
            $config['database'],
        ], env: ['PGPASSWORD' => (string) $config['password']]);

        // A dump of a course's transcripts is not a fast operation to
        // interrupt halfway; the default 60 seconds is not this command's to
        // impose.
        $process->setTimeout(null);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            // A half-written dump restores to a half-written database, which
            // is worse than no dump at all.
            if (is_file($path)) {
                unlink($path);
            }

            $this->components->error('pg_dump failed: '.trim($process->getErrorOutput() ?: $e->getMessage()));

            return self::FAILURE;
        }

        $this->components->info(sprintf('Backed up [%s] to %s (%s).', $config['database'], $path, $this->size($path)));
        $this->components->twoColumnDetail('Restore with', sprintf(
            'psql --host=%s --port=%s --username=%s --dbname=%s --file=%s',
            $config['host'],
            $config['port'],
            $config['username'],
            $config['database'],
            $path,
        ));

        return self::SUCCESS;
    }

    /**
     * Where the dump goes, with the directory made if it is not there yet.
     */
    private function destination(string $database): string
    {
        $path = $this->option('path');

        if (is_string($path) && $path !== '') {
            return $path;
        }

        $directory = storage_path('backups');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, recursive: true);
        }

        return $directory.'/'.$database.'-'.now()->format(self::STAMP).'.sql';
    }

    private function size(string $path): string
    {
        $bytes = (int) filesize($path);

        return $bytes < 1048576
            ? round($bytes / 1024).' KB'
            : round($bytes / 1048576, 1).' MB';
    }
}
