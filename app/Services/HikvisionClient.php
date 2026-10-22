<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Talks to a Hikvision access-control / attendance terminal over its web (ISAPI) interface.
 * The device uses HTTP Digest login. After one rejected login this client pauses itself for
 * 30 minutes so a wrong password is never retried in a loop (devices lock the account).
 */
class HikvisionClient
{
    public const LOCK_KEY = 'hikvision.auth_failed';

    private function baseUrl(): string
    {
        return 'http://'.config('hikvision.host').':'.config('hikvision.port');
    }

    private function http(): PendingRequest
    {
        return Http::withDigestAuth((string) config('hikvision.username'), (string) config('hikvision.password'))
            ->timeout((int) config('hikvision.timeout', 10));
    }

    private function guard(): void
    {
        if (Cache::has(self::LOCK_KEY)) {
            throw new HikvisionException(
                'Skipped to protect the device: the last login attempt was rejected. '
                .'Fix HIK_USERNAME and HIK_PASSWORD in .env, run "php artisan config:clear", then run "php artisan hikvision:test". '
                .'This pause also ends by itself after 30 minutes.'
            );
        }
    }

    private function send(callable $call): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException $e) {
            throw new HikvisionException(
                'Cannot reach the device at '.config('hikvision.host').':'.config('hikvision.port')
                .'. Check that it is switched on, on the same network as this computer, and that HIK_HOST in .env is correct.'
            );
        }

        if ($response->status() === 401) {
            Cache::put(self::LOCK_KEY, true, now()->addMinutes(30));

            throw new HikvisionException(
                'The device rejected the login (HTTP 401). Check HIK_USERNAME and HIK_PASSWORD in .env. '
                .'Do not keep retrying: after several wrong passwords the device locks the account for a while.'
            );
        }

        if ($response->failed()) {
            throw new HikvisionException('The device answered with HTTP '.$response->status().'.');
        }

        return $response;
    }

    /** One request to the device, to prove the address and login work. */
    public function deviceInfo(bool $force = false): array
    {
        if (! $force) {
            $this->guard();
        }

        $response = $this->send(fn () => $this->http()->get($this->baseUrl().'/ISAPI/System/deviceInfo'));
        Cache::forget(self::LOCK_KEY);

        $body = $response->body();
        $pick = function (string $tag) use ($body) {
            return preg_match('/<'.$tag.'>(.*?)<\/'.$tag.'>/s', $body, $m) ? trim($m[1]) : null;
        };

        return [
            'name' => $pick('deviceName'),
            'model' => $pick('model'),
            'serial' => $pick('serialNumber'),
            'firmware' => $pick('firmwareVersion'),
        ];
    }

    /**
     * Door/attendance events between two moments. Only events that carry an employee number
     * (a person who verified by face, finger or card) are returned.
     */
    public function events(Carbon $from, Carbon $to): array
    {
        $this->guard();

        $events = [];
        $position = 0;
        $searchId = (string) Str::uuid();

        for ($page = 0; $page < 200; $page++) {
            $response = $this->send(fn () => $this->http()->post(
                $this->baseUrl().'/ISAPI/AccessControl/AcsEvent?format=json',
                [
                    'AcsEventCond' => [
                        'searchID' => $searchId,
                        'searchResultPosition' => $position,
                        'maxResults' => 30,
                        'major' => 0,
                        'minor' => 0,
                        'startTime' => $from->format('Y-m-d\TH:i:sP'),
                        'endTime' => $to->format('Y-m-d\TH:i:sP'),
                    ],
                ]
            ));

            $acs = $response->json('AcsEvent') ?? [];
            $count = (int) ($acs['numOfMatches'] ?? 0);

            foreach ($acs['InfoList'] ?? [] as $item) {
                if (trim((string) ($item['employeeNoString'] ?? '')) !== '') {
                    $events[] = $item;
                }
            }

            $position += $count;

            if (($acs['responseStatusStrg'] ?? '') !== 'MORE' || $count === 0) {
                break;
            }
        }

        return $events;
    }
}
