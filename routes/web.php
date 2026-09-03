<?php

use App\Http\Controllers\BankruptcyTech\BankruptcyCaseController;
use App\Http\Controllers\BankruptcyTech\CaseAssetController;
use App\Http\Controllers\BankruptcyTech\CaseChecklistController;
use App\Http\Controllers\BankruptcyTech\CaseClientController;
use App\Http\Controllers\BankruptcyTech\CaseCreditorController;
use App\Http\Controllers\BankruptcyTech\CaseDocumentController;
use App\Http\Controllers\BankruptcyTech\CaseEmployeeController;
use App\Http\Controllers\BankruptcyTech\CaseHearingController;
use App\Http\Controllers\BankruptcyTech\CaseNoteController;
use App\Http\Controllers\BankruptcyTech\CasePartyController;
use App\Http\Controllers\BankruptcyTech\CaseProcedureController;
use App\Http\Controllers\BankruptcyTech\CaseProfileController;
use App\Http\Controllers\BankruptcyTech\CaseSignatureController;
use App\Http\Controllers\BankruptcyTech\CaseTimelineController;
use App\Http\Controllers\BankruptcyTech\CaseWizardController;
use App\Http\Controllers\BankruptcyTech\ClientPortalController;
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

    // Final Execution Sprint — إفلاس تك، أول تطبيق Marketplace حقيقي غير مرفا.
    // Entitlement (marketplace.entitled) على المجموعة كاملة — Authorization
    // لكل قضية بعينها عبر BankruptcyCasePolicy داخل الـService (فصل صريح).
    Route::middleware('marketplace.entitled:bankruptcy-tech')
        ->prefix('apps/bankruptcy-tech')
        ->name('bankruptcy-tech.')
        ->group(function () {
            Route::get('/', [BankruptcyCaseController::class, 'index'])->name('cases.index');
            Route::get('/cases/create', [BankruptcyCaseController::class, 'create'])->name('cases.create');
            Route::post('/cases', [BankruptcyCaseController::class, 'store'])->name('cases.store');
            Route::get('/cases/{case}', [BankruptcyCaseController::class, 'show'])->name('cases.show');
            Route::patch('/cases/{case}/status', [BankruptcyCaseController::class, 'updateStatus'])->name('cases.status.update');
            Route::delete('/cases/{case}', [BankruptcyCaseController::class, 'destroy'])->name('cases.destroy');

            Route::post('/cases/{case}/parties', [CasePartyController::class, 'store'])->name('cases.parties.store');

            Route::post('/cases/{case}/procedures', [CaseProcedureController::class, 'store'])->name('cases.procedures.store');
            Route::patch('/cases/{case}/procedures/{procedure}/status', [CaseProcedureController::class, 'updateStatus'])->name('cases.procedures.status.update');

            Route::post('/cases/{case}/notes', [CaseNoteController::class, 'store'])->name('cases.notes.store');

            Route::post('/cases/{case}/documents', [CaseDocumentController::class, 'store'])->name('cases.documents.store');
            Route::get('/cases/{case}/documents/{document}/download', [CaseDocumentController::class, 'download'])->name('cases.documents.download');
            Route::delete('/cases/{case}/documents/{document}', [CaseDocumentController::class, 'destroy'])->name('cases.documents.destroy');

            // المرحلة 1 — النموذج القانوني الكامل (منقول من hw-eflas).
            Route::post('/cases/{case}/creditors', [CaseCreditorController::class, 'store'])->name('cases.creditors.store');
            Route::post('/cases/{case}/assets', [CaseAssetController::class, 'store'])->name('cases.assets.store');
            Route::post('/cases/{case}/employees', [CaseEmployeeController::class, 'store'])->name('cases.employees.store');
            Route::post('/cases/{case}/hearings', [CaseHearingController::class, 'store'])->name('cases.hearings.store');
            Route::patch('/cases/{case}/timeline/{event}/toggle', [CaseTimelineController::class, 'toggle'])->name('cases.timeline.toggle');
            Route::patch('/cases/{case}/profile', [CaseProfileController::class, 'update'])->name('cases.profile.update');
            Route::patch('/cases/{case}/wizard', [CaseWizardController::class, 'update'])->name('cases.wizard.update');
            Route::patch('/cases/{case}/checklists', [CaseChecklistController::class, 'update'])->name('cases.checklists.update');

            // المرحلة 3 — حفظ توقيع (Canvas حقيقي، لا Nafath وهمي).
            Route::patch('/cases/{case}/signature', [CaseSignatureController::class, 'update'])->name('cases.signature.update');

            // المرحلة 2 — إدارة وصول العميل (جانب المحامي فقط).
            Route::post('/cases/{case}/client', [CaseClientController::class, 'store'])->name('cases.client.store');
            Route::post('/cases/{case}/client/revoke', [CaseClientController::class, 'revoke'])->name('cases.client.revoke');
            Route::post('/cases/{case}/client/restore', [CaseClientController::class, 'restore'])->name('cases.client.restore');
        });

    // المرحلة 2 — بوابة العميل الخارجية (المدين). عمدًا خارج
    // marketplace.entitled:bankruptcy-tech — العميل ليس مشترك Marketplace،
    // هو ضيف على قضية واحدة فقط (Authorization محصور بـ viewAsClient/
    // contributeAsClient داخل ClientPortalController نفسه).
    Route::prefix('client-portal')->name('client-portal.')->group(function () {
        Route::get('/cases/{case}', [ClientPortalController::class, 'show'])->name('cases.show');
        Route::post('/cases/{case}/documents', [ClientPortalController::class, 'storeDocument'])->name('cases.documents.store');
        Route::get('/cases/{case}/documents/{document}/download', [ClientPortalController::class, 'downloadDocument'])->name('cases.documents.download');
    });
});

require __DIR__.'/auth.php';
