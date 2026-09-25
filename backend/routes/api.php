<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProdukPenjualanController;
use App\Http\Controllers\SupplierPenjualanController;

Route::post('/login', [AuthController::class, 'login']);
 
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/upload', [UploadController::class, 'store']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/produk-penjualan', [ProdukPenjualanController::class, 'index']);
    Route::get('/supplier-penjualan', [SupplierPenjualanController::class, 'index']);
});