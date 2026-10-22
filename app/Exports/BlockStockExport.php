<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BlockStockExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private const HEADING_ROW = 7;

    public function __construct(private array $report)
    {
    }

    public function array(): array
    {
        $r = $this->report;
        $unit = $r['unit'] ? ' '.$r['unit'] : '';
        $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');

        $capacityLine = 'Capacity: '.($r['capacity'] === null ? '-' : $num($r['capacity']).$unit)
            .'   In stock: '.$num($r['total']).$unit
            .'   Available space: '.($r['available'] === null ? '-' : $num($r['available']).$unit);

        $data = [
            ['Stock report'],
            ['Godown: '.$r['godown']->name.' ('.$r['godown']->code.')'],
            ['Block: '.$r['block']->code.($r['block']->name ? ' - '.$r['block']->name : '').' ('.$r['block']->storage_type.')'],
            [$capacityLine],
            ['Generated: '.$r['generatedAt']->format('d M Y H:i')],
            [],
            ['Product', 'SKU', 'Batches', 'Quantity available', 'Unit', 'Earliest expiry'],
        ];

        foreach ($r['rows'] as $row) {
            $data[] = [
                $row->name,
                $row->sku,
                (int) $row->batches,
                (float) $row->quantity,      // a real number, so Excel can add it up
                $row->unit,
                $row->next_expiry ? \Illuminate\Support\Carbon::parse($row->next_expiry)->format('d M Y') : '-',
            ];
        }

        $data[] = ['Total', '', '', (float) $r['total'], '', ''];

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = self::HEADING_ROW + $this->report['rows']->count() + 1;

        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            self::HEADING_ROW => ['font' => ['bold' => true]],
            $lastRow => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', '', 'Block '.$this->report['block']->code);

        return mb_substr($name, 0, 31);
    }
}
