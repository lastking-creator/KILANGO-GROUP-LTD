<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/** The business details printed at the top of every document. */
class Company
{
    public static function profile(): array
    {
        $logo = null;
        $path = Setting::get('company_logo');

        if ($path && Storage::disk('public')->exists($path)) {
            $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
            // Embedded in the page so it also works in PDF files
            $logo = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
        }

        return [
            'name' => Setting::get('company_name') ?: config('app.name'),
            'address' => Setting::get('company_address'),
            'phone' => Setting::get('company_phone'),
            'email' => Setting::get('company_email'),
            'tin' => Setting::get('company_tin'),
            'footer' => Setting::get('document_footer'),
            'logo' => $logo,
        ];
    }
}
