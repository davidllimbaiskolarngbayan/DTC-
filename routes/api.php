<?php

use App\Http\Controllers\DtcCodesController;
use App\Http\Controllers\DtcRegisterController;
use Illuminate\Support\Facades\Route;

// The "api" group already rate-limits; all WordPress traffic shares one IP, so keep it generous.
Route::middleware('dtc.api')->group(function () {
    Route::post('register', [DtcRegisterController::class, 'store']);
    Route::post('checkExistingRecord', [DtcRegisterController::class, 'checkExistingRecord']);
    Route::post('fetchPlateNumLatestEntryToday', [DtcRegisterController::class, 'fetchPlateNumLatestEntryToday']);
    Route::post('countAllPurchaseDates', [DtcRegisterController::class, 'countAllPurchaseDates']);
    Route::post('checkRecordByDtcCodeOrPlateNum', [DtcRegisterController::class, 'checkRecordsByDtcCodeOrPlateNum']);
    Route::post('claims', [DtcRegisterController::class, 'submitClaim'])->middleware('throttle:10,1');
    Route::post('claims/lookup', [DtcRegisterController::class, 'lookupClaimHistory'])->middleware('throttle:30,1');
    Route::post('claims/respond', [DtcRegisterController::class, 'respondToClaim']);
    Route::delete('dtc/{id}', [DtcRegisterController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('auth:sanctum');

    Route::get('dtc_codes/is_exist/{code}', [DtcCodesController::class, 'isExist'])->where('code', '[A-Za-z0-9\-]{1,64}');
    Route::get('dtc_codes_new/is_exist/{code}', [DtcCodesController::class, 'isExistNew'])->where('code', '[A-Za-z0-9\-]{1,64}');
});
