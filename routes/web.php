<?php

use App\Http\Controllers\BillingController;
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
use App\Http\Controllers\FirstLoginPasswordController;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LastlinkWebhookController;
use App\Http\Controllers\LegalRetentionController;
use App\Http\Controllers\OnlineBookingCampaignLinkController;
use App\Http\Controllers\OnlineBookingSettingsController;
use App\Http\Controllers\PackageTemplateController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\RetentionCampaignController;
use App\Http\Controllers\SaleCategoryController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleItemController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TenantDomainController;
use App\Http\Middleware\PreventOnlineBookingPreviewCaching;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/webhooks/lastlink', LastlinkWebhookController::class)
    ->middleware('throttle:30,1')
    ->name('webhooks.lastlink');

Route::get('/billing', BillingController::class)
    ->middleware(['auth', 'verified', 'tenant.context'])
    ->name('billing.index');

Route::get('/', function () {
    $domain = request()->attributes->get('tenant_domain');

    if ($domain?->kind?->value === 'public') {
        $tenant = $domain->tenant;

        return Inertia::render('public/coming-soon', [
            'branding' => [
                'name' => $tenant->brand_name ?: $tenant->name,
                'logoUrl' => $tenant->logo_url,
                'primaryColor' => $tenant->primary_color,
                'accentColor' => $tenant->accent_color,
            ],
        ]);
    }

    if ($domain !== null) {
        return redirect('/login');
    }

    return Inertia::render('marketing/home', [
        'branding' => [
            'name' => config('branding.name', config('app.name')),
            'logoUrl' => config('branding.logo_url'),
            'primaryColor' => config('branding.primary_color'),
            'accentColor' => config('branding.accent_color'),
        ],
    ]);
})->name('home');

Route::redirect('/signin', '/login')->name('signin');
Route::redirect('/signup', '/register')->name('signup');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/first-login/password', [FirstLoginPasswordController::class, 'edit'])->name('first-login-password.edit');
    Route::put('/first-login/password', [FirstLoginPasswordController::class, 'update'])->middleware('throttle:6,1')->name('first-login-password.update');
});

Route::get('/book/{public_slug}', [PublicBookingController::class, 'showBySlug'])
    ->middleware('throttle:public-booking')
    ->name('public_booking.slug');

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

Route::get('google-calendar/callback', [GoogleCalendarController::class, 'callback'])->name('google_calendar.callback');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('dashboard', DashboardController::class)
        ->middleware(['tenant.context', 'saas.access', 'first.login.complete'])
        ->name('dashboard');

    Route::middleware(['tenant.context', 'saas.access', 'first.login.complete'])->group(function (): void {
        Route::get('settings/domains', [TenantDomainController::class, 'index'])->name('tenant-domains.index');
        Route::post('settings/domains', [TenantDomainController::class, 'store'])->name('tenant-domains.store');
        Route::post('settings/domains/{tenantDomain}/verify', [TenantDomainController::class, 'verify'])->name('tenant-domains.verify');
        Route::post('settings/domains/{tenantDomain}/provision', [TenantDomainController::class, 'provision'])->name('tenant-domains.provision');
        Route::post('settings/domains/{tenantDomain}/activate', [TenantDomainController::class, 'activate'])->name('tenant-domains.activate');
        Route::get('online-booking', [OnlineBookingSettingsController::class, 'index'])->name('online_booking.index');
        Route::get('online-booking/editor', [OnlineBookingSettingsController::class, 'index'])->name('online_booking.editor');
        Route::get('online-booking/publications', [OnlineBookingSettingsController::class, 'index'])->name('online_booking.publications.index');
        Route::get('online-booking/preview/{tenant:slug}/{unit:slug}', [PublicBookingController::class, 'preview'])
            ->scopeBindings()
            ->middleware(['signed', PreventOnlineBookingPreviewCaching::class])
            ->name('online_booking.preview');
        Route::get('online-booking/campaign-links', [OnlineBookingCampaignLinkController::class, 'index'])->name('online_booking.campaign_links.index');
        Route::get('online-booking/publications/{publication}/preview', [PublicBookingController::class, 'previewPublication'])
            ->middleware(PreventOnlineBookingPreviewCaching::class)
            ->middleware('signed')
            ->name('online_booking.publication_preview');
        Route::get('online-booking/links', [OnlineBookingCampaignLinkController::class, 'index'])->name('online_booking.links');
        Route::post('online-booking/campaign-links', [OnlineBookingCampaignLinkController::class, 'store'])->name('online_booking.campaign_links.store');
        Route::patch('online-booking/campaign-links/{campaignLink}/toggle', [OnlineBookingCampaignLinkController::class, 'toggle'])->name('online_booking.campaign_links.toggle');
        Route::delete('online-booking/campaign-links/{campaignLink}', [OnlineBookingCampaignLinkController::class, 'destroy'])->name('online_booking.campaign_links.destroy');
        Route::patch('online-booking', [OnlineBookingSettingsController::class, 'update'])->name('online_booking.update');
        Route::patch('online-booking/draft', [OnlineBookingSettingsController::class, 'saveDraft'])->name('online_booking.draft.update');
        Route::post('online-booking/publish', [OnlineBookingSettingsController::class, 'publish'])->name('online_booking.publish');
        Route::post('online-booking/unpublish', [OnlineBookingSettingsController::class, 'unpublish'])->name('online_booking.unpublish');
        Route::post('online-booking/publications/{publication}/restore', [OnlineBookingSettingsController::class, 'restore'])->name('online_booking.publications.restore');
        Route::post('online-booking/cover', [OnlineBookingSettingsController::class, 'storeCover'])->name('online_booking.cover.store');
        Route::delete('online-booking/cover', [OnlineBookingSettingsController::class, 'destroyCover'])->name('online_booking.cover.destroy');
        Route::post('online-booking/gallery', [OnlineBookingSettingsController::class, 'storeGallery'])->name('online_booking.gallery.store');
        Route::patch('online-booking/gallery/{image}', [OnlineBookingSettingsController::class, 'updateGallery'])->name('online_booking.gallery.update');
        Route::delete('online-booking/gallery/{image}', [OnlineBookingSettingsController::class, 'destroyGallery'])->name('online_booking.gallery.destroy');
        Route::post('online-booking/gallery/reorder', [OnlineBookingSettingsController::class, 'reorderGallery'])->name('online_booking.gallery.reorder');
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
        Route::get('google-calendar/status', [GoogleCalendarController::class, 'status'])->name('google_calendar.status');
        Route::get('google-calendar/connect', [GoogleCalendarController::class, 'connect'])->name('google_calendar.connect');
        Route::delete('google-calendar/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('google_calendar.disconnect');
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
        Route::get('retention/inactive', [CustomerController::class, 'retentionIndex'])->name('retention.inactive');
        Route::get('retention/customers/inactive', [CustomerController::class, 'inactive'])->name('retention.customers.inactive');
        Route::patch('customers/{customer}/communication-preferences', [CustomerController::class, 'updateCommunicationPreference'])->name('customers.communication_preferences.update');
        Route::post('customers/{customer}/retention/mark', [CustomerController::class, 'markAtRisk'])->name('customers.retention.mark');
        Route::post('customers/{customer}/retention/reactivate', [CustomerController::class, 'reactivateRetention'])->name('customers.retention.reactivate');
        Route::get('retention/campaigns', [RetentionCampaignController::class, 'index'])->name('retention.campaigns.index');
        Route::post('retention/campaigns', [RetentionCampaignController::class, 'store'])->name('retention.campaigns.store');
        Route::patch('retention/campaigns/{retention_campaign}/status', [RetentionCampaignController::class, 'status'])->name('retention.campaigns.status');
        Route::post('retention/campaigns/{retention_campaign}/audience', [RetentionCampaignController::class, 'audience'])->name('retention.campaigns.audience');
        Route::post('retention/campaigns/{retention_campaign}/dispatch', [RetentionCampaignController::class, 'dispatch'])->name('retention.campaigns.dispatch');
        Route::post('retention/campaigns/{retention_campaign}/process', [RetentionCampaignController::class, 'process'])->name('retention.campaigns.process');
        Route::post('customers/{customer}/legal-holds', [LegalRetentionController::class, 'store'])->name('legal_holds.store');
        Route::post('legal-holds/{legal_hold}/release', [LegalRetentionController::class, 'release'])->name('legal_holds.release');
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
        Route::post('customer-packages/{customer_package}/usages/{package_usage}/reverse', [CustomerPackageController::class, 'reverseUsage'])
            ->scopeBindings()
            ->name('customer-packages.usages.reverse');
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
        Route::post('customer-subscriptions/{customer_subscription}/consume', [CustomerSubscriptionController::class, 'consume'])->name('customer-subscriptions.consume');
        Route::post('customer-subscriptions/{customer_subscription}/renew', [CustomerSubscriptionController::class, 'renew'])->name('customer-subscriptions.renew');
    });
});

require __DIR__.'/settings.php';
