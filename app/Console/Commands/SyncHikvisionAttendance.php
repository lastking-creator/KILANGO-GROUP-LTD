<?php

namespace App\Console\Commands;

use App\Services\HikvisionAttendanceSync;
use App\Services\HikvisionException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncHikvisionAttendance extends Command
{
    protected $signature = 'attendance:sync-hikvision
        {--date= : Day to import (Y-m-d). Default is today}
        {--days=1 : Import this many days, ending on that date}';

    protected $description = 'Import attendance punches from the Hikvision device';

    public function handle(HikvisionAttendanceSync $sync): int
    {
        $end = $this->option('date')
            ? Carbon::createFromFormat('Y-m-d', $this->option('date'))->startOfDay()
            : now()->startOfDay();

        $days = max(1, (int) $this->option('days'));

        try {
            for ($i = $days - 1; $i >= 0; $i--) {
                $day = $end->copy()->subDays($i);
                $result = $sync->run($day);

                $this->info(sprintf(
                    '%s: %d punches, %d new, %d updated, %d left alone (entered by hand).',
                    $day->toDateString(), $result['events'], $result['created'], $result['updated'], $result['skipped']
                ));

                if ($result['unmatched']) {
                    $this->warn('No employee has these device IDs yet: '.implode(', ', $result['unmatched']));
                }
            }
        } catch (HikvisionException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
