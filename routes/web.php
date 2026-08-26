<?php

use App\Http\Controllers\CalendarAvailabilityController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CashShiftController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClosingSessionController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\CustomerSubscriptionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceDashboardController;
use App\Http\Controllers\FinancialObligationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OnlineBookingSettingsController;
use App\Http\Controllers\PackageTemplateController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\SaleCategoryController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleItemController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::prefix('book/{tenant:slug}/{unit:slug}')
    ->scopeBindings()
    ->middleware('throttle:public-booking')
    ->group(function (): void {
        Route::get('/', [PublicBookingController::class, 'show'])->name('public_booking.show');
        Route::get('/availability', [PublicBookingController::class, 'availability'])->name('public_booking.availability');
        Route::post('/appointments', [PublicBookingController::class, 'store'])
            ->middleware('throttle:public-booking-create')
            ->name('public_booking.appointments.store');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('tenant.context')
        ->name('dashboard');

    Route::middleware('tenant.context')->group(function (): void {
        Route::get('online-booking', [OnlineBookingSettingsController::class, 'index'])->name('online_booking.index');
        Route::patch('online-booking', [OnlineBookingSettingsController::class, 'update'])->name('online_booking.update');
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
        Route::patch('categories/{category}/reactivate', [CategoryController::class, 'reactivate'])->name('categories.reactivate');
        Route::resource('customers', CustomerController::class)
            ->except(['create', 'edit']);
        Route::patch('customers/{customer}/reactivate', [CustomerController::class, 'reactivate'])->name('customers.reactivate');
        Route::resource('products', ProductController::class)
            ->except(['create', 'edit']);
        Route::patch('products/{product}/reactivate', [ProductController::class, 'reactivate'])->name('products.reactivate');
        Route::resource('professionals', ProfessionalController::class)
            ->except(['create', 'edit']);
        Route::patch('professionals/{professional}/reactivate', [ProfessionalController::class, 'reactivate'])->name('professionals.reactivate');
        Route::resource('services', ServiceController::class)
            ->except(['create', 'edit']);
        Route::patch('services/{service}/reactivate', [ServiceController::class, 'reactivate'])->name('services.reactivate');
        Route::resource('suppliers', SupplierController::class)
            ->except(['create', 'edit']);
        Route::patch('suppliers/{supplier}/reactivate', [SupplierController::class, 'reactivate'])->name('suppliers.reactivate');
        Route::resource('sale-categories', SaleCategoryController::class)
            ->except(['create', 'edit']);
        Route::patch('sale-categories/{sale_category}/reactivate', [SaleCategoryController::class, 'reactivate'])->name('sale-categories.reactivate');
        Route::resource('packages', PackageTemplateController::class)
            ->parameters(['packages' => 'package_template'])
            ->except(['create', 'edit']);
        Route::patch('packages/{package_template}/reactivate', [PackageTemplateController::class, 'reactivate'])->name('packages.reactivate');
        Route::post('customer-packages', [CustomerPackageController::class, 'store'])->name('customer-packages.store');
        Route::post('customer-packages/{customer_package}/consume', [CustomerPackageController::class, 'consume'])->name('customer-packages.consume');
        Route::resource('sales', SaleController::class)
            ->only(['index', 'show', 'store']);
        Route::resource('closing-sessions', ClosingSessionController::class)
            ->only(['show', 'store']);
        Route::post('sales/{sale}/discount', [SaleController::class, 'applyDiscount'])->name('sales.discount');
        Route::post('sales/{sale}/transition', [SaleController::class, 'transitionStatus'])->name('sales.transition');
        Route::post('sales/{sale}/adjust', [SaleController::class, 'adjust'])->name('sales.adjust');
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
        Route::get('finance/dashboard', FinanceDashboardController::class)->name('finance.dashboard');
        Route::get('finance/transactions', [FinancialObligationController::class, 'index'])->name('financial_obligations.index');
        Route::post('finance/transactions', [FinancialObligationController::class, 'store'])->name('financial_obligations.store');
        Route::get('finance/transactions/{financialObligation}', [FinancialObligationController::class, 'show'])->name('financial_obligations.show');
        Route::put('finance/transactions/{financialObligation}', [FinancialObligationController::class, 'update'])->name('financial_obligations.update');
        Route::post('finance/transactions/{financialObligation}/settle', [FinancialObligationController::class, 'settle'])->name('financial_obligations.settle');
        Route::post('finance/transactions/{financialObligation}/cancel', [FinancialObligationController::class, 'cancel'])->name('financial_obligations.cancel');
        Route::get('finance/commissions', [CommissionController::class, 'index'])->name('commissions.index');
        Route::get('finance/commissions/professionals/{professional}', [CommissionController::class, 'show'])->name('commissions.show');
        Route::post('finance/commissions/rules', [CommissionController::class, 'storeRule'])->name('commissions.rules.store');
        Route::put('finance/commissions/rules/{rule}', [CommissionController::class, 'updateRule'])->name('commissions.rules.update');
        Route::delete('finance/commissions/rules/{rule}', [CommissionController::class, 'deleteRule'])->name('commissions.rules.destroy');
        Route::post('finance/commissions/settle', [CommissionController::class, 'settle'])->name('commissions.settle');
        Route::resource('subscriptions', SubscriptionPlanController::class)
            ->parameters(['subscriptions' => 'subscription_plan'])
            ->except(['create', 'edit']);
        Route::patch('subscriptions/{subscription_plan}/reactivate', [SubscriptionPlanController::class, 'reactivate'])->name('subscriptions.reactivate');
        Route::post('customer-subscriptions', [CustomerSubscriptionController::class, 'store'])->name('customer-subscriptions.store');
        Route::post('customer-subscriptions/{customer_subscription}/cancel', [CustomerSubscriptionController::class, 'cancel'])->name('customer-subscriptions.cancel');
        Route::post('customer-subscriptions/{customer_subscription}/pause', [CustomerSubscriptionController::class, 'pause'])->name('customer-subscriptions.pause');
        Route::post('customer-subscriptions/{customer_subscription}/resume', [CustomerSubscriptionController::class, 'resume'])->name('customer-subscriptions.resume');
    });
});

require __DIR__.'/settings.php';
