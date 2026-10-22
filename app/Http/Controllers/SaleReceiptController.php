<?php

namespace App\Http\Controllers;

use App\Models\Sale;

class SaleReceiptController
{
    public function show(Sale $sale)
    {
        $user = auth()->user();

        abort_unless(in_array($user->role, ['admin', 'outlet_manager', 'cashier'], true), 403);

        if ($user->role !== 'admin') {
            abort_unless($user->outlet_id === $sale->outlet_id, 403);
        }

        $sale->load(['outlet', 'cashier', 'customer', 'items.product.baseUnit', 'payments']);

        return view('sales.receipt', ['sale' => $sale]);
    }
}
