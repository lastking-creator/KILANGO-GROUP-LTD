<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('doc_title') {{ $number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; margin: 28px; }
        table { width: 100%; border-collapse: collapse; }
        .letterhead td { vertical-align: middle; }
        .letterhead .name { font-size: 18px; font-weight: bold; }
        .muted { color: #555; }
        .rule { border: 0; border-top: 2px solid #111; margin: 10px 0 14px 0; }
        .doc-title { font-size: 20px; font-weight: bold; letter-spacing: 1px; }
        .r { text-align: right; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .grid th, .grid td { border: 1px solid #bbb; padding: 6px 8px; text-align: left; vertical-align: top; }
        .grid th { background: #eee; }
        .grid td.num, .grid th.num { text-align: right; }
        .grid tfoot td { font-weight: bold; background: #f6f6f6; }
        .box { border: 1px solid #bbb; padding: 8px 10px; margin-top: 12px; }
        .sign { margin-top: 46px; }
        .sign td { width: 33%; padding-right: 20px; vertical-align: bottom; }
        .sign .line { border-top: 1px solid #111; padding-top: 4px; }
        .footer { margin-top: 28px; text-align: center; color: #555; font-size: 11px; }
        .stamp { display: inline-block; border: 3px solid #b00; color: #b00; font-weight: bold; padding: 2px 10px; }
        .toolbar { margin-bottom: 16px; }
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

    <table class="letterhead">
        <tr>
            @if ($company['logo'])
                <td style="width: 170px;"><img src="{{ $company['logo'] }}" alt="" style="max-height: 64px; max-width: 160px;"></td>
            @endif
            <td>
                <div class="name">{{ $company['name'] }}</div>
                @if ($company['address'])<div class="muted">{{ $company['address'] }}</div>@endif
                <div class="muted">
                    @if ($company['phone']){{ $company['phone'] }}@endif
                    @if ($company['phone'] && $company['email']) · @endif
                    @if ($company['email']){{ $company['email'] }}@endif
                </div>
                @if ($company['tin'])<div class="muted">Reg. / Tax no.: {{ $company['tin'] }}</div>@endif
            </td>
        </tr>
    </table>

    <hr class="rule">

    <table>
        <tr>
            <td><div class="doc-title">@yield('doc_title')</div></td>
            <td class="r">
                <div><strong>No.</strong> {{ $number }}</div>
                @yield('doc_meta')
            </td>
        </tr>
    </table>

    @yield('body')

    @yield('signatures')

    @if ($company['footer'])
        <div class="footer">{{ $company['footer'] }}</div>
    @endif

    @if ($autoPrint)
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
