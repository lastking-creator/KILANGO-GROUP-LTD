@php
    use App\Services\ReportBuilder;
    $numeric = fn ($col) => in_array($col['type'], ['money', 'qty', 'int'], true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #bbb; padding: 5px 7px; text-align: left; }
        th { background: #eee; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: bold; background: #f6f6f6; }
        .note { margin-top: 10px; color: #555; }
        .toolbar { margin-bottom: 14px; }
        .toolbar button { padding: 6px 14px; margin-right: 8px; cursor: pointer; }
        @media print { .toolbar { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar">
            <button onclick="window.print()">Print</button>
            <button onclick="window.close()">Close</button>
        </div>
    @endunless

    <h1>{{ $report['title'] }}</h1>
    <div class="muted">{{ $report['subtitle'] }}</div>
    <div class="muted">Generated {{ now()->format('d M Y H:i') }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($report['columns'] as $col)
                    <th class="{{ $numeric($col) ? 'num' : '' }}">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($report['columns'] as $col)
                        <td class="{{ $numeric($col) ? 'num' : '' }}">{{ ReportBuilder::format($col['type'], $row[$col['key']] ?? null) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}" style="text-align:center;">Nothing to show.</td></tr>
            @endforelse
        </tbody>
        @if ($report['totals'])
            <tfoot>
                <tr>
                    @foreach ($report['columns'] as $col)
                        <td class="{{ $numeric($col) ? 'num' : '' }}">
                            @if (array_key_exists($col['key'], $report['totals']))
                                {{ ReportBuilder::format($col['type'], $report['totals'][$col['key']]) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    @if ($report['note'])
        <div class="note">{{ $report['note'] }}</div>
    @endif

    @if ($autoPrint)
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
