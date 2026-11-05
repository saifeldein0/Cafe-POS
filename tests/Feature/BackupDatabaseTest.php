<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // VACUUM cannot run inside RefreshDatabase's transaction, so back up a separate temp database file.
    $this->workDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cafepos-backup-test-'.uniqid();
    $this->backupDir = $this->workDir.DIRECTORY_SEPARATOR.'backups';
    File::ensureDirectoryExists($this->workDir);

    $source = $this->workDir.DIRECTORY_SEPARATOR.'source.sqlite';
    touch($source);

    config()->set('database.connections.backup_source', [
        'driver' => 'sqlite',
        'database' => $source,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    DB::connection('backup_source')->statement('create table things (id integer primary key, name text)');
    DB::connection('backup_source')->table('things')->insert(['name' => 'espresso']);
});

afterEach(function () {
    DB::purge('backup_source');
    File::deleteDirectory($this->workDir);
});

it('creates a timestamped sqlite backup containing the data', function () {
    $this->artisan('app:backup', ['--connection' => 'backup_source', '--dir' => $this->backupDir])
        ->assertSuccessful();

    $files = File::glob($this->backupDir.DIRECTORY_SEPARATOR.'cafepos-*.sqlite');
    expect($files)->toHaveCount(1)
        ->and(basename($files[0]))->toMatch('/^cafepos-\d{4}-\d{2}-\d{2}-\d{6}\.sqlite$/');

    $pdo = new PDO('sqlite:'.$files[0]);
    expect($pdo->query('select name from things')->fetchColumn())->toBe('espresso');
    $pdo = null;
});

it('prunes old backups down to the newest ones', function () {
    File::ensureDirectoryExists($this->backupDir);
    foreach (range(1, 31) as $day) {
        File::put($this->backupDir.DIRECTORY_SEPARATOR.sprintf('cafepos-2020-01-%02d-120000.sqlite', $day), '');
    }

    $this->artisan('app:backup', ['--connection' => 'backup_source', '--dir' => $this->backupDir])
        ->assertSuccessful();

    $remaining = collect(File::glob($this->backupDir.DIRECTORY_SEPARATOR.'cafepos-*.sqlite'))
        ->map(fn (string $f) => basename($f));

    expect($remaining)->toHaveCount(30)
        ->and($remaining)->not->toContain('cafepos-2020-01-01-120000.sqlite')
        ->and($remaining)->not->toContain('cafepos-2020-01-02-120000.sqlite')
        ->and($remaining)->toContain('cafepos-2020-01-31-120000.sqlite')
        ->and($remaining->filter(fn ($f) => ! str_starts_with($f, 'cafepos-2020-')))->toHaveCount(1);
});
