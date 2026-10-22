<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Support\Carbon;

/**
 * Turns the device's punches for one day into attendance records:
 *  - first punch of the day = check in (late if after work start + grace minutes)
 *  - last punch = check out, if it is far enough after the check in
 * Records that a person entered or edited by hand are never overwritten.
 */
class HikvisionAttendanceSync
{
    public function __construct(private HikvisionClient $client)
    {
    }

    public function run(Carbon $date): array
    {
        $day = $date->copy()->startOfDay();
        $events = $this->client->events($day->copy(), $day->copy()->endOfDay());

        $punches = [];
        foreach ($events as $event) {
            $no = trim((string) $event['employeeNoString']);

            if ($no === '' || empty($event['time'])) {
                continue;
            }

            $punches[$no][] = Carbon::parse($event['time'])->setTimezone(config('app.timezone'));
        }

        $employees = Employee::whereIn('device_employee_no', array_map('strval', array_keys($punches)))
            ->get()
            ->keyBy('device_employee_no');

        $lateAfter = Carbon::parse($day->toDateString().' '.config('hikvision.work_start'), config('app.timezone'))
            ->addMinutes((int) config('hikvision.late_after_minutes'));

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $unmatched = [];

        foreach ($punches as $no => $times) {
            $employee = $employees->get($no);

            if (! $employee) {
                $unmatched[] = (string) $no;

                continue;
            }

            $times = collect($times)->sort()->values();
            $first = $times->first();
            $last = $times->last();

            $checkOut = abs($first->diffInMinutes($last)) >= (int) config('hikvision.min_checkout_gap_minutes')
                ? $last->format('H:i:s')
                : null;

            $record = AttendanceRecord::firstOrNew([
                'employee_id' => $employee->id,
                'work_date' => $day->toDateString(),
            ]);

            if ($record->exists && $record->source === 'manual') {
                $skipped++;

                continue;
            }

            $isNew = ! $record->exists;

            $record->fill([
                'status' => $first->gt($lateAfter) ? 'late' : 'present',
                'check_in' => $first->format('H:i:s'),
                'check_out' => $checkOut,
            ]);
            $record->source = 'device';
            $record->save();

            $isNew ? $created++ : $updated++;
        }

        return [
            'events' => count($events),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'unmatched' => $unmatched,
        ];
    }
}
