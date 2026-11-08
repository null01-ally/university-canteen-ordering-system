<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\StaffMenuController;
use App\Http\Controllers\StaffOrderController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureCanteenAccess;
use App\Http\Middleware\RecordWorkspaceActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return match ($request->user()?->user_type) {
        'customer' => to_route('stalls.index'),
        'staff' => to_route('staff.orders.index'),
        'admin' => to_route('admin.users.index'),
        default => to_route('login'),
    };
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordRecoveryController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [PasswordRecoveryController::class, 'reset'])->middleware('throttle:8,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', EnsureCanteenAccess::class.':customer'])->group(function (): void {
    Route::view('/account', 'customer.account')->name('account.edit');
    Route::patch('/account', [AuthController::class, 'updateAccount'])->middleware('throttle:10,1')->name('account.update');
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:1,1')->name('verification.send');
});

Route::middleware(['auth', EnsureCanteenAccess::class.':customer', 'verified'])->group(function (): void {
    Route::get('/stalls', [MenuController::class, 'stalls'])->name('stalls.index');
    Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/service', [CartController::class, 'service'])->name('cart.service');
    Route::put('/cart/items/{item}', [CartController::class, 'setQuantity'])->withTrashed()->name('cart.quantity');
    Route::post('/cart/{item}', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/{line}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{line}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::get('/checkout', [CartController::class, 'review'])->name('checkout.index');
    Route::get('/customer/orders', [CustomerOrderController::class, 'history'])->name('customer.orders.index');
    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('/track-order', [CustomerOrderController::class, 'track'])->name('orders.track');
    Route::get('/track-order/{order:reference_number}', [CustomerOrderController::class, 'show'])->name('orders.track.show');
    Route::post('/orders/{order:reference_number}/again', [CustomerOrderController::class, 'reorder'])->name('orders.again');
    Route::get('/notifications', [CustomerOrderController::class, 'notifications'])->name('notifications.index');
    Route::patch('/notifications/read-all', [CustomerOrderController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/orders', [CartController::class, 'checkout'])->name('orders.store');
    Route::get('/orders/{order:reference_number}', [CustomerOrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order:reference_number}/receipt', [CustomerOrderController::class, 'receipt'])->name('orders.receipt');
    Route::patch('/orders/{order:reference_number}/cancel', [CustomerOrderController::class, 'cancel'])->name('orders.cancel');
    Route::patch('/pickup-notifications/{notification}', [CustomerOrderController::class, 'read'])->name('notifications.read');
});

Route::prefix('staff')->name('staff.')->middleware(['auth', EnsureCanteenAccess::class.':staff', RecordWorkspaceActivity::class])->group(function (): void {
    Route::view('/profile', 'staff.profile')->name('profile.edit');
    Route::get('/summary', [StaffOrderController::class, 'summary'])->name('summary');
    Route::patch('/profile', [AuthController::class, 'updateAccount'])->middleware('throttle:10,1')->name('profile.update');
    Route::patch('/delivery', [StaffOrderController::class, 'delivery'])->name('delivery.update');
    Route::get('/stalls', [StaffOrderController::class, 'settings'])->name('stalls.index');
    Route::get('/settings', [StaffOrderController::class, 'settings'])->name('settings.index');
    Route::get('/inventory', [StaffMenuController::class, 'index'])->name('inventory.index');
    Route::patch('/stalls/{stall}', [StaffOrderController::class, 'updateStall'])->name('stalls.update');
    Route::get('/orders', [StaffOrderController::class, 'index'])->name('orders.index');
    Route::patch('/orders/{order:reference_number}', [StaffOrderController::class, 'update'])->name('orders.update');
    Route::patch('/orders/{order:reference_number}/cancel', [StaffOrderController::class, 'cancel'])->name('orders.cancel');
    Route::patch('/menu/{menu}/availability', [StaffMenuController::class, 'availability'])->name('menu.availability');
    Route::resource('menu', StaffMenuController::class)->except('show');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', EnsureCanteenAccess::class.':admin', RecordWorkspaceActivity::class])->group(function (): void {
    Route::get('/stalls', [AdminController::class, 'stalls'])->name('stalls.index');
    Route::get('/stalls/{stall}/configuration', [StaffOrderController::class, 'configuration'])->name('stalls.configuration');
    Route::patch('/stalls/{stall}/configuration', [StaffOrderController::class, 'updateConfiguration'])->name('stalls.configuration.update');
    Route::post('/stalls', [AdminController::class, 'createStall'])->name('stalls.store');
    Route::patch('/stalls/{stall}', [AdminController::class, 'updateStall'])->name('stalls.update');
    Route::patch('/stalls/{stall}/staff', [AdminController::class, 'reassignStall'])->name('stalls.reassign');
    Route::patch('/stalls/{stall}/delivery', [AdminController::class, 'stallDelivery'])->name('stalls.delivery');
    Route::delete('/stalls/{stall}', [AdminController::class, 'deleteStall'])->name('stalls.destroy');
    Route::get('/users', [AdminController::class, 'users'])->name('users.index');
    Route::get('/users/create', [AdminController::class, 'create'])->name('users.create');
    Route::post('/users', [AdminController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [AdminController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}', [AdminController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/password-reset', [AdminController::class, 'resetPassword'])->middleware('throttle:5,1')->name('users.password-reset');
    Route::get('/reports', [WorkspaceController::class, 'reports'])->name('reports.index');
    Route::get('/audit', [WorkspaceController::class, 'audit'])->name('audit.index');
    Route::get('/sales', [AdminController::class, 'sales'])->name('sales.index');
    Route::get('/sales/export', [AdminController::class, 'export'])->name('sales.export');
    Route::post('/sales', [AdminController::class, 'report'])->name('sales.store');
});
