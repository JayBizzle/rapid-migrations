<?php
namespace Jaybizzle\RapidMigrations;

use DB;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class Rapid
{
    /**
     * Path of the database that holds the clean import.
     *
     * @var string
     */
    protected $sourceDb = 'tests/source.sqlite';

    /**
     * The path of the database that the tests run against.
     *
     * @var string
     */
    protected $testDb = 'tests/ready.sqlite';

    /**
     * Make sure the testing database is up to date.
     */
    public function importSnapshot()
    {
        $this->migrationChanges() ? $this->newSnapshot() : $this->importDatabase();
    }

    /**
     * Determine if there have been migration changes since the last time the snapshot was updated.
     *
     * @return bool
     */
    protected function migrationChanges()
    {
        $snipeFile = config('rapid-migrations.rapidfile-location');
        $snipeDumpFile = config('rapid-migrations.snapshot-location');
        $storedTimeSum = file_exists($snipeFile) ? file_get_contents($snipeFile) : 0;

        $timeSum = collect(File::allFiles(database_path('migrations')))->sum(function ($file) {
            return $file->getMTime();
        });

        if (! $storedTimeSum || (int) $storedTimeSum !== $timeSum || ! file_exists($snipeDumpFile)) {
            // store the new time sum.
            file_put_contents($snipeFile, $timeSum);
            return true;
        }

        return false;
    }

    /**
     * Generate a new snapshot of the MySql database.
     */
    protected function newSnapshot()
    {
        // Artisan::call('migrate:fresh');
        $storageLocation = config('rapid-migrations.snapshot-location');

        // if (empty(shell_exec("which mysqldump"))) {
        //     throw new Exception('mysqldump is not available');
        // }

        $convertor = __DIR__.DIRECTORY_SEPARATOR.'mysql2sqlite';

        if (is_executable($convertor) === false) {
            exec('chmod +x '.$convertor);
        }

        dump("/Applications/MAMP/Library/bin/mysqldump --no-data -u {$this->getRootEnv('DB_USERNAME')} --password={$this->getRootEnv('DB_PASSWORD')} {$this->getRootEnv('DB_DATABASE')} | {$convertor} - > {$storageLocation} 2>/dev/null");

        // Store a snapshot of the db after migrations run.
        exec("/Applications/MAMP/Library/bin/mysqldump --no-data -u {$this->getRootEnv('DB_USERNAME')} --password={$this->getRootEnv('DB_PASSWORD')} {$this->getRootEnv('DB_DATABASE')} | {$convertor} - > {$storageLocation} 2>/dev/null");

        $this->importDatabase();
    }

    /**
     * Import the snapshot file into the database if it hasn't been imported yet.
     */
    protected function importDatabase()
    {
        if (! RapidDatabaseState::$imported) {
            $storageLocation = config('rapid-migrations.snapshot-location');

            @unlink(base_path($this->sourceDb));
            @unlink(base_path($this->testDb));

            touch(base_path($this->sourceDb));

            DB::unprepared(file_get_contents($storageLocation));

            copy(base_path($this->sourceDb), base_path($this->testDb));

            RapidDatabaseState::$imported = true;
        } else {
            $this->reuseExistingDatabase();
        }
    }

    public function reuseExistingDatabase()
    {
        @unlink(base_path($this->sourceDb));
        copy(base_path($this->testDb), base_path($this->sourceDb));
    }

    public function getRootEnv($name)
    {
        $command = 'awk -F= \'$1=="'.$name.'"{print $2;exit}\' '.base_path().'/.env';

        return exec($command);
    }
}