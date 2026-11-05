<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup
        {--keep=30 : Number of most recent backups to keep}
        {--dir= : Backup directory (defaults to storage/backups)}
        {--connection= : SQLite connection to back up (defaults to the default connection)}';

    protected $description = 'Back up the SQLite database to storage/backups (safe while the app is running)';

    public function handle(): int
    {
        $connection = DB::connection($this->option('connection') ?: null);

        if ($connection->getDriverName() !== 'sqlite') {
            $this->error('app:backup only supports SQLite connections.');

            return self::FAILURE;
        }

        $dir = $this->option('dir') ?: storage_path('backups');
        File::ensureDirectoryExists($dir);

        $base = 'cafepos-'.now()->format('Y-m-d-His');
        $path = $dir.DIRECTORY_SEPARATOR.$base.'.sqlite';

        for ($i = 2; File::exists($path); $i++) {
            $path = $dir.DIRECTORY_SEPARATOR.$base.'-'.$i.'.sqlite';
        }

        // VACUUM INTO produces a consistent, compacted copy even while the server is writing.
        $connection->statement('VACUUM INTO ?', [$path]);

        $this->prune($dir, max(1, (int) $this->option('keep')));

        $this->info($path);

        return self::SUCCESS;
    }

    private function prune(string $dir, int $keep): void
    {
        $backups = collect(File::glob($dir.DIRECTORY_SEPARATOR.'cafepos-*.sqlite'))
            ->sortDesc(SORT_NATURAL)
            ->values();

        $backups->slice($keep)->each(fn (string $file) => File::delete($file));
    }
}
