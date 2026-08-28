<?php

use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LawController;
use App\Http\Controllers\LegalUpdateController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MyAppsController;
use App\Http\Controllers\OrganizationContextController;
use App\Http\Controllers\OrganizationSeatController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceInterestController;
use App\Livewire\GratuityCalculator;
use Illuminate\Support\Facades\Route;

Route::get('/', [PlatformController::class, 'index'])->name('platform.home');

Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('platform.marketplace');
Route::post('/marketplace/interest', [ServiceInterestController::class, 'store'])->name('service-interest.store');
Route::get('/marketplace/{key}', [MarketplaceController::class, 'show'])->name('platform.marketplace.show');

Route::get('/marefa', [HomeController::class, 'index'])->name('marefa.home');

Route::get('/laws', [LawController::class, 'index'])->name('laws.index');
Route::get('/laws/{lawEntry}', [LawController::class, 'show'])->name('laws.show');

Route::get('/updates', [LegalUpdateController::class, 'index'])->name('updates.index');

Route::get('/calculators/gratuity', GratuityCalculator::class)->name('calculators.gratuity');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/laws/{lawEntry}/bookmark', [BookmarkController::class, 'toggle'])->name('bookmarks.toggle');
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');

    // Phase 1b — وصول شخصي بتطبيقات مجانية فقط (لا Organization/Seats/Billing).
    Route::post('/marketplace/{key}/activate', [MarketplaceController::class, 'activate'])->name('platform.marketplace.activate');
    Route::post('/marketplace/{key}/cancel', [MarketplaceController::class, 'cancel'])->name('platform.marketplace.cancel');
    Route::get('/my/apps', [MyAppsController::class, 'index'])->name('my-apps.index');

    // Phase 2A — Active Organization Context فقط (لا Organization Subscription/Seats/Access بعد).
    Route::post('/organization-context/personal', [OrganizationContextController::class, 'switchToPersonal'])->name('organization-context.personal');
    Route::post('/organization-context/{organization}', [OrganizationContextController::class, 'switch'])->name('organization-context.switch');

    // Phase 2B — إدارة المقاعد (Owner/Admin فقط، مُنفَّذ داخل Controller نفسه — AD-012).
    Route::get('/organizations/{organization}/seats', [OrganizationSeatController::class, 'index'])->name('organization-seats.index');
    Route::post('/organizations/{organization}/subscriptions/{subscription}/seats/{user}', [OrganizationSeatController::class, 'assign'])->name('organization-seats.assign');
    Route::post('/organizations/{organization}/seats/{seat}/release', [OrganizationSeatController::class, 'release'])->name('organization-seats.release');
});

require __DIR__.'/auth.php';
