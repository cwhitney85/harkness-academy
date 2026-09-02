<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * `db:backup` -- the restore point taken before anything destructive.
 *
 * The development database holds a real course and no copy of it exists
 * anywhere else, so the two things worth pinning are that a dump is really
 * written and that a failure leaves nothing behind that could be restored in
 * good faith.
 */
class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_writes_a_restorable_dump_of_the_current_connection(): void
    {
        if ((new ExecutableFinder)->find('pg_dump') === null) {
            $this->markTestSkipped('pg_dump is not on the PATH.');
        }

        $path = storage_path('framework/testing/backup-test.sql');

        $this->artisan('db:backup', ['--path' => $path])->assertSuccessful();

        $this->assertFileExists($path);
        $this->assertStringContainsString(
            'CREATE TABLE public.courses',
            (string) file_get_contents($path),
            'A dump that cannot rebuild the courses table is not a backup.',
        );

        unlink($path);
    }

    #[Test]
    public function it_refuses_a_connection_it_cannot_dump(): void
    {
        config()->set('database.connections.scratch', ['driver' => 'sqlite', 'database' => ':memory:']);

        $this->artisan('db:backup', ['--connection' => 'scratch'])->assertFailed();
    }

    #[Test]
    public function a_failed_dump_leaves_no_file_to_restore_from(): void
    {
        if ((new ExecutableFinder)->find('pg_dump') === null) {
            $this->markTestSkipped('pg_dump is not on the PATH.');
        }

        $path = storage_path('framework/testing/backup-failure.sql');

        config()->set('database.connections.pgsql.database', 'no_such_database_here');

        $this->artisan('db:backup', ['--path' => $path])->assertFailed();

        $this->assertFileDoesNotExist($path);
    }
}
