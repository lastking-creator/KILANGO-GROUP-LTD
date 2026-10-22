<?php

namespace App\Console\Commands;

use App\Services\HikvisionClient;
use App\Services\HikvisionException;
use Illuminate\Console\Command;

class TestHikvisionConnection extends Command
{
    protected $signature = 'hikvision:test';

    protected $description = 'Make ONE request to the Hikvision device to check the address and login';

    public function handle(HikvisionClient $client): int
    {
        $this->info('Contacting '.config('hikvision.host').':'.config('hikvision.port').' (a single attempt)...');

        try {
            $info = $client->deviceInfo(force: true);
        } catch (HikvisionException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Connected.');
        $this->table(['Name', 'Model', 'Serial number', 'Firmware'], [[
            $info['name'] ?? '-', $info['model'] ?? '-', $info['serial'] ?? '-', $info['firmware'] ?? '-',
        ]]);

        return self::SUCCESS;
    }
}
