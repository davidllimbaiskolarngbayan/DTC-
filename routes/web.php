<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\ClaimNotificationSettingsController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\LiveEdmController;
use App\Http\Controllers\DtcCodesController;
use App\Http\Controllers\DtcController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'store'])->middleware('throttle:5,1')->name('password.store');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Public pages reached from registration emails / WordPress; the id is encrypted.
Route::get('register/thankyou/{encUserId}', [LiveEdmController::class, 'dtcThankYou'])->name('dtc.thankyou');
Route::get('unsubscribe/{encUserId}', [LiveEdmController::class, 'edit'])->name('dtc.unsubscribe');
Route::put('unsubscribe/{encUserId}', [LiveEdmController::class, 'update'])->middleware('throttle:10,1')->name('dtc.unsubscribe.update');
Route::get('unsubscribed/thankyou', [LiveEdmController::class, 'thankyou'])->name('dtc.unsubscribed');

Route::middleware('auth')->group(function () {
    Route::get('/', [DtcController::class, 'index'])->name('dashboard');

    Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::post('password', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.update');

    Route::get('profile', [UserManagementController::class, 'ownProfile'])->name('profile.show');
    Route::get('profile/edit', [UserManagementController::class, 'editOwnProfile'])->name('profile.edit');
    Route::put('profile', [UserManagementController::class, 'updateOwnProfile'])->name('profile.update');

    Route::prefix('dtc')->group(function () {
        Route::get('/', [DtcController::class, 'lists'])->name('dtc.lists');
        Route::get('{dtc}/claims/create', [ClaimController::class, 'create'])->name('claims.create');
        Route::post('{dtc}/claims', [ClaimController::class, 'store'])->name('claims.store');
        Route::get('{dtc}/promo-invoice', [DtcController::class, 'downloadPromoInvoice'])->name('dtc.promo-invoice');
        Route::get('{dtc}', [DtcController::class, 'show'])->name('dtc.show');
    });
    Route::post('dtc/export/user', [DtcController::class, 'downloadDtc'])->name('dtc.export');

    Route::get('claims/reports', [ClaimController::class, 'report'])->name('claims.report');
    Route::get('claims', [ClaimController::class, 'index'])->name('claims.index');
    Route::get('claims/export', [ClaimController::class, 'export'])->middleware('role:admin')->name('claims.export');
    Route::get('claims/{claim}', [ClaimController::class, 'show'])->name('claims.show');
    Route::post('claims/{claim}/alerts/open', [ClaimController::class, 'openAlert'])->name('claims.alerts.open');
    Route::post('claims/{claim}/assignment', [ClaimController::class, 'assign'])->name('claims.assignment');
    Route::put('claims/{claim}/status', [ClaimController::class, 'updateStatus'])->name('claims.status');
    Route::post('claims/{claim}/notification/retry', [ClaimController::class, 'retryNotification'])->name('claims.notification.retry');
    Route::post('claims/{claim}/staff-notification/retry', [ClaimController::class, 'retryStaffNotification'])->name('claims.staff-notification.retry');
    Route::post('claims/{claim}/email-deliveries/{delivery}/retry', [ClaimController::class, 'retryStatusNotification'])->name('claims.email-deliveries.retry');
    Route::get('claims/{claim}/attachments/{attachment}/preview', [ClaimController::class, 'previewAttachment'])->name('claims.attachments.preview');
    Route::get('claims/{claim}/attachments/{attachment}', [ClaimController::class, 'downloadAttachment'])->name('claims.attachments.download');
    Route::post('claims/{claim}/notes', [ClaimController::class, 'addNote'])->name('claims.notes');
    Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');

    Route::middleware('role:admin')->group(function () {
        Route::get('settings/claim-notifications', [ClaimNotificationSettingsController::class, 'index'])->name('settings.claim-notifications');
        Route::put('settings/claim-notifications', [ClaimNotificationSettingsController::class, 'update'])->name('settings.claim-notifications.update');

        Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        Route::get('users/{user}', [UserManagementController::class, 'show'])->name('users.show');

        Route::get('dtc/{dtc}/edit', [DtcController::class, 'edit'])->name('dtc.edit');
        Route::put('dtc/{dtc}', [DtcController::class, 'update'])->name('dtc.update');
        Route::delete('dtc/{dtc}', [DtcController::class, 'destroy'])->name('dtc.destroy');

        Route::get('dtc_codes/download', [DtcCodesController::class, 'downloadCodes'])->name('dtc_codes.download');
        Route::resource('dtc_codes', DtcCodesController::class)->only(['index', 'create', 'store']);
    });
});
