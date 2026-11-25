<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Telegram calls this without a session, so it lives outside the auth middleware.
Route::post('/telegram/webhook/{secret}', TelegramWebhookController::class)->name('telegram.webhook');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Income and Expense share one controller; the type comes from the route defaults.
    foreach (['income', 'expense'] as $type) {
        // Category management sits under each menu, registered before the wildcard routes.
        Route::get("/{$type}/categories", [CategoryController::class, 'index'])->defaults('type', $type)->name("{$type}.categories");
        Route::get("/{$type}/categories/create", [CategoryController::class, 'create'])->defaults('type', $type)->name("{$type}.categories.create");
        Route::post("/{$type}/categories", [CategoryController::class, 'store'])->defaults('type', $type)->name("{$type}.categories.store");
        Route::get("/{$type}/categories/{category}/edit", [CategoryController::class, 'edit'])->defaults('type', $type)->name("{$type}.categories.edit");
        Route::put("/{$type}/categories/{category}", [CategoryController::class, 'update'])->defaults('type', $type)->name("{$type}.categories.update");
        Route::delete("/{$type}/categories/{category}", [CategoryController::class, 'destroy'])->defaults('type', $type)->name("{$type}.categories.destroy");

        Route::get("/{$type}", [TransactionController::class, 'index'])->defaults('type', $type)->name("{$type}.index");
        Route::get("/{$type}/create", [TransactionController::class, 'create'])->defaults('type', $type)->name("{$type}.create");
        Route::post("/{$type}", [TransactionController::class, 'store'])->defaults('type', $type)->name("{$type}.store");
        Route::get("/{$type}/{transaction}/edit", [TransactionController::class, 'edit'])->defaults('type', $type)->name("{$type}.edit");
        Route::put("/{$type}/{transaction}", [TransactionController::class, 'update'])->defaults('type', $type)->name("{$type}.update");
        Route::delete("/{$type}/{transaction}", [TransactionController::class, 'destroy'])->defaults('type', $type)->name("{$type}.destroy");
    }

    Route::get('/budgeting', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('/budgeting', [BudgetController::class, 'store'])->name('budgets.store');
    Route::put('/budgeting/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
    Route::delete('/budgeting/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'exportCsv'])->name('reports.export');
    Route::post('/reports/sync-sheets', [ReportController::class, 'syncToSheets'])->name('reports.sync');

    Route::get('/savings', [SavingsController::class, 'index'])->name('savings.index');
    Route::post('/savings', [SavingsController::class, 'store'])->name('savings.store');
    Route::put('/savings/{goal}', [SavingsController::class, 'update'])->name('savings.update');
    Route::delete('/savings/{goal}', [SavingsController::class, 'destroy'])->name('savings.destroy');

    Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
    Route::post('/portfolio', [PortfolioController::class, 'store'])->name('portfolio.store');
    Route::put('/portfolio/{holding}', [PortfolioController::class, 'update'])->name('portfolio.update');
    Route::delete('/portfolio/{holding}', [PortfolioController::class, 'destroy'])->name('portfolio.destroy');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/telegram', [SettingsController::class, 'updateTelegram'])->name('settings.telegram');
    Route::put('/settings/sheets', [SettingsController::class, 'updateSheets'])->name('settings.sheets');
    Route::post('/settings/telegram/test', [SettingsController::class, 'testTelegram'])->name('settings.telegram.test');
});
