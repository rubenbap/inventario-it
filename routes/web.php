<?php

use App\Http\Controllers\Web\AccessController;
use App\Http\Controllers\Web\ActiveCompanyController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ImportController;
use App\Http\Controllers\Web\PasswordController;
use App\Http\Controllers\Web\TokenController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/password/esqueci', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/password/esqueci', [PasswordController::class, 'email'])->middleware('throttle:5,10')->name('password.email');
    Route::get('/password/nova/{token}', [PasswordController::class, 'edit'])->name('password.reset');
    Route::post('/password/nova', [PasswordController::class, 'update'])->middleware('throttle:10,10')->name('password.update');
});

Route::middleware(['auth', 'role:admin,leitura'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/empresa-ativa', ActiveCompanyController::class)->name('active-company');

    Route::get('/importar', [ImportController::class, 'index'])->name('import.index');
    Route::get('/exportar/{module}', [ImportController::class, 'export'])->name('export');
    Route::middleware('role:admin')->prefix('importar')->name('import.')->group(function () {
        Route::post('/', [ImportController::class, 'upload'])->name('upload');
        Route::post('/pre-visualizar', [ImportController::class, 'preview'])->name('preview');
        Route::post('/confirmar', [ImportController::class, 'run'])->name('run');
        Route::post('/{id}/anular', [ImportController::class, 'undo'])->whereNumber('id')->name('undo');
        Route::get('/modelo/{module}', [ImportController::class, 'template'])->name('template');
    });

    Route::middleware('role:admin')->prefix('empresas')->name('companies.')->group(function () {
        Route::get('/', [CompanyController::class, 'index'])->name('index');
        Route::get('/nova', [CompanyController::class, 'create'])->name('create');
        Route::post('/', [CompanyController::class, 'store'])->name('store');
        Route::get('/{id}', [CompanyController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [CompanyController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [CompanyController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    Route::middleware('role:admin')->prefix('acessos')->name('access.')->group(function () {
        Route::get('/', [AccessController::class, 'index'])->name('index');
        Route::get('/novo', [AccessController::class, 'create'])->name('create');
        Route::post('/', [AccessController::class, 'store'])->name('store');
        Route::get('/{id}', [AccessController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [AccessController::class, 'update'])->whereNumber('id')->name('update');
        Route::post('/{id}/link-password', [AccessController::class, 'sendLink'])->whereNumber('id')->middleware('throttle:10,10')->name('link');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/tokens', [TokenController::class, 'index'])->name('tokens');
        Route::post('/tokens', [TokenController::class, 'store']);
        Route::delete('/tokens/{id}', [TokenController::class, 'destroy'])->whereNumber('id');
    });
});
