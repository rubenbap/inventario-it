<?php

use App\Http\Controllers\Api\AccessoriesController;
use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\CompaniesController;
use App\Http\Controllers\Api\DepartmentsController;
use App\Http\Controllers\Api\FieldsController;
use App\Http\Controllers\Api\FieldsetsController;
use App\Http\Controllers\Api\HardwareController;
use App\Http\Controllers\Api\LocationsController;
use App\Http\Controllers\Api\ManufacturersController;
use App\Http\Controllers\Api\ModelsController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\StatuslabelsController;
use App\Http\Controllers\Api\SuppliersController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

$writers = 'role:admin';
$admins = 'role:admin';

$crud = function (string $uri, string $controller, string $writeRole) use ($admins) {
    Route::get($uri, [$controller, 'index']);
    Route::get("$uri/{id}", [$controller, 'show'])->whereNumber('id');
    Route::post($uri, [$controller, 'store'])->middleware($writeRole);
    Route::match(['put', 'patch'], "$uri/{id}", [$controller, 'update'])->whereNumber('id')->middleware($writeRole);
    Route::delete("$uri/{id}", [$controller, 'destroy'])->whereNumber('id')->middleware($admins);
};

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api', 'role:admin,leitura'])->group(function () use ($crud, $writers, $admins) {
    Route::get('hardware/bytag/{tag}', [HardwareController::class, 'byTag']);
    Route::get('hardware/byserial/{serial}', [HardwareController::class, 'bySerial']);
    Route::post('hardware/{id}/checkout', [HardwareController::class, 'checkout'])->whereNumber('id')->middleware($writers);
    Route::post('hardware/{id}/checkin', [HardwareController::class, 'checkin'])->whereNumber('id')->middleware($writers);
    Route::get('hardware/{id}/pin', [HardwareController::class, 'pin'])->whereNumber('id')->middleware($writers);
    Route::post('hardware/{id}/restore', [HardwareController::class, 'restore'])->whereNumber('id')->middleware($writers);
    $crud('hardware', HardwareController::class, $writers);

    Route::get('accessories/{id}/checkedout', [AccessoriesController::class, 'checkedout'])->whereNumber('id');
    Route::post('accessories/{id}/checkout', [AccessoriesController::class, 'checkout'])->whereNumber('id')->middleware($writers);
    Route::post('accessories/{id}/checkin', [AccessoriesController::class, 'checkin'])->whereNumber('id')->middleware($writers);
    $crud('accessories', AccessoriesController::class, $writers);

    Route::get('users/me', [UsersController::class, 'me']);
    Route::get('users/{id}/assets', [UsersController::class, 'assets'])->whereNumber('id');
    Route::get('users/{id}/accessories', [UsersController::class, 'accessories'])->whereNumber('id');
    $crud('users', UsersController::class, $writers);

    $crud('companies', CompaniesController::class, $admins);
    $crud('locations', LocationsController::class, $writers);
    $crud('models', ModelsController::class, $writers);
    $crud('manufacturers', ManufacturersController::class, $writers);
    $crud('categories', CategoriesController::class, $writers);
    $crud('statuslabels', StatuslabelsController::class, $admins);
    $crud('suppliers', SuppliersController::class, $writers);
    $crud('departments', DepartmentsController::class, $writers);

    Route::post('fields/{id}/associate', [FieldsController::class, 'associate'])->whereNumber('id')->middleware($admins);
    Route::post('fields/{id}/disassociate', [FieldsController::class, 'disassociate'])->whereNumber('id')->middleware($admins);
    $crud('fields', FieldsController::class, $admins);
    Route::get('fieldsets/{id}/fields', [FieldsetsController::class, 'fields'])->whereNumber('id');
    $crud('fieldsets', FieldsetsController::class, $admins);

    Route::get('reports/activity', [ReportsController::class, 'activity']);
    Route::get('reports/summary', [ReportsController::class, 'summary']);
});
