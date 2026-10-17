<?php

namespace App\Providers;

use App\Models\Claim;
use App\Models\CustomField;
use App\Models\DailyClosing;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\ExpenseCategory;
use App\Models\ImportMappingProfile;
use App\Models\MasterImportBatch;
use App\Models\MasterImportRow;
use App\Models\MonthLock;
use App\Models\OpeningBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\ProductBatch;
use App\Models\Retailer;
use App\Models\SalesRep;
use App\Models\SalesRoute;
use App\Models\SchemeLine;
use App\Models\StockComparison;
use App\Models\StockComparisonLine;
use App\Models\StockLedgerEntry;
use App\Models\StockNote;
use App\Models\StockNoteLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Settings\BusinessSettings;
use App\Settings\CoreSettings;
use App\Settings\SettingsRegistry;
use App\Settings\SettingsStore;
use App\Support\Access\PermissionRegistry;
use App\Support\Audit\AuditLogger;
use App\Support\Maintenance\OwnerAccess;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRegistry::class, function () {
            $registry = new SettingsRegistry;
            CoreSettings::register($registry);
            BusinessSettings::register($registry);

            return $registry;
        });

        $this->app->scoped(SettingsStore::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->scoped(OwnerAccess::class);
    }

    public function boot(): void
    {
        // Older shared-host MySQL/MariaDB builds cap utf8mb4 index keys at 767 bytes.
        Schema::defaultStringLength(191);

        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Relation::morphMap([
            'user' => User::class,
            'role' => Role::class,
            'product' => Product::class,
            'product_alias' => ProductAlias::class,
            'supplier' => Supplier::class,
            'sales_route' => SalesRoute::class,
            'sales_rep' => SalesRep::class,
            'vehicle' => Vehicle::class,
            'price_list' => PriceList::class,
            'price_list_item' => PriceListItem::class,
            'retailer' => Retailer::class,
            'opening_balance' => OpeningBalance::class,
            'custom_field' => CustomField::class,
            'document' => Document::class,
            'document_line' => DocumentLine::class,
            'stock_ledger_entry' => StockLedgerEntry::class,
            'product_batch' => ProductBatch::class,
            'stock_note' => StockNote::class,
            'stock_note_line' => StockNoteLine::class,
            'claim' => Claim::class,
            'scheme_line' => SchemeLine::class,
            'stock_comparison' => StockComparison::class,
            'stock_comparison_line' => StockComparisonLine::class,
            'payment' => Payment::class,
            'payment_allocation' => PaymentAllocation::class,
            'daily_closing' => DailyClosing::class,
            'expense_category' => ExpenseCategory::class,
            'month_lock' => MonthLock::class,
            'import_mapping_profile' => ImportMappingProfile::class,
            'master_import_batch' => MasterImportBatch::class,
            'master_import_row' => MasterImportRow::class,
        ]);

        // The owner can never be locked out by the permission matrix.
        Gate::before(fn ($user) => $user instanceof User && $user->isOwner() ? true : null);

        Password::defaults(fn () => Password::min(10)->max(128));

        Paginator::defaultView('components.pagination');

        $this->auditAuthEvents();
    }

    private function auditAuthEvents(): void
    {
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
                AuditLogger::log('auth.login', $event->user, actor: $event->user);
            }
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                AuditLogger::log('auth.logout', $event->user, actor: $event->user);
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            AuditLogger::log('auth.failed', $event->user instanceof User ? $event->user : null, null, [
                'login' => mb_substr((string) ($event->credentials['login'] ?? ''), 0, 100),
            ], actor: null, subjectType: 'user');
        });

        Event::listen(Lockout::class, function (Lockout $event) {
            AuditLogger::log('auth.lockout', null, null, [
                'login' => mb_substr((string) $event->request->input('login', ''), 0, 100),
            ], subjectType: 'user');
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event) {
            if ($event->user instanceof User) {
                AuditLogger::log('auth.password_reset', $event->user, actor: $event->user);
            }
        });
    }
}
