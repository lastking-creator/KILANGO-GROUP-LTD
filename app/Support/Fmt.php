<?php

namespace App\Support;

class Fmt
{
    public static function money($n): string
    {
        $currency = config('pos.currency');

        return number_format((float) $n, 2, '.', ',').($currency ? ' '.$currency : '');
    }

    public static function qty($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.') ?: '0';
    }
}
