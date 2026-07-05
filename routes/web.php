<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => view('welcome'));

// Admin auth (no middleware — must be public)
Route::get('/admin/login',  [AdminController::class, 'loginForm'])->name('admin.login');
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminController::class, 'logout'])->name('admin.logout');

// Protected admin routes
Route::middleware(['App\Http\Middleware\AdminOnly'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/',            [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/applicants',  [AdminController::class, 'applicants'])->name('applicants');
        Route::get('/applicants/{user}', [AdminController::class, 'applicantShow'])->name('applicants.show');
        Route::post('/applicants/{user}/verify', [AdminController::class, 'applicantVerify'])->name('applicants.verify');
        Route::get('/requests',    [AdminController::class, 'requests'])->name('requests');
        Route::get('/requests/{serviceRequest}', [AdminController::class, 'requestShow'])->name('requests.show');
        Route::post('/requests/{serviceRequest}/pay-rate', [AdminController::class, 'setPayRate'])->name('requests.pay-rate');
        Route::post('/requests/{serviceRequest}/status',   [AdminController::class, 'updateRequestStatus'])->name('requests.status');

        Route::get('/packages',             [AdminController::class, 'packages'])->name('packages');
        Route::post('/packages/{package}',  [AdminController::class, 'updatePackage'])->name('packages.update');

        Route::get('/pricing',                         [AdminController::class, 'pricingSettings'])->name('pricing');
        Route::post('/pricing/services/{houseService}', [AdminController::class, 'updateService'])->name('pricing.service');
        Route::post('/pricing/apartment/{apartmentType}', [AdminController::class, 'updateApartmentType'])->name('pricing.apartment');
        Route::post('/pricing/plan/{managementPlan}',  [AdminController::class, 'updateManagementPlan'])->name('pricing.plan');
        Route::post('/pricing/extra-cost',             [AdminController::class, 'updateExtraServiceCost'])->name('pricing.extra');
    });
