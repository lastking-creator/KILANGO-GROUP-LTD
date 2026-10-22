<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private const HEADING_ROW = 5;

    public function __construct(private array $report)
    {
    }

    public function array(): array
    {
        $r = $this->report;
        $keys = array_column($r['columns'], 'key');

        $data = [
            [$r['title']],
            [$r['subtitle']],
            ['Generated: '.now()->format('d M Y H:i')],
            [],
            array_column($r['columns'], 'label'),
        ];

        foreach ($r['rows'] as $row) {
            $line = [];
            foreach ($r['columns'] as $col) {
                $value = $row[$col['key']] ?? null;
                $line[] = in_array($col['type'], ['money', 'qty', 'int'], true) && $value !== null && $value !== ''
                    ? (float) $value
                    : $value;
            }
            $data[] = $line;
        }

        if ($r['totals']) {
            $line = [];
            foreach ($r['columns'] as $col) {
                $line[] = $r['totals'][$col['key']] ?? '';
            }
            $data[] = $line;
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            self::HEADING_ROW => ['font' => ['bold' => true]],
        ];

        if ($this->report['totals']) {
            $styles[self::HEADING_ROW + count($this->report['rows']) + 1] = ['font' => ['bold' => true]];
        }

        return $styles;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->report['title']), 0, 31);
    }
}
