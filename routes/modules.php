<?php

use App\Http\Controllers\BlockStockExportController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SaleReceiptController;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureUserIsActive::class])->group(function () {
    // Who may open each page. The sidebar shows the same tabs for each role.
    $roles = fn (string ...$allowed) => EnsureRole::class.':'.implode(',', $allowed);

    Route::view('inventory', 'modules.inventory')->middleware($roles('admin', 'godown_keeper', 'outlet_manager'))->name('inventory');
    Route::view('outlets', 'modules.outlets')->middleware($roles('admin', 'outlet_manager'))->name('outlets');
    Route::view('godowns', 'modules.godowns')->middleware($roles('admin', 'godown_keeper'))->name('godowns');
    Route::view('transfers', 'modules.transfers')->middleware($roles('admin', 'godown_keeper', 'outlet_manager'))->name('transfers');

    // Sales
    Route::view('sales', 'modules.sales')->middleware($roles('admin', 'outlet_manager', 'cashier'))->name('sales');
    Route::view('sales/history', 'modules.sales-history')->middleware($roles('admin', 'outlet_manager', 'cashier'))->name('sales.history');

    // Purchase
    Route::view('purchases', 'modules.purchases')->middleware($roles('admin', 'godown_keeper'))->name('purchases');
    Route::view('purchases/suppliers', 'modules.suppliers')->middleware($roles('admin', 'godown_keeper'))->name('suppliers');

    // Finance
    Route::view('finance', 'modules.finance')->middleware($roles('admin', 'outlet_manager'))->name('finance');
    Route::view('finance/expenses', 'modules.expenses')->middleware($roles('admin', 'outlet_manager'))->name('expenses');

    Route::view('customers', 'modules.customers')->middleware($roles('admin', 'outlet_manager', 'cashier'))->name('customers');
    Route::view('reports', 'modules.reports')->middleware($roles('admin', 'outlet_manager', 'godown_keeper'))->name('reports');

    // Employees
    Route::view('employees', 'modules.employees')->middleware($roles('admin', 'godown_keeper', 'outlet_manager'))->name('employees');
    Route::view('employees/attendance', 'modules.attendance')->middleware($roles('admin', 'godown_keeper', 'outlet_manager'))->name('attendance');

    // Documents
    Route::view('documents', 'modules.documents')->middleware($roles('admin', 'godown_keeper', 'outlet_manager', 'cashier'))->name('documents');
    Route::view('documents/company', 'modules.company')->middleware($roles('admin'))->name('documents.company');
    Route::get('documents/{type}/{id}/{format}', DocumentController::class)
        ->whereIn('type', ['invoice', 'grn', 'expense-voucher', 'customer-settlement', 'cash-handover'])
        ->whereNumber('id')
        ->whereIn('format', ['print', 'pdf'])
        ->name('documents.show');

    Route::view('users', 'modules.users')->middleware($roles('admin'))->name('users');

    // Products-in-block: print, PDF and Excel
    Route::middleware($roles('admin', 'godown_keeper'))->group(function () {
        Route::get('godowns/blocks/{block}/stock/print', [BlockStockExportController::class, 'print'])->name('blocks.stock.print');
        Route::get('godowns/blocks/{block}/stock/pdf', [BlockStockExportController::class, 'pdf'])->name('blocks.stock.pdf');
        Route::get('godowns/blocks/{block}/stock/xlsx', [BlockStockExportController::class, 'xlsx'])->name('blocks.stock.xlsx');
    });

    // Sale receipt (opens a print-ready page)
    Route::get('sales/{sale}/receipt', [SaleReceiptController::class, 'show'])
        ->middleware($roles('admin', 'outlet_manager', 'cashier'))
        ->name('sales.receipt');

    // Reports: print, PDF and Excel (the controller also checks which reports the role may see)
    Route::get('reports/{type}/{format}', ReportExportController::class)
        ->middleware($roles('admin', 'outlet_manager', 'godown_keeper'))
        ->whereIn('format', ['print', 'pdf', 'xlsx'])
        ->name('reports.export');
});
