<?php

use App\Http\Controllers\BankruptcyTech\BankruptcyCaseController;
use App\Http\Controllers\BankruptcyTech\CaseAssetController;
use App\Http\Controllers\BankruptcyTech\CaseChecklistController;
use App\Http\Controllers\BankruptcyTech\CaseClientController;
use App\Http\Controllers\BankruptcyTech\CaseCreditorController;
use App\Http\Controllers\BankruptcyTech\CaseDocumentController;
use App\Http\Controllers\BankruptcyTech\CaseDraftController;
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
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Articles\ArticleAuthorController;
use App\Http\Controllers\Articles\ArticleDashboardController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\Community\ServiceListingDashboardController;
use App\Http\Controllers\CommunityController;
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
use App\Http\Controllers\Auth\CoreSsoController;
use App\Http\Controllers\ServiceInterestController;
use App\Http\Controllers\TechPortalCartController;
use App\Http\Controllers\TechPortalController;
use App\Http\Controllers\Training\TrainingOpportunityDashboardController;
use App\Http\Controllers\TrainingController;
use App\Livewire\GratuityCalculator;
use Illuminate\Support\Facades\Route;

Route::get('/', [PlatformController::class, 'index'])->name('platform.home');

Route::get('/auth/core/redirect', [CoreSsoController::class, 'redirect'])
    ->middleware('throttle:20,1')
    ->name('auth.core.redirect');
Route::get('/auth/core/callback', [CoreSsoController::class, 'callback'])
    ->middleware('throttle:20,1')
    ->name('auth.core.callback');

Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('platform.marketplace');
Route::post('/marketplace/interest', [ServiceInterestController::class, 'store'])->name('service-interest.store');
Route::get('/marketplace/{key}', [MarketplaceController::class, 'show'])->name('platform.marketplace.show');

Route::get('/marefa', [HomeController::class, 'index'])->name('marefa.home');

Route::get('/laws', [LawController::class, 'index'])->name('laws.index');
Route::get('/laws/{lawEntry}', [LawController::class, 'show'])->name('laws.show');

Route::get('/updates', [LegalUpdateController::class, 'index'])->name('updates.index');

// بوابة المقالات — عرض عام بالكامل (لا Auth، مطابق لـlaws/updates)، مطابقًا
// لنمط entry_route المجاني (marefa.home): "التفعيل" شكلي، المحتوى عام أصلًا.
Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');

// مجتمع الخدمات — دليل عام (محامون/مختصون يعرضون خدماتهم للعامة)، تصفح
// بلا Auth مطابق تمامًا لبوابة المقالات. إرسال استفسار مسموح لأي زائر ضيف
// أيضًا (نفس منطق service-interest.store العام). النشر نفسه (لوحة
// "إعلاناتي") خلف Auth+marketplace.entitled بمجموعة apps/community أدناه.
Route::get('/community', [CommunityController::class, 'index'])->name('community.index');
Route::get('/community/{listing}', [CommunityController::class, 'show'])->name('community.show');
Route::post('/community/{listing}/inquiry', [CommunityController::class, 'storeInquiry'])->name('community.inquiry');

// بوابة التقنية — مزوّد واحد (المنصة نفسها)، كتالوج ثابت يديره فريق المنصة
// عبر Filament حصرًا (لا نشر ذاتي). عامة بالكامل بلا Auth — السلة Session
// فقط حتى لحظة الإرسال (TechServiceRequestService، BR-013).
Route::get('/tech-portal', [TechPortalController::class, 'index'])->name('tech-portal.index');
Route::get('/tech-portal/cart', [TechPortalCartController::class, 'show'])->name('tech-portal.cart.show');
// "submit" مسار حرفي — يجب تسجيله قبل {techService} المتغيّر، وإلا يبتلعه
// الأخير (يطابق أي سلسلة كـmodel-binding id، فيفشل 404 على "submit" حرفيًا).
Route::post('/tech-portal/cart/submit', [TechPortalCartController::class, 'submit'])->name('tech-portal.cart.submit');
Route::post('/tech-portal/cart/{techService}', [TechPortalCartController::class, 'add'])->name('tech-portal.cart.add');
Route::delete('/tech-portal/cart/{techService}', [TechPortalCartController::class, 'remove'])->name('tech-portal.cart.remove');

// بوابة التدريب التعاوني — مكاتب/شركات تنشر فرصًا، الطلاب يتصفحون بلا Auth
// (مطابق لمجتمع الخدمات)، لكن التقديم وحده يتطلب تسجيل دخول (auth على
// مستوى الـRoute تحديدًا لهذا الفعل فقط — الفرق الجوهري عن استفسارات
// مجتمع الخدمات العامة للضيوف).
Route::get('/internships', [TrainingController::class, 'index'])->name('internships.index');
Route::get('/internships/{opportunity}', [TrainingController::class, 'show'])->name('internships.show');
Route::post('/internships/{opportunity}/apply', [TrainingController::class, 'apply'])
    ->middleware('auth')
    ->name('internships.apply');

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
    // Interest ≠ Subscription — records interest on Core identity API only.
    Route::post('/marketplace/{key}/interest', [MarketplaceController::class, 'interest'])
        ->middleware('throttle:10,1')
        ->name('platform.marketplace.interest');
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

            // محرك مسودة القضية الذكي — عنصر كتالوج مستقل (ai-case-draft)،
            // طبقة Entitlement إضافية فوق bankruptcy-tech نفسها. الأهلية
            // للقضية بعينها تبقى BankruptcyCasePolicy داخل CaseDraftService
            // (فصل Entitlement/Authorization المعتاد، AD-005).
            Route::middleware('marketplace.entitled:ai-case-draft')->group(function () {
                Route::post('/cases/{case}/ai-draft', [CaseDraftController::class, 'store'])->name('cases.ai-draft.store');
            });
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

    // بوابة المقالات — لوحة المؤلف فقط (القراءة العامة بمسار /articles خارج
    // هذي المجموعة). "التفعيل" شكلي زي marefa — لكن دخول لوحة المؤلف نفسها
    // يتطلب marketplace.entitled:articles مطابقًا لبقية التطبيقات.
    Route::middleware('marketplace.entitled:articles')
        ->prefix('apps/articles')
        ->name('articles.dashboard.')
        ->group(function () {
            Route::get('/', [ArticleDashboardController::class, 'index'])->name('index');
            Route::get('/become-author', [ArticleAuthorController::class, 'create'])->name('author.create');
            Route::post('/become-author', [ArticleAuthorController::class, 'store'])->name('author.store');
            Route::get('/create', [ArticleDashboardController::class, 'create'])->name('create');
            Route::post('/', [ArticleDashboardController::class, 'store'])->name('store');
            Route::get('/{article}/edit', [ArticleDashboardController::class, 'edit'])->name('edit');
            Route::patch('/{article}', [ArticleDashboardController::class, 'update'])->name('update');
            Route::post('/{article}/submit', [ArticleDashboardController::class, 'submit'])->name('submit');
        });

    // مجتمع الخدمات — لوحة "إعلاناتي" فقط (التصفح العام بمسار /community
    // خارج هذي المجموعة، مطابق تمامًا لبوابة المقالات). "التفعيل" شكلي زي
    // marefa/articles — لكن دخول لوحة النشر نفسها يتطلب marketplace.entitled:community.
    Route::middleware('marketplace.entitled:community')
        ->prefix('apps/community')
        ->name('community.dashboard.')
        ->group(function () {
            Route::get('/', [ServiceListingDashboardController::class, 'index'])->name('index');
            Route::get('/create', [ServiceListingDashboardController::class, 'create'])->name('create');
            Route::post('/', [ServiceListingDashboardController::class, 'store'])->name('store');
            Route::get('/{listing}', [ServiceListingDashboardController::class, 'show'])->name('show');
            Route::post('/{listing}/close', [ServiceListingDashboardController::class, 'close'])->name('close');
        });

    // بوابة التدريب التعاوني — لوحة "فرصي" فقط (التصفح العام بمسار
    // /internships خارج هذي المجموعة، مطابق تمامًا لمجتمع الخدمات).
    Route::middleware('marketplace.entitled:internships')
        ->prefix('apps/internships')
        ->name('internships.dashboard.')
        ->group(function () {
            Route::get('/', [TrainingOpportunityDashboardController::class, 'index'])->name('index');
            Route::get('/create', [TrainingOpportunityDashboardController::class, 'create'])->name('create');
            Route::post('/', [TrainingOpportunityDashboardController::class, 'store'])->name('store');
            Route::get('/{opportunity}', [TrainingOpportunityDashboardController::class, 'show'])->name('show');
            Route::post('/{opportunity}/close', [TrainingOpportunityDashboardController::class, 'close'])->name('close');
            Route::get(
                '/{opportunity}/applications/{application}/documents/{document}/download',
                [TrainingOpportunityDashboardController::class, 'downloadDocument']
            )->name('applications.documents.download');
        });
});

require __DIR__.'/auth.php';
