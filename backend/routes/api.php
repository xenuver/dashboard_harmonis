<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportDataController;
use App\Http\Controllers\ManageUsersController;
use App\Http\Controllers\ProdukPenjualanController;
use App\Http\Controllers\SupplierPenjualanController;
use App\Http\Controllers\UploadController;
use App\Http\Middleware\EnsureUserIsEnabled;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');

Route::middleware(['auth:sanctum', EnsureUserIsEnabled::class])->group(function () {
    Route::get('/whoami', [AuthController::class, 'whoAmI']);
    Route::post('/logout', [AuthController::class, 'logOut']);
    Route::post('/upload', [UploadController::class, 'store'])->middleware('can:upload-reports')->middleware('ReportUploadInspector');
    Route::get('/export-xlsx', [ExportDataController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/produk-penjualan', [ProdukPenjualanController::class, 'index']);
    Route::get('/supplier-penjualan', [SupplierPenjualanController::class, 'index']);

    Route::middleware(['can:manage-users'])->group(function () {
        Route::get('/list-users', [ManageUsersController::class, 'listUsers']);
        Route::post('/add-user', [ManageUsersController::class, 'addUser']);
        Route::post('/update-user', [ManageUsersController::class, 'updateUser']);
        Route::post('/delete-user', [ManageUsersController::class, 'deleteUser']);
    });
});

Route::fallback(function () {
    return response()->json([
        'message' => 'Not Found',
    ], 404);
});
