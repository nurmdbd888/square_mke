<?php

use App\Domain\Import\EntityImportRegistry;
use App\Http\Controllers\Accounts\BookController;
use App\Http\Controllers\Accounts\LedgerController;
use App\Http\Controllers\Accounts\PaymentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Documents\StockController;
use App\Http\Controllers\Export\ExportController;
use App\Http\Controllers\Google\GoogleDriveController;
use App\Http\Controllers\Google\GoogleOAuthController;
use App\Http\Controllers\Google\GoogleSheetController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Import\EntityImportController;
use App\Http\Controllers\Master\CustomFieldController;
use App\Http\Controllers\Master\DirectoryController;
use App\Http\Controllers\Master\MastersController;
use App\Http\Controllers\Master\OpeningBalanceController;
use App\Http\Controllers\Master\PriceListController;
use App\Http\Controllers\Master\ProductController;
use App\Http\Controllers\Master\RetailerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\MonthLockController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Stock\ClaimController;
use App\Http\Controllers\Stock\StockComparisonController;
use App\Http\Controllers\Stock\StockNoteController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');

    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'verify'])->name('two-factor.verify');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('/profile/locale/{locale}', [ProfileController::class, 'locale'])->name('profile.locale');

    Route::post('/profile/two-factor', [TwoFactorController::class, 'start'])->name('profile.two-factor.start');
    Route::post('/profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('profile.two-factor.confirm');
    Route::post('/profile/two-factor/recovery-codes', [TwoFactorController::class, 'regenerate'])->name('profile.two-factor.recovery');
    Route::delete('/profile/two-factor', [TwoFactorController::class, 'disable'])->name('profile.two-factor.disable');

    Route::middleware('account.ready')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/health', HealthController::class)->middleware('can:health.view')->name('health');

        Route::middleware('can:products.view')->group(function () {
            Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        });
        Route::middleware('can:products.manage')->group(function () {
            Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::post('/products/bulk', [ProductController::class, 'bulk'])->name('products.bulk');
            Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->withTrashed()->name('products.edit');
            Route::put('/products/{product}', [ProductController::class, 'update'])->withTrashed()->name('products.update');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
            Route::post('/products/{product}/restore', [ProductController::class, 'restore'])->withTrashed()->name('products.restore');
        });

        Route::middleware('can:retailers.view')->group(function () {
            Route::get('/retailers', [RetailerController::class, 'index'])->name('retailers.index');
        });
        Route::middleware('can:retailers.manage')->group(function () {
            Route::get('/retailers/create', [RetailerController::class, 'create'])->name('retailers.create');
            Route::post('/retailers', [RetailerController::class, 'store'])->name('retailers.store');
            Route::post('/retailers/bulk', [RetailerController::class, 'bulk'])->name('retailers.bulk');
            Route::get('/retailers/{retailer}/edit', [RetailerController::class, 'edit'])->withTrashed()->name('retailers.edit');
            Route::put('/retailers/{retailer}', [RetailerController::class, 'update'])->withTrashed()->name('retailers.update');
            Route::delete('/retailers/{retailer}', [RetailerController::class, 'destroy'])->name('retailers.destroy');
            Route::post('/retailers/{retailer}/restore', [RetailerController::class, 'restore'])->withTrashed()->name('retailers.restore');
        });

        $masterKinds = 'suppliers|routes|sales-reps|vehicles';

        Route::middleware('can:masters.view')->group(function () use ($masterKinds) {
            Route::get('/masters', [MastersController::class, 'index'])->name('masters.index');
            Route::get('/masters/price-lists', [PriceListController::class, 'index'])->name('masters.price-lists.index');
            Route::get('/masters/opening-balances', [OpeningBalanceController::class, 'index'])->name('masters.opening-balances.index');
            Route::get('/masters/custom-fields', [CustomFieldController::class, 'index'])->name('masters.custom-fields.index');
            Route::get('/masters/{kind}', [DirectoryController::class, 'index'])->where('kind', $masterKinds)->name('masters.directory.index');
        });

        // Registered before {priceList}, or "create" is treated as an id.
        Route::get('/masters/price-lists/create', [PriceListController::class, 'create'])->middleware('can:masters.manage')->name('masters.price-lists.create');
        Route::get('/masters/price-lists/{priceList}', [PriceListController::class, 'show'])->middleware('can:masters.view')->withTrashed()->name('masters.price-lists.show');

        Route::middleware('can:masters.manage')->group(function () use ($masterKinds) {
            Route::post('/masters/price-lists', [PriceListController::class, 'store'])->name('masters.price-lists.store');
            Route::put('/masters/price-lists/{priceList}', [PriceListController::class, 'update'])->withTrashed()->name('masters.price-lists.update');
            Route::delete('/masters/price-lists/{priceList}', [PriceListController::class, 'destroy'])->name('masters.price-lists.destroy');
            Route::post('/masters/price-lists/{priceList}/restore', [PriceListController::class, 'restore'])->withTrashed()->name('masters.price-lists.restore');
            Route::post('/masters/price-lists/{priceList}/items', [PriceListController::class, 'storeItem'])->name('masters.price-lists.items.store');
            Route::delete('/masters/price-lists/{priceList}/items/{item}', [PriceListController::class, 'destroyItem'])->name('masters.price-lists.items.destroy');

            Route::get('/masters/opening-balances/create', [OpeningBalanceController::class, 'create'])->name('masters.opening-balances.create');
            Route::post('/masters/opening-balances', [OpeningBalanceController::class, 'store'])->name('masters.opening-balances.store');
            Route::get('/masters/opening-balances/{openingBalance}/edit', [OpeningBalanceController::class, 'edit'])->name('masters.opening-balances.edit');
            Route::put('/masters/opening-balances/{openingBalance}', [OpeningBalanceController::class, 'update'])->name('masters.opening-balances.update');
            Route::delete('/masters/opening-balances/{openingBalance}', [OpeningBalanceController::class, 'destroy'])->name('masters.opening-balances.destroy');

            Route::get('/masters/custom-fields/create', [CustomFieldController::class, 'create'])->name('masters.custom-fields.create');
            Route::post('/masters/custom-fields', [CustomFieldController::class, 'store'])->name('masters.custom-fields.store');
            Route::get('/masters/custom-fields/{customField}/edit', [CustomFieldController::class, 'edit'])->name('masters.custom-fields.edit');
            Route::put('/masters/custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('masters.custom-fields.update');
            Route::delete('/masters/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('masters.custom-fields.destroy');

            Route::get('/masters/{kind}/create', [DirectoryController::class, 'create'])->where('kind', $masterKinds)->name('masters.directory.create');
            Route::post('/masters/{kind}', [DirectoryController::class, 'store'])->where('kind', $masterKinds)->name('masters.directory.store');
            Route::get('/masters/{kind}/{id}/edit', [DirectoryController::class, 'edit'])->where('kind', $masterKinds)->whereNumber('id')->name('masters.directory.edit');
            Route::put('/masters/{kind}/{id}', [DirectoryController::class, 'update'])->where('kind', $masterKinds)->whereNumber('id')->name('masters.directory.update');
            Route::delete('/masters/{kind}/{id}', [DirectoryController::class, 'destroy'])->where('kind', $masterKinds)->whereNumber('id')->name('masters.directory.destroy');
            Route::post('/masters/{kind}/{id}/restore', [DirectoryController::class, 'restore'])->where('kind', $masterKinds)->whereNumber('id')->name('masters.directory.restore');
        });

        $importEntities = implode('|', EntityImportRegistry::ENTITIES);
        Route::prefix('imports')->group(function () use ($importEntities) {
            Route::get('/{entity}/template', [EntityImportController::class, 'template'])->where('entity', $importEntities)->name('imports.entity.template');
            Route::get('/{entity}/export', [EntityImportController::class, 'export'])->where('entity', $importEntities)->name('imports.entity.export');
            Route::get('/{entity}', [EntityImportController::class, 'index'])->where('entity', $importEntities)->name('imports.entity.index');
            Route::post('/{entity}', [EntityImportController::class, 'store'])->where('entity', $importEntities)->name('imports.entity.store');
            Route::post('/{entity}/profiles', [EntityImportController::class, 'storeProfile'])->where('entity', $importEntities)->name('imports.entity.profiles.store');
            Route::get('/{entity}/batches/{entityImportBatch}', [EntityImportController::class, 'show'])->where('entity', $importEntities)->name('imports.entity.show');
            Route::post('/{entity}/batches/{entityImportBatch}/confirm', [EntityImportController::class, 'confirm'])->where('entity', $importEntities)->name('imports.entity.confirm');
            Route::post('/{entity}/batches/{entityImportBatch}/undo', [EntityImportController::class, 'undo'])->where('entity', $importEntities)->name('imports.entity.undo');
        });

        Route::get('/purchases', [DocumentController::class, 'index'])->middleware('can:purchases.view')->defaults('type', 'purchase')->name('purchases.index');
        Route::get('/purchases/create', [DocumentController::class, 'create'])->middleware('can:purchases.manage')->defaults('type', 'purchase')->name('purchases.create');
        Route::post('/purchases', [DocumentController::class, 'store'])->middleware('can:purchases.manage')->defaults('type', 'purchase')->name('purchases.store');
        Route::get('/purchases/{document}', [DocumentController::class, 'show'])->middleware('can:purchases.view')->name('purchases.show');
        Route::get('/purchases/{document}/edit', [DocumentController::class, 'edit'])->middleware('can:purchases.manage')->name('purchases.edit');
        Route::put('/purchases/{document}', [DocumentController::class, 'update'])->middleware('can:purchases.manage')->name('purchases.update');
        Route::post('/purchases/{document}/post', [DocumentController::class, 'post'])->middleware('can:purchases.manage')->name('purchases.post');
        Route::delete('/purchases/{document}', [DocumentController::class, 'destroy'])->middleware('can:purchases.manage')->name('purchases.destroy');

        Route::get('/sales', [DocumentController::class, 'index'])->middleware('can:sales.view')->defaults('type', 'sale')->name('sales.index');
        Route::get('/sales/create', [DocumentController::class, 'create'])->middleware('can:sales.manage')->defaults('type', 'sale')->name('sales.create');
        Route::post('/sales', [DocumentController::class, 'store'])->middleware('can:sales.manage')->defaults('type', 'sale')->name('sales.store');
        Route::get('/sales/{document}', [DocumentController::class, 'show'])->middleware('can:sales.view')->name('sales.show');
        Route::get('/sales/{document}/edit', [DocumentController::class, 'edit'])->middleware('can:sales.manage')->name('sales.edit');
        Route::put('/sales/{document}', [DocumentController::class, 'update'])->middleware('can:sales.manage')->name('sales.update');
        Route::post('/sales/{document}/post', [DocumentController::class, 'post'])->middleware('can:sales.manage')->name('sales.post');
        Route::delete('/sales/{document}', [DocumentController::class, 'destroy'])->middleware('can:sales.manage')->name('sales.destroy');

        Route::middleware('can:stock.view')->group(function () {
            Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
            Route::get('/stock/comparison', [StockComparisonController::class, 'index'])->middleware('can:stock.compare')->name('stock.comparison.index');
            Route::get('/stock/comparison/{comparison}', [StockComparisonController::class, 'show'])->middleware('can:stock.compare')->name('stock.comparison.show');
            Route::get('/stock/{product}', [StockController::class, 'show'])->name('stock.show');
        });
        Route::post('/stock/comparison', [StockComparisonController::class, 'store'])->middleware('can:stock.compare')->name('stock.comparison.store');

        Route::get('/returns', [StockNoteController::class, 'index'])->middleware('can:returns.view')->name('returns.index');
        Route::get('/returns/create', [StockNoteController::class, 'create'])->middleware('can:returns.manage')->name('returns.create');
        Route::post('/returns', [StockNoteController::class, 'store'])->middleware('can:returns.manage')->name('returns.store');
        Route::get('/returns/{stockNote}', [StockNoteController::class, 'show'])->middleware('can:returns.view')->name('returns.show');
        Route::get('/returns/{stockNote}/edit', [StockNoteController::class, 'edit'])->middleware('can:returns.manage')->name('returns.edit');
        Route::put('/returns/{stockNote}', [StockNoteController::class, 'update'])->middleware('can:returns.manage')->name('returns.update');
        Route::post('/returns/{stockNote}/post', [StockNoteController::class, 'post'])->middleware('can:returns.manage')->name('returns.post');
        Route::delete('/returns/{stockNote}', [StockNoteController::class, 'destroy'])->middleware('can:returns.manage')->name('returns.destroy');

        Route::get('/claims', [ClaimController::class, 'index'])->middleware('can:claims.view')->name('claims.index');
        Route::get('/claims/create', [ClaimController::class, 'create'])->middleware('can:claims.manage')->name('claims.create');
        Route::post('/claims', [ClaimController::class, 'store'])->middleware('can:claims.manage')->name('claims.store');
        Route::get('/claims/{claim}', [ClaimController::class, 'show'])->middleware('can:claims.view')->name('claims.show');
        Route::get('/claims/{claim}/edit', [ClaimController::class, 'edit'])->middleware('can:claims.manage')->name('claims.edit');
        Route::put('/claims/{claim}', [ClaimController::class, 'update'])->middleware('can:claims.manage')->name('claims.update');

        Route::get('/payments', [PaymentController::class, 'index'])->middleware('can:payments.view')->name('payments.index');
        Route::get('/payments/open-invoices', [PaymentController::class, 'openInvoices'])->middleware('can:payments.manage')->name('payments.open-invoices');
        Route::get('/payments/create', [PaymentController::class, 'create'])->middleware('can:payments.manage')->name('payments.create');
        Route::post('/payments', [PaymentController::class, 'store'])->middleware('can:payments.manage')->name('payments.store');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('can:payments.view')->name('payments.show');
        Route::get('/payments/{payment}/edit', [PaymentController::class, 'edit'])->middleware('can:payments.manage')->name('payments.edit');
        Route::put('/payments/{payment}', [PaymentController::class, 'update'])->middleware('can:payments.manage')->name('payments.update');
        Route::post('/payments/{payment}/post', [PaymentController::class, 'post'])->middleware('can:payments.manage')->name('payments.post');
        Route::post('/payments/{payment}/clear', [PaymentController::class, 'clear'])->middleware('can:payments.manage')->name('payments.clear');
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->middleware('can:payments.manage')->name('payments.destroy');

        Route::middleware('can:accounts.view')->group(function () {
            Route::get('/accounts/receivables', [LedgerController::class, 'receivables'])->name('accounts.receivables');
            Route::get('/accounts/payables', [LedgerController::class, 'payables'])->name('accounts.payables');
            Route::get('/accounts/retailers/{retailer}/statement', [LedgerController::class, 'retailerStatement'])->name('accounts.statement');
            Route::get('/accounts/ageing', [LedgerController::class, 'ageing'])->name('accounts.ageing');
            Route::get('/accounts/cash-book', [BookController::class, 'cash'])->name('accounts.cash-book');
            Route::get('/accounts/bank-book', [BookController::class, 'bank'])->name('accounts.bank-book');
            Route::get('/accounts/day-book', [BookController::class, 'day'])->name('accounts.day-book');
            Route::get('/accounts/closings', [BookController::class, 'closings'])->name('accounts.closings');
        });
        Route::post('/accounts/closings', [BookController::class, 'close'])->middleware('can:accounts.manage')->name('accounts.closings.store');

        Route::middleware('can:reports.view')->group(function () {
            Route::get('/reports/pl', [ReportController::class, 'profitAndLoss'])->name('reports.pl');
            Route::get('/reports/month-locks', [MonthLockController::class, 'index'])->name('reports.month-locks');
        });
        Route::post('/reports/month-locks', [MonthLockController::class, 'store'])->middleware('can:reports.manage')->name('reports.month-locks.store');
        Route::delete('/reports/month-locks/{year}/{month}', [MonthLockController::class, 'destroy'])
            ->middleware('can:reports.manage')
            ->whereNumber(['year', 'month'])
            ->name('reports.month-locks.destroy');

        Route::middleware('can:exports.view')->group(function () {
            Route::get('/downloads', [ExportController::class, 'downloads'])->name('exports.downloads');
            Route::get('/downloads/{appJob}', [ExportController::class, 'download'])->name('exports.download');
        });
        Route::post('/exports/{key}', [ExportController::class, 'store'])->middleware('can:exports.view')->name('exports.store');

        Route::middleware('can:google.manage')->group(function () {
            Route::get('/google/connect', [GoogleOAuthController::class, 'connect'])->name('google.connect');
            Route::get('/google/callback', [GoogleOAuthController::class, 'callback'])->name('google.callback');
            Route::delete('/google/disconnect', [GoogleOAuthController::class, 'disconnect'])->name('google.disconnect');
            Route::post('/google/drive-folder', [GoogleDriveController::class, 'folder'])->name('google.drive-folder');
            Route::post('/google/sheets/{key}', [GoogleSheetController::class, 'store'])->name('google.sheets.store');
        });

        Route::middleware('can:users.view')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
        });

        Route::middleware('can:users.manage')->group(function () {
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->withTrashed()->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->withTrashed()->name('users.update');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('/users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])->name('users.reset-two-factor');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::post('/users/{user}/restore', [UserController::class, 'restore'])->withTrashed()->name('users.restore');
        });

        Route::middleware('can:roles.manage')->group(function () {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::put('/roles', [RoleController::class, 'update'])->name('roles.update');
        });

        Route::get('/settings', [SettingsController::class, 'index'])->middleware('can:settings.view')->name('settings.index');
        Route::middleware('can:settings.manage')->group(function () {
            Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::delete('/settings/{key}/{date}', [SettingsController::class, 'destroyDated'])
                ->where('date', '\d{4}-\d{2}-\d{2}')->name('settings.destroy-dated');
        });

        Route::middleware('can:audit.view')->group(function () {
            Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('/audit/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');
        });
    });
});
