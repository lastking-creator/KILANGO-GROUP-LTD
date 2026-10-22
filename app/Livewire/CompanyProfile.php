<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Support\Company;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class CompanyProfile extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $tin = '';
    public string $footer = '';
    public $logo = null;

    public string $message = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $this->name = (string) Setting::get('company_name', '');
        $this->address = (string) Setting::get('company_address', '');
        $this->phone = (string) Setting::get('company_phone', '');
        $this->email = (string) Setting::get('company_email', '');
        $this->tin = (string) Setting::get('company_tin', '');
        $this->footer = (string) Setting::get('document_footer', '');
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:150'],
            'tin' => ['nullable', 'string', 'max:60'],
            'footer' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ], [], ['name' => 'company name', 'tin' => 'tax or registration number', 'logo' => 'logo']);

        Setting::put('company_name', $data['name']);
        Setting::put('company_address', $data['address'] ?: null);
        Setting::put('company_phone', $data['phone'] ?: null);
        Setting::put('company_email', $data['email'] ?: null);
        Setting::put('company_tin', $data['tin'] ?: null);
        Setting::put('document_footer', $data['footer'] ?: null);

        if ($this->logo) {
            $old = Setting::get('company_logo');
            $path = $this->logo->store('branding', 'public');

            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }

            Setting::put('company_logo', $path);
            $this->logo = null;
        }

        $this->message = 'Company details saved. They now appear on every document.';
    }

    public function removeLogo(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $old = Setting::get('company_logo');
        if ($old && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        Setting::put('company_logo', null);
        $this->message = 'Logo removed.';
    }

    public function render()
    {
        return view('livewire.company-profile', ['company' => Company::profile()]);
    }
}
