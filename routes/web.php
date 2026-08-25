<?php

use App\Http\Controllers\CalendarAvailabilityController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfessionalController;
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
    });
});

require __DIR__.'/settings.php';
