<?php

namespace App\Livewire;

use App\Models\Outlet;
use App\Services\ReportBuilder;
use Livewire\Component;

class ReportViewer extends Component
{
    public string $type = '';
    public string $from = '';
    public string $to = '';
    public string $outletId = '';
    public string $days = '30';

    public function mount(): void
    {
        $available = ReportBuilder::availableFor(auth()->user()?->role);
        abort_if(! $available, 403);

        $this->type = array_key_first($available);
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function render()
    {
        $user = auth()->user();
        $available = ReportBuilder::availableFor($user->role);

        if (! array_key_exists($this->type, $available)) {
            $this->type = array_key_first($available);
        }

        $meta = $available[$this->type];
        $input = ['from' => $this->from, 'to' => $this->to, 'outlet' => $this->outletId, 'days' => $this->days];
        $filters = ReportBuilder::filters($input, $user);
        $report = ReportBuilder::build($this->type, $filters);

        $query = array_filter([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'outlet' => $filters['outlet'],
            'days' => $filters['days'],
        ]);

        return view('livewire.report-viewer', [
            'available' => $available,
            'meta' => $meta,
            'report' => $report,
            'outlets' => $user->role === 'admin' ? Outlet::orderBy('code')->get() : collect(),
            'query' => $query,
        ]);
    }
}
