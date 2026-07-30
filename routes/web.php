<?php

use App\Policies\SalesPolicy;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\GudangController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SuppliersController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboardPage'])->name('dashboard.page');
    Route::get('/products', [AdminController::class, 'productsPage'])->name('product.page');
    Route::get('/suppliers', [AdminController::class, 'suppliersPage'])->name('supplier.page');
    Route::get('/users', [AdminController::class, 'usersPage'])->name('user.page');
    Route::get('/laporan', [AdminController::class, 'laporanPage'])->name('laporan.page');

    Route::resource('/product', ProductController::class);
    Route::resource('/supplier', SuppliersController::class);
    Route::resource('/user', UserController::class);
});

Route::middleware(['auth', 'role:kasir'])
    ->prefix('kasir')
    ->name('kasir.')
    ->group(function () {
        Route::get(
            '/transaksi',
            [KasirController::class, 'transaksi']
        )->name('transaksi.page');

        Route::get(
            '/drafts',
            [KasirController::class, 'drafts']
        )->name('drafts.index');

        Route::post(
            '/drafts',
            [KasirController::class, 'storeDraft']
        )->name('drafts.store');

        Route::get(
            '/drafts/{sale}',
            [KasirController::class, 'showDraft']
        )->name('drafts.show');

        Route::put(
            '/drafts/{sale}',
            [KasirController::class, 'updateDraft']
        )->name('drafts.update');

        Route::delete(
            '/drafts/{sale}',
            [KasirController::class, 'destroyDraft']
        )->name('drafts.destroy');

        Route::post(
            '/drafts/{sale}/confirm',
            [KasirController::class, 'confirmDraft']
        )->name('drafts.confirm');

        Route::get(
            '/laporan-transaksi',
            [KasirController::class, 'LaporanTransaksi']
        )->name('laporan-transaksi.page');
    });


Route::middleware((['auth', 'role:gudang']))->prefix('gudang')->name('gudang.')->group(function () {
    Route::get('/purchase', [GudangController::class, 'purchasePage'])->name('purchase.page');
    Route::post('/purchase', [GudangController::class, 'prosesPurchase'])->name('purchase.proses');
    Route::get('/laporan-purchase', [GudangController::class, 'laporanPage'])->name('laporan-purchase.page');
});




Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::get('/logout', [LoginController::class, 'logout'])->name('logout');


Route::get('/testing', function () {
    return view('testing');
})->name('testing');


Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])
    ->name('password.request');

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->middleware('throttle:5,1')
    ->name('password.email');

Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])
    ->name('password.reset');

Route::post('/password/reset', [ResetPasswordController::class, 'reset'])
    ->name('password.update');
