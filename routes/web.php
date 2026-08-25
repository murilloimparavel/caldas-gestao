<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\ServiceController;
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
        Route::resource('customers', CustomerController::class)
            ->except(['create', 'edit']);
        Route::resource('professionals', ProfessionalController::class)
            ->except(['create', 'edit']);
        Route::resource('services', ServiceController::class)
            ->except(['create', 'edit']);
    });
});

require __DIR__.'/settings.php';
