<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\StockLevel;
use App\Services\InsufficientStockException;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class PosScreen extends Component
{
    public string $outletId = '';          // chosen by admins; staff use their own outlet
    public string $search = '';

    public array $cart = [];               // keyed "p<productId>"
    public string $discount = '';
    public string $customerId = '';

    public bool $showNewCustomer = false;
    public string $newCustomerName = '';
    public string $newCustomerPhone = '';
    public string $newCustomerLimit = '';

    public string $cashTendered = '';
    public string $mobileAmount = '';
    public string $mobileRef = '';
    public string $cardAmount = '';
    public string $cardRef = '';
    public string $accountAmount = '';

    public string $openingCash = '';
    public bool $showCloseShift = false;
    public string $countedCash = '';

    public ?int $lastSaleId = null;
    public string $message = '';
    public string $error = '';

    private const ROLES = ['admin', 'outlet_manager', 'cashier'];

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::ROLES, true), 403);
    }

    // ---------- Helpers ----------

    private function isManager(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'outlet_manager'], true);
    }

    private function currentOutletId(): ?int
    {
        $user = auth()->user();

        if ($user->role === 'admin') {
            return $this->outletId !== '' ? (int) $this->outletId : null;
        }

        return $user->outlet_id ? (int) $user->outlet_id : null;
    }

    private function activeShift(?int $outletId): ?Shift
    {
        if (! $outletId) {
            return null;
        }

        return Shift::where('outlet_id', $outletId)
            ->where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->latest('id')
            ->first();
    }

    /** Sellable quantity of each product at an outlet (expired batches are not sellable). */
    private function availability(int $outletId)
    {
        return StockLevel::query()
            ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
            ->where('stock_levels.location_type', 'outlet')
            ->where('stock_levels.location_id', $outletId)
            ->where('stock_levels.quantity', '>', 0)
            ->where(function ($q) {
                $q->whereNull('pb.expiry_date')->orWhere('pb.expiry_date', '>=', now()->toDateString());
            })
            ->groupBy('pb.product_id')
            ->selectRaw('pb.product_id as product_id, SUM(stock_levels.quantity) as qty')
            ->pluck('qty', 'product_id');
    }

    private function num($value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function resetMessages(): void
    {
        $this->message = '';
        $this->error = '';
        $this->lastSaleId = null;
    }

    private function resetSale(): void
    {
        $this->reset([
            'cart', 'discount', 'customerId', 'cashTendered', 'mobileAmount', 'mobileRef',
            'cardAmount', 'cardRef', 'accountAmount', 'showNewCustomer',
            'newCustomerName', 'newCustomerPhone', 'newCustomerLimit',
        ]);
        $this->resetValidation();
    }

    public function updatedOutletId(): void
    {
        $this->resetSale();
        $this->resetMessages();
        $this->showCloseShift = false;
    }

    // ---------- Shift ----------

    public function openShift(): void
    {
        $this->resetMessages();
        $outletId = $this->currentOutletId();

        if (! $outletId || ! Outlet::where('id', $outletId)->where('is_active', true)->exists()) {
            $this->error = 'Choose an active outlet first.';

            return;
        }

        $this->validate(['openingCash' => ['nullable', 'numeric', 'min:0']], [], ['openingCash' => 'opening cash']);

        if ($this->activeShift($outletId)) {
            $this->error = 'You already have an open shift here.';

            return;
        }

        Shift::create([
            'outlet_id' => $outletId,
            'user_id' => auth()->id(),
            'opened_at' => now(),
            'opening_cash' => $this->num($this->openingCash),
        ]);

        $this->openingCash = '';
        $this->message = 'Shift opened. You can start selling.';
    }

    private function shiftTotals(Shift $shift): array
    {
        $byMethod = Payment::query()
            ->leftJoin('sales', 'sales.id', '=', 'payments.sale_id')
            ->where('payments.shift_id', $shift->id)
            ->where(function ($q) {
                $q->whereNull('payments.sale_id')->orWhere('sales.status', 'completed');
            })
            ->groupBy('payments.method')
            ->selectRaw('payments.method as method, SUM(payments.amount) as total')
            ->pluck('total', 'method');

        $sales = Sale::where('shift_id', $shift->id)->where('status', 'completed');

        return [
            'byMethod' => $byMethod,
            'count' => (clone $sales)->count(),
            'total' => (float) (clone $sales)->sum('total'),
            'expectedCash' => round((float) $shift->opening_cash + (float) ($byMethod['cash'] ?? 0), 2),
        ];
    }

    public function startCloseShift(): void
    {
        $this->resetMessages();
        $this->countedCash = '';
        $this->showCloseShift = true;
    }

    public function cancelCloseShift(): void
    {
        $this->showCloseShift = false;
        $this->resetValidation();
    }

    public function confirmCloseShift(): void
    {
        $this->resetMessages();
        $shift = $this->activeShift($this->currentOutletId());

        if (! $shift) {
            $this->showCloseShift = false;

            return;
        }

        $this->validate(['countedCash' => ['required', 'numeric', 'min:0']], [], ['countedCash' => 'counted cash']);

        $totals = $this->shiftTotals($shift);
        $counted = round($this->num($this->countedCash), 2);

        $shift->update([
            'closed_at' => now(),
            'expected_cash' => $totals['expectedCash'],
            'counted_cash' => $counted,
        ]);

        $difference = round($counted - $totals['expectedCash'], 2);
        $this->message = 'Shift closed. Expected cash '.number_format($totals['expectedCash'], 2)
            .', counted '.number_format($counted, 2)
            .($difference == 0 ? ': the cash matches.' : ($difference > 0 ? ': '.number_format($difference, 2).' over.' : ': '.number_format(abs($difference), 2).' short.'));

        $this->showCloseShift = false;
        $this->countedCash = '';
        $this->resetSale();
    }

    // ---------- Cart ----------

    public function addToCart(int $productId): void
    {
        $this->resetMessages();
        $outletId = $this->currentOutletId();
        abort_unless($outletId && $this->activeShift($outletId), 403);

        $product = Product::with('baseUnit')->where('is_active', true)->findOrFail($productId);
        $available = (float) ($this->availability($outletId)[$productId] ?? 0);
        $key = 'p'.$productId;
        $current = (float) ($this->cart[$key]['quantity'] ?? 0);

        if ($available <= 0 || $current + 1 > $available + 0.0005) {
            $this->error = $available <= 0
                ? "{$product->name} is out of stock at this outlet."
                : "Only {$this->fmt($available)} {$product->baseUnit?->abbreviation} of {$product->name} available.";

            return;
        }

        $this->cart[$key] = [
            'productId' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->baseUnit?->abbreviation,
            'price' => (float) $product->selling_price,
            'quantity' => $current + 1,
            'max' => $available,
        ];
    }

    public function increment(string $key): void
    {
        if (isset($this->cart[$key])) {
            $this->addToCart((int) $this->cart[$key]['productId']);
        }
    }

    public function decrement(string $key): void
    {
        $this->resetMessages();

        if (! isset($this->cart[$key])) {
            return;
        }

        $this->cart[$key]['quantity'] = (float) $this->cart[$key]['quantity'] - 1;

        if ($this->cart[$key]['quantity'] <= 0) {
            unset($this->cart[$key]);
        }
    }

    public function removeFromCart(string $key): void
    {
        unset($this->cart[$key]);
    }

    public function clearCart(): void
    {
        $this->resetSale();
        $this->resetMessages();
    }

    public function updatedCart($value, $key): void
    {
        $parts = explode('.', (string) $key, 2);

        if (($parts[1] ?? null) === 'quantity' && isset($this->cart[$parts[0]])) {
            $max = (float) $this->cart[$parts[0]]['max'];

            if ($this->num($value) > $max) {
                $this->cart[$parts[0]]['quantity'] = $max;
                $this->error = "Only {$this->fmt($max)} available.";
            }
        }
    }

    public function addByCode(): void
    {
        $code = trim($this->search);
        if ($code === '') {
            return;
        }

        $product = Product::where('is_active', true)
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', strtoupper($code)))
            ->first();

        if ($product) {
            $this->addToCart($product->id);
            $this->search = '';
        }
    }

    // ---------- Payment helpers ----------

    public function payExact(): void
    {
        $this->cashTendered = (string) $this->totals()['total'];
    }

    public function putBalanceOnAccount(): void
    {
        $t = $this->totals();
        $this->accountAmount = (string) max(0, round($t['total'] - $this->num($this->mobileAmount) - $this->num($this->cardAmount) - $this->num($this->cashTendered), 2));
    }

    private function totals(): array
    {
        $subtotal = 0.0;
        foreach ($this->cart as $line) {
            $q = $this->num($line['quantity'] ?? 0);
            if ($q > 0) {
                $subtotal += round((float) $line['price'] * $q, 2);
            }
        }

        $discount = min($this->num($this->discount), $subtotal);
        $total = round(max(0, $subtotal - $discount), 2);
        $nonCash = $this->num($this->mobileAmount) + $this->num($this->cardAmount) + $this->num($this->accountAmount);
        $cashDue = round(max(0, $total - $nonCash), 2);
        $tendered = $this->num($this->cashTendered);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total' => $total,
            'cashDue' => $cashDue,
            'change' => round(max(0, $tendered - $cashDue), 2),
            'short' => round(max(0, $cashDue - $tendered), 2),
            'over' => $nonCash > $total + 0.005,
        ];
    }

    private function fmt($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    }

    // ---------- Customers ----------

    public function saveNewCustomer(): void
    {
        abort_unless($this->isManager(), 403);

        $data = $this->validate([
            'newCustomerName' => ['required', 'string', 'max:150'],
            'newCustomerPhone' => ['nullable', 'string', 'max:30'],
            'newCustomerLimit' => ['nullable', 'numeric', 'min:0'],
        ], [], ['newCustomerName' => 'name', 'newCustomerPhone' => 'phone', 'newCustomerLimit' => 'credit limit']);

        $customer = Customer::create([
            'name' => $data['newCustomerName'],
            'phone' => $data['newCustomerPhone'] ?: null,
            'credit_limit' => $this->num($data['newCustomerLimit'] ?? 0),
        ]);

        $this->customerId = (string) $customer->id;
        $this->reset(['showNewCustomer', 'newCustomerName', 'newCustomerPhone', 'newCustomerLimit']);
    }

    // ---------- Complete the sale ----------

    public function completeSale(): void
    {
        $this->resetMessages();
        $user = auth()->user();
        $outletId = $this->currentOutletId();

        $outlet = $outletId ? Outlet::where('id', $outletId)->where('is_active', true)->first() : null;
        if (! $outlet) {
            $this->error = 'Choose an active outlet first.';

            return;
        }

        $shift = $this->activeShift($outletId);
        if (! $shift) {
            $this->error = 'Open a shift before selling.';

            return;
        }

        $cart = collect($this->cart)->filter(fn ($l) => $this->num($l['quantity'] ?? 0) > 0)->all();
        if (! $cart) {
            $this->error = 'The cart is empty.';

            return;
        }

        $this->validate([
            'discount' => ['nullable', 'numeric', 'min:0'],
            'cashTendered' => ['nullable', 'numeric', 'min:0'],
            'mobileAmount' => ['nullable', 'numeric', 'min:0'],
            'cardAmount' => ['nullable', 'numeric', 'min:0'],
            'accountAmount' => ['nullable', 'numeric', 'min:0'],
            'mobileRef' => ['nullable', 'string', 'max:80'],
            'cardRef' => ['nullable', 'string', 'max:80'],
        ]);

        $products = Product::whereIn('id', collect($cart)->pluck('productId'))->where('is_active', true)->get()->keyBy('id');

        $subtotal = 0.0;
        foreach ($cart as $line) {
            $product = $products->get((int) $line['productId']);
            if (! $product) {
                $this->error = 'A product in the cart is no longer active. Remove it and try again.';

                return;
            }
            $subtotal += round((float) $product->selling_price * round($this->num($line['quantity']), 3), 2);
        }
        $subtotal = round($subtotal, 2);

        $discount = round($this->num($this->discount), 2);
        if ($discount > 0 && ! $this->isManager()) {
            $this->error = 'Only a manager can give a discount.';

            return;
        }
        if ($discount > $subtotal) {
            $this->error = 'The discount is more than the total.';

            return;
        }

        $total = round($subtotal - $discount, 2);
        if ($total <= 0) {
            $this->error = 'The total must be more than zero.';

            return;
        }

        $mobile = round($this->num($this->mobileAmount), 2);
        $card = round($this->num($this->cardAmount), 2);
        $account = round($this->num($this->accountAmount), 2);
        $tendered = round($this->num($this->cashTendered), 2);
        $nonCash = round($mobile + $card + $account, 2);

        if ($nonCash > $total + 0.005) {
            $this->error = 'Mobile money, card and account amounts add up to more than the total.';

            return;
        }

        $cashDue = round($total - $nonCash, 2);
        if ($tendered + 0.005 < $cashDue) {
            $this->error = 'The payment is short by '.number_format($cashDue - $tendered, 2).'. Add cash, or put the balance on account.';

            return;
        }
        $change = round($tendered - $cashDue, 2);

        $customer = null;
        if ($account > 0.005) {
            $customer = $this->customerId !== '' ? Customer::where('is_active', true)->find((int) $this->customerId) : null;

            if (! $customer) {
                $this->error = 'Choose a customer to sell on account.';

                return;
            }

            if ((float) $customer->credit_limit <= 0) {
                $this->error = "{$customer->name} has no credit limit, so cannot buy on account.";

                return;
            }

            if ($customer->outstanding() + $account > (float) $customer->credit_limit + 0.005) {
                $this->error = "This would take {$customer->name} over the credit limit of ".number_format((float) $customer->credit_limit, 2)
                    .'. They already owe '.number_format($customer->outstanding(), 2).'.';

                return;
            }
        } elseif ($this->customerId !== '') {
            $customer = Customer::find((int) $this->customerId);
        }

        try {
            $sale = DB::transaction(function () use ($cart, $products, $outlet, $shift, $user, $customer, $subtotal, $discount, $total, $mobile, $card, $account, $cashDue, $change) {
                $stock = app(StockService::class);

                $sale = Sale::create([
                    'receipt_no' => 'TMP-'.Str::uuid(),
                    'outlet_id' => $outlet->id,
                    'cashier_id' => $user->id,
                    'shift_id' => $shift->id,
                    'customer_id' => $customer?->id,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'amount_paid' => round($total - $account, 2),
                    'amount_on_account' => $account,
                    'change_given' => $change,
                    'status' => 'completed',
                ]);

                foreach ($cart as $line) {
                    $product = $products->get((int) $line['productId']);
                    $needed = round($this->num($line['quantity']), 3);
                    $price = (float) $product->selling_price;

                    // Take from the batches that expire first
                    $batches = StockLevel::query()
                        ->join('product_batches as pb', 'pb.id', '=', 'stock_levels.batch_id')
                        ->where('stock_levels.location_type', 'outlet')
                        ->where('stock_levels.location_id', $outlet->id)
                        ->where('pb.product_id', $product->id)
                        ->where('stock_levels.quantity', '>', 0)
                        ->where(function ($q) {
                            $q->whereNull('pb.expiry_date')->orWhere('pb.expiry_date', '>=', now()->toDateString());
                        })
                        ->orderByRaw('pb.expiry_date IS NULL, pb.expiry_date')
                        ->orderBy('pb.id')
                        ->lockForUpdate()
                        ->select('stock_levels.batch_id', 'stock_levels.quantity')
                        ->get();

                    foreach ($batches as $batch) {
                        if ($needed <= 0.0005) {
                            break;
                        }

                        $take = round(min($needed, (float) $batch->quantity), 3);

                        SaleItem::create([
                            'sale_id' => $sale->id,
                            'product_id' => $product->id,
                            'batch_id' => $batch->batch_id,
                            'quantity' => $take,
                            'unit_price' => $price,
                            'line_total' => round($price * $take, 2),
                        ]);

                        $stock->change($batch->batch_id, 'outlet', $outlet->id, -$take, 'sale', 'sale', $sale->id, $user->id);
                        $needed = round($needed - $take, 3);
                    }

                    if ($needed > 0.0005) {
                        throw new InsufficientStockException("There is not enough {$product->name} in stock.");
                    }
                }

                $pay = function (string $method, float $amount, ?string $reference = null) use ($sale, $shift, $user, $customer) {
                    return Payment::create([
                        'sale_id' => $sale->id,
                        'customer_id' => $customer?->id,
                        'shift_id' => $shift->id,
                        'method' => $method,
                        'amount' => $amount,
                        'reference' => $reference ?: null,
                        'user_id' => $user->id,
                    ]);
                };

                if ($cashDue > 0.005) {
                    $pay('cash', $cashDue);
                }
                if ($mobile > 0.005) {
                    $pay('mobile_money', $mobile, trim($this->mobileRef));
                }
                if ($card > 0.005) {
                    $pay('card', $card, trim($this->cardRef));
                }
                if ($account > 0.005) {
                    $payment = $pay('on_account', $account);

                    CustomerLedger::create([
                        'customer_id' => $customer->id,
                        'entry_type' => 'debit',
                        'amount' => $account,
                        'sale_id' => $sale->id,
                        'payment_id' => $payment->id,
                        'note' => 'Sale on account',
                    ]);
                }

                $sale->update(['receipt_no' => $outlet->code.'-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

                return $sale;
            });
        } catch (InsufficientStockException $e) {
            $this->error = $e->getMessage().' Refresh the list and adjust the cart.';

            return;
        }

        $this->resetSale();
        $this->lastSaleId = $sale->id;
        $this->message = "Sale {$sale->receipt_no} completed.";
        $this->dispatch('sale-completed');
    }

    // ---------- Render ----------

    public function render()
    {
        $outletId = $this->currentOutletId();
        $outlet = $outletId ? Outlet::find($outletId) : null;
        $shift = $this->activeShift($outletId);
        $user = auth()->user();

        $products = collect();
        $availability = collect();
        $shiftTotals = null;

        if ($outlet && $shift) {
            $availability = $this->availability($outlet->id);
            $term = trim($this->search);

            $products = Product::with('baseUnit')
                ->where('is_active', true)
                ->whereIn('id', $availability->keys())
                ->when($term !== '', function ($q) use ($term) {
                    $q->where(function ($q2) use ($term) {
                        $q2->where('name', 'like', "%{$term}%")
                            ->orWhere('sku', 'like', "%{$term}%")
                            ->orWhere('barcode', 'like', "%{$term}%");
                    });
                })
                ->orderBy('name')
                ->limit(60)
                ->get();

            $shiftTotals = $this->shiftTotals($shift);
        }

        $customer = $this->customerId !== '' ? Customer::find((int) $this->customerId) : null;

        return view('livewire.pos-screen', [
            'outlet' => $outlet,
            'outlets' => $user->role === 'admin' ? Outlet::where('is_active', true)->orderBy('code')->get() : collect(),
            'shift' => $shift,
            'shiftTotals' => $shiftTotals,
            'products' => $products,
            'availability' => $availability,
            'totals' => $this->totals(),
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'customer' => $customer,
            'customerOwes' => $customer ? $customer->outstanding() : 0,
            'isManager' => $this->isManager(),
            'lastSale' => $this->lastSaleId ? Sale::find($this->lastSaleId) : null,
            'staffWithoutOutlet' => $user->role !== 'admin' && ! $user->outlet_id,
        ]);
    }
}
