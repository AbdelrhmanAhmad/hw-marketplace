<?php

namespace Tests\Feature\BankruptcyTech;

use App\Models\BankruptcyCase;
use App\Models\CaseDocument;
use App\Models\User;
use App\Support\BankruptcyRecommendationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * نسخ حرفي لحالات recommendation.test.ts بـhw-eflas — بما فيها حدود الحافة
 * بالضبط (>= مقابل >، < مقابل <=). Feature-level (لا Unit) لأن total_debts/
 * total_assets Accessors تُحسَب حيًا من قاعدة البيانات (قرار #3 بخطة المرحلة 1).
 */
class BankruptcyRecommendationEngineTest extends TestCase
{
    use RefreshDatabase;

    private BankruptcyRecommendationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(BankruptcyRecommendationEngine::class);
    }

    private function baseCase(array $overrides = []): BankruptcyCase
    {
        $user = User::factory()->create();

        return BankruptcyCase::create(array_merge([
            'created_by_user_id' => $user->id,
            'title' => 'قضية اختبار',
            'status' => 'draft',
            'is_establishment' => 'company',
            'is_active' => 'no',
            'has_assets' => 'yes',
            'assets_cover_expenses' => 'no',
            'insolvency_status' => 'actual',
            'financial_statements_available' => 'no',
            'financial_transactions_available' => 'no',
            'creditors_notified' => 'no',
            'operated_twelve_months' => 'yes',
            'previous_settlement' => 'no',
            'attorney_name' => 'محامي الاختبار',
            'cr_number' => '1010101010',
        ], $overrides));
    }

    private function addDebt(BankruptcyCase $case, float $amount): void
    {
        $case->creditors()->create([
            'name' => 'دائن', 'amount' => $amount, 'priority' => 'p3_unsecured',
            'type' => 'تجاري', 'date' => now()->toDateString(), 'added_by_user_id' => $case->created_by_user_id,
        ]);
    }

    private function addAssetValue(BankruptcyCase $case, float $value): void
    {
        $case->assets()->create([
            'name' => 'أصل', 'value' => $value, 'location' => 'الرياض', 'added_by_user_id' => $case->created_by_user_id,
        ]);
    }

    // --- تسوية وقائية / يحتاج مراجعة ---

    public function test_upcoming_insolvency_with_conditions_met_is_preventive(): void
    {
        $case = $this->baseCase(['insolvency_status' => 'upcoming', 'operated_twelve_months' => 'yes', 'previous_settlement' => 'no']);

        $this->assertSame('preventive', $this->engine->recommend($case)->code);
    }

    public function test_upcoming_insolvency_without_twelve_months_needs_review(): void
    {
        $case = $this->baseCase(['insolvency_status' => 'upcoming', 'operated_twelve_months' => 'no', 'previous_settlement' => 'no']);

        $this->assertSame('needs_review', $this->engine->recommend($case)->code);
    }

    public function test_upcoming_insolvency_never_falls_through_to_liquidation(): void
    {
        $case = $this->baseCase(['insolvency_status' => 'upcoming', 'operated_twelve_months' => 'no', 'has_assets' => 'no']);

        $this->assertSame('needs_review', $this->engine->recommend($case)->code);
    }

    // --- تصفية إدارية ---

    public function test_no_assets_at_all_is_admin_liquidation(): void
    {
        $case = $this->baseCase(['has_assets' => 'no']);

        $this->assertSame('admin', $this->engine->recommend($case)->code);
    }

    public function test_assets_below_liquidation_cost_is_admin_small_debtor_track(): void
    {
        $case = $this->baseCase();
        $this->addAssetValue($case, 100_000); // < 150,000
        $this->addDebt($case, 500_000); // < 1,000,000، وموظفون <= 5

        $recommendation = $this->engine->recommend($case);

        $this->assertSame('admin', $recommendation->code);
        $this->assertStringContainsString('168/أ', $recommendation->title);
    }

    public function test_assets_exactly_at_liquidation_cost_is_not_admin(): void
    {
        $case = $this->baseCase(['is_active' => 'yes']);
        $this->addAssetValue($case, 150_000); // == العتبة بالضبط
        $this->addDebt($case, 100_000); // 150,000 > 100,000*0.3 → إعادة هيكلة

        $this->assertNotSame('admin', $this->engine->recommend($case)->code);
    }

    public function test_debts_exactly_one_million_is_not_small_debtor_track(): void
    {
        $case = $this->baseCase();
        $this->addAssetValue($case, 50_000); // أصول لا تكفي → إدارية بأي الحالتين
        $this->addDebt($case, 1_000_000); // == العتبة بالضبط

        $this->assertStringNotContainsString('168/أ', $this->engine->recommend($case)->title);
    }

    public function test_six_employees_is_not_small_debtor_track(): void
    {
        $case = $this->baseCase();
        $this->addAssetValue($case, 50_000);
        $this->addDebt($case, 500_000);
        for ($i = 0; $i < 6; $i++) {
            $case->employees()->create([
                'name' => "موظف {$i}", 'nationality' => 'سعودي', 'iqama' => '1000000000',
                'salary' => 5000, 'join_date' => now()->subYear(), 'added_by_user_id' => $case->created_by_user_id,
            ]);
        }

        $this->assertStringNotContainsString('168/أ', $this->engine->recommend($case)->title);
    }

    // --- إعادة هيكلة ---

    public function test_active_with_assets_over_thirty_percent_of_debts_is_restructuring(): void
    {
        $case = $this->baseCase(['is_active' => 'yes']);
        $this->addAssetValue($case, 400_000);
        $this->addDebt($case, 1_000_000); // 400,000 > 300,000 (30%)

        $this->assertSame('restructuring', $this->engine->recommend($case)->code);
    }

    public function test_assets_exactly_thirty_percent_of_debts_is_not_restructuring(): void
    {
        $case = $this->baseCase(['is_active' => 'yes']);
        $this->addAssetValue($case, 300_000); // == 30% بالضبط
        $this->addDebt($case, 1_000_000);

        $this->assertNotSame('restructuring', $this->engine->recommend($case)->code);
    }

    // --- تصفية عادية (fallback) ---

    public function test_inactive_with_sufficient_assets_falls_back_to_regular(): void
    {
        $case = $this->baseCase(['is_active' => 'no']);
        $this->addAssetValue($case, 400_000);
        $this->addDebt($case, 1_000_000);

        $this->assertSame('regular', $this->engine->recommend($case)->code);
    }

    // --- النواقص ---

    public function test_deficiencies_flags_all_expected_checks(): void
    {
        $case = $this->baseCase([
            'cr_number' => '123', // ليس 10 أرقام
            'attorney_name' => 'أب', // <= 3 أحرف
            'financial_statements_available' => 'no',
            'financial_transactions_available' => 'no',
            'creditors_notified' => 'no',
            'is_establishment' => 'company',
        ]);
        // لا دائنين ولا أصول → total_debts/total_assets = 0

        $messages = collect($this->engine->deficiencies($case))->pluck('message')->implode(' | ');

        $this->assertStringContainsString('10 أرقام', $messages);
        $this->assertStringContainsString('المحامي', $messages);
        $this->assertStringContainsString('الأصول', $messages);
        $this->assertStringContainsString('الديون', $messages);
        $this->assertStringContainsString('قرار الشركاء', $messages);
        $this->assertStringContainsString('القوائم المالية', $messages);
        $this->assertStringContainsString('المعاملات المالية', $messages);
        $this->assertStringContainsString('الدائنين', $messages);
        $this->assertStringContainsString('زكاة', $messages);
    }

    public function test_individual_establishment_does_not_require_shareholders_resolution(): void
    {
        $case = $this->baseCase(['is_establishment' => 'individual', 'cr_number' => '1010101010', 'attorney_name' => 'محامي كافٍ']);

        $messages = collect($this->engine->deficiencies($case))->pluck('message')->implode(' | ');

        $this->assertStringNotContainsString('قرار الشركاء', $messages);
    }

    public function test_shareholders_resolution_document_clears_the_deficiency(): void
    {
        $case = $this->baseCase(['is_establishment' => 'company', 'cr_number' => '1010101010', 'attorney_name' => 'محامي كافٍ']);
        CaseDocument::create([
            'bankruptcy_case_id' => $case->id, 'title' => 'قرار الشركاء بشأن التصفية',
            'original_filename' => 'x.pdf', 'disk' => 'local', 'path' => 'x', 'uploaded_by_user_id' => $case->created_by_user_id,
        ]);

        $messages = collect($this->engine->deficiencies($case))->pluck('message')->implode(' | ');

        $this->assertStringNotContainsString('قرار الشركاء', $messages);
    }

    // --- إصلاح خلل حقيقي: لا توصية قبل اكتمال المعالج ---

    public function test_brand_new_case_with_no_wizard_answers_is_not_ready(): void
    {
        $user = User::factory()->create();
        $case = BankruptcyCase::create(['created_by_user_id' => $user->id, 'title' => 'قضية جديدة فارغة', 'status' => 'draft']);

        $this->assertFalse($this->engine->isReadyForRecommendation($case));
    }

    public function test_fully_answered_wizard_is_ready(): void
    {
        $this->assertTrue($this->engine->isReadyForRecommendation($this->baseCase()));
    }

    public function test_missing_a_single_wizard_field_is_not_ready(): void
    {
        $case = $this->baseCase(['previous_settlement' => null]);

        $this->assertFalse($this->engine->isReadyForRecommendation($case));
    }
}
