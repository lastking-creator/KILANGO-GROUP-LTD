<?php

namespace App\Http\Controllers;

use App\Exports\BlockStockExport;
use App\Models\Block;
use App\Support\BlockStockReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class BlockStockExportController
{
    /** Clean page that opens the browser's print dialog. */
    public function print(Block $block)
    {
        return view('exports.block-stock', BlockStockReport::for($block) + [
            'forPdf' => false,
            'autoPrint' => true,
        ]);
    }

    public function pdf(Block $block)
    {
        $data = BlockStockReport::for($block) + ['forPdf' => true, 'autoPrint' => false];

        return Pdf::loadView('exports.block-stock', $data)
            ->setPaper('a4')
            ->download($this->fileName($block, 'pdf'));
    }

    public function xlsx(Block $block)
    {
        return Excel::download(
            new BlockStockExport(BlockStockReport::for($block)),
            $this->fileName($block, 'xlsx')
        );
    }

    private function fileName(Block $block, string $extension): string
    {
        $block->loadMissing('godown');

        return Str::slug("stock {$block->godown->code} block {$block->code} ".now()->format('Y-m-d')).'.'.$extension;
    }
}
