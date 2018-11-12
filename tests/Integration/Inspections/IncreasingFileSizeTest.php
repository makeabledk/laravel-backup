<?php

namespace Spatie\Backup\Test\Integration\Inspections;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Spatie\Backup\Test\Integration\TestCase;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Spatie\Backup\Tasks\Monitor\Inspections\IncreasingFileSize;

class IncreasingFileSizeTest extends TestCase
{
    /** @var \Carbon\Carbon */
    protected $date;

    public function setUp()
    {
        parent::setUp();

        $this->app['config']->set('backup.monitorBackups.0', [
            'name' => config('app.name'),
            'disks' => ['local'],
            'newestBackupsShouldNotBeOlderThanDays' => 1,
            'storageUsedMayNotBeHigherThanMegabytes' => 5000,
            'inspections' => [IncreasingFileSize::class],
        ]);
    }

    /** @test **/
    public function it_is_considered_healthy_when_only_one_backup_present()
    {
        $this->fakeBackup(1);

        $this->expectsEvents(HealthyBackupWasFound::class);

        Artisan::call('backup:monitor');
    }

    /** @test **/
    public function it_is_considered_healthy_when_newest_backup_is_reduced_within_threshold()
    {
        $this->fakeBackup(1, 100);
        $this->fakeBackup(2, 96);

        $this->expectsEvents(HealthyBackupWasFound::class);

        Artisan::call('backup:monitor');
    }

    /** @test **/
    public function it_is_considered_unhealthy_when_newest_backup_is_reduced_beyond_threshold()
    {
        $this->fakeBackup(1, 100);
        $this->fakeBackup(2, 94);

        $this->expectsEvents(UnhealthyBackupWasFound::class);

        Artisan::call('backup:monitor');
    }

    /** @test **/
    public function reduction_tolerance_can_be_configured()
    {
        $this->app['config']->set('backup.monitorBackups.0.inspections', [
            IncreasingFileSize::class => ['reductionTolerance' => '10%'],
        ]);

        $this->fakeBackup(1, 100);
        $this->fakeBackup(2, 94);

        $this->expectsEvents(HealthyBackupWasFound::class);
        Artisan::call('backup:monitor');

        $this->fakeBackup(3, 80);
        $this->expectsEvents(UnhealthyBackupWasFound::class);
        Artisan::call('backup:monitor');
    }

    protected function fakeBackup($no, $sizeInKb = 1)
    {
        $this->testHelper->createTempFileWithAge(
            "mysite/backup-{$no}.zip",
            Carbon::now()->subSecond(10 - $no),
            random_bytes($sizeInKb * 1024)
        );
    }
}
