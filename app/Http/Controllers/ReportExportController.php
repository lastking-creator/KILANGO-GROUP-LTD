<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Services\ReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController
{
    public function __invoke(Request $request, string $type, string $format)
    {
        $user = auth()->user();

        abort_unless(array_key_exists($type, ReportBuilder::availableFor($user->role)), 403);
        abort_unless(in_array($format, ['print', 'pdf', 'xlsx'], true), 404);

        $report = ReportBuilder::build($type, ReportBuilder::filters($request->only(['from', 'to', 'outlet', 'days']), $user));
        $name = Str::slug($report['title'].' '.now()->format('Y-m-d'));

        if ($format === 'xlsx') {
            return Excel::download(new ReportExport($report), $name.'.xlsx');
        }

        if ($format === 'pdf') {
            return Pdf::loadView('exports.report', ['report' => $report, 'forPdf' => true, 'autoPrint' => false])
                ->setPaper('a4', count($report['columns']) > 6 ? 'landscape' : 'portrait')
                ->download($name.'.pdf');
        }

        return view('exports.report', ['report' => $report, 'forPdf' => false, 'autoPrint' => true]);
    }
}
