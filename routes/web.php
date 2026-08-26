<?php

use App\Http\Controllers\CalendarAvailabilityController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CashShiftController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClosingSessionController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\SaleCategoryController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleItemController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('tenant.context')
        ->name('dashboard');

    Route::middleware('tenant.context')->group(function (): void {
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
        Route::post('appointments', [CalendarController::class, 'store'])->name('appointments.store');
        Route::put('appointments/{appointment}', [CalendarController::class, 'update'])->name('appointments.update');
        Route::post('appointments/{appointment}/cancel', [CalendarController::class, 'cancel'])->name('appointments.cancel');
        Route::post('appointments/{appointment}/check-in', [CalendarController::class, 'checkIn'])->name('appointments.check_in');
        Route::post('availability-rules', [CalendarAvailabilityController::class, 'storeAvailabilityRule'])->name('availability_rules.store');
        Route::put('availability-rules/{availabilityRule}', [CalendarAvailabilityController::class, 'updateAvailabilityRule'])->name('availability_rules.update');
        Route::delete('availability-rules/{availabilityRule}', [CalendarAvailabilityController::class, 'deleteAvailabilityRule'])->name('availability_rules.destroy');
        Route::post('schedule-blocks', [CalendarAvailabilityController::class, 'storeScheduleBlock'])->name('schedule_blocks.store');
        Route::put('schedule-blocks/{scheduleBlock}', [CalendarAvailabilityController::class, 'updateScheduleBlock'])->name('schedule_blocks.update');
        Route::delete('schedule-blocks/{scheduleBlock}', [CalendarAvailabilityController::class, 'deleteScheduleBlock'])->name('schedule_blocks.destroy');
        Route::resource('categories', CategoryController::class)
            ->except(['create', 'edit']);
        Route::resource('customers', CustomerController::class)
            ->except(['create', 'edit']);
        Route::resource('products', ProductController::class)
            ->except(['create', 'edit']);
        Route::resource('professionals', ProfessionalController::class)
            ->except(['create', 'edit']);
        Route::resource('services', ServiceController::class)
            ->except(['create', 'edit']);
        Route::resource('suppliers', SupplierController::class)
            ->except(['create', 'edit']);
        Route::resource('sale-categories', SaleCategoryController::class)
            ->except(['create', 'edit']);
        Route::resource('sales', SaleController::class)
            ->only(['index', 'show', 'store']);
        Route::resource('closing-sessions', ClosingSessionController::class)
            ->only(['show', 'store']);
        Route::post('sales/{sale}/discount', [SaleController::class, 'applyDiscount'])->name('sales.discount');
        Route::post('sales/{sale}/transition', [SaleController::class, 'transitionStatus'])->name('sales.transition');
        Route::post('sales/{sale}/items', [SaleItemController::class, 'store'])->name('sales.items.store');
        Route::delete('sales/{sale}/items/{item}', [SaleItemController::class, 'destroy'])->name('sales.items.destroy');
        Route::get('finance/cash', [CashShiftController::class, 'index'])->name('cash_shifts.index');
        Route::get('finance/cash/history', [CashShiftController::class, 'history'])->name('cash_shifts.history');
        Route::post('finance/cash', [CashShiftController::class, 'store'])->name('cash_shifts.store');
        Route::get('finance/cash/{cashShift}', [CashShiftController::class, 'show'])->name('cash_shifts.show');
        Route::post('finance/cash/{cashShift}/move', [CashShiftController::class, 'move'])->name('cash_shifts.move');
        Route::post('finance/cash/{cashShift}/close', [CashShiftController::class, 'close'])->name('cash_shifts.close');
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('inventory/movements', [InventoryController::class, 'store'])->name('inventory.movements.store');
        Route::get('finance/commissions', [CommissionController::class, 'index'])->name('commissions.index');
        Route::get('finance/commissions/professionals/{professional}', [CommissionController::class, 'show'])->name('commissions.show');
        Route::post('finance/commissions/rules', [CommissionController::class, 'storeRule'])->name('commissions.rules.store');
        Route::put('finance/commissions/rules/{rule}', [CommissionController::class, 'updateRule'])->name('commissions.rules.update');
        Route::delete('finance/commissions/rules/{rule}', [CommissionController::class, 'deleteRule'])->name('commissions.rules.destroy');
        Route::post('finance/commissions/settle', [CommissionController::class, 'settle'])->name('commissions.settle');
    });
});

require __DIR__.'/settings.php';
