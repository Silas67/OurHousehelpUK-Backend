<?php

use App\Http\Controllers\Api\ApplicantDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ClientDashboardController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn(Request $request) => $request->user())->middleware('auth:sanctum');
Route::get('/health', fn() => response()->json(['status' => 'ok']));

// Stripe webhook — must be public and receive raw body (no Sanctum, no CSRF)
Route::post('/stripe/webhook', [PaymentController::class, 'webhook']);

// Signed document links — no Sanctum token attached when opened via
// Linking.openURL, so the signature itself is what proves access.
Route::get('/documents/{type}/{user}', [ProfileController::class, 'serveDocument'])
    ->name('documents.show')
    ->middleware('signed');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register/client', [RegisterController::class, 'client'])->middleware('throttle:login');
Route::post('/register/applicant', [RegisterController::class, 'applicant'])->middleware('throttle:login');
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');

// Public — needed before login to populate the booking form and let guests
// browse the marketplace. Staff endpoints reuse the same controller methods
// (they return only vetted, public-safe data and don't read the auth user).
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/pricing-data', [PricingController::class, 'pricingData']);
    Route::post('/cost-estimate', [PricingController::class, 'estimate']);
    Route::get('/public/available-staff', [StaffController::class, 'index']);
    Route::get('/public/staff/{staff}', [StaffController::class, 'publicShow']);
});

// Any authenticated user: profile, notifications, logout
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);
    Route::get('/profile/documents/{type}/link', [ProfileController::class, 'documentLink']);
    Route::post('/profile/documents/{type}', [ProfileController::class, 'uploadDocument']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
});

// Client-only routes
Route::middleware(['auth:sanctum', 'account_type:client'])->group(function () {
    Route::get('/client/dashboard', [ClientDashboardController::class, 'index']);

    Route::get('/client/bookings', [BookingController::class, 'index']);
    Route::post('/client/bookings', [BookingController::class, 'store']);
    Route::get('/client/bookings/{booking}', [BookingController::class, 'show']);
    Route::delete('/client/bookings/{booking}', [BookingController::class, 'destroy']);
    Route::post('/client/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::post('/client/bookings/{booking}/confirm/{applicantId}', [BookingController::class, 'confirm']);
    Route::post('/client/bookings/{booking}/activate', [BookingController::class, 'activate']);
    Route::post('/client/bookings/{booking}/complete', [BookingController::class, 'complete']);
    Route::post('/client/bookings/{booking}/rate', [\App\Http\Controllers\Api\RatingController::class, 'store']);

    Route::get('/available-staff', [StaffController::class, 'index']);
    Route::get('/staff/{staff}', [StaffController::class, 'show']);
    Route::post('/client/bookings/{booking}/invite/{staff}', [BookingController::class, 'invite']);

    Route::post('/payments/checkout', [PaymentController::class, 'checkout']);
    Route::get('/payments/booking/{booking}', [PaymentController::class, 'forBooking']);
});

// Applicant-only routes
Route::middleware(['auth:sanctum', 'account_type:applicant'])->group(function () {
    Route::get('/applicant/dashboard', [ApplicantDashboardController::class, 'index']);
    Route::patch('/profile/availability', [ProfileController::class, 'toggleAvailability']);

    Route::get('/applicant/jobs', [JobController::class, 'index']);
    Route::get('/applicant/jobs/{job}', [JobController::class, 'show']);
    Route::post('/applicant/jobs/{job}/accept', [JobController::class, 'accept']);
    Route::post('/applicant/jobs/{job}/decline', [JobController::class, 'decline']);
    Route::get('/applicant/applications', [JobController::class, 'myApplications']);
    Route::post('/applicant/invitations/{job}/accept', [JobController::class, 'acceptInvite']);
    Route::post('/applicant/invitations/{job}/decline', [JobController::class, 'declineInvite']);
    Route::get('/applicant/confirmed-jobs/{booking}', [JobController::class, 'confirmedJob']);
});
