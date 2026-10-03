<?php

namespace Tests\Feature\BankruptcyTech;

use App\Models\BankruptcyCase;
use App\Models\User;
use App\Support\BankruptcyLegalDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * إفلاس تك — المرحلة 3. يتأكد إن كل مستند من الستة يتضمّن القيم الفعلية
 * من القضية (لا نص عام ثابت). Feature-level (لا Unit) لأن total_debts/
 * total_assets Accessors تُحسَب حيًا من قاعدة البيانات (نفس سبب
 * BankruptcyRecommendationEngineTest بالمرحلة 1).
 */
class BankruptcyLegalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function fixtureCase(array $overrides = []): BankruptcyCase
    {
        $user = User::factory()->create();

        $case = BankruptcyCase::create(array_merge([
            'created_by_user_id' => $user->id,
            'title' => 'قضية اختبار',
            'status' => 'draft',
            'debtor_name' => 'شركة الاختبار المحدودة',
            'legal_form' => 'ذات مسؤولية محدودة',
            'cr_number' => '1010101010',
            'cr_city' => 'الرياض',
            'court_city' => 'جدة',
            'representative_name' => 'محمد الاختبار',
            'representative_title' => 'المدير التنفيذي',
            'representative_id' => '1234567890',
            'attorney_name' => 'أحمد المحامي',
            'attorney_license' => '41/892',
            'poa_number' => '99',
            'poa_city' => 'الرياض',
        ], $overrides));

        $case->creditors()->create(['name' => 'دائن', 'amount' => 500000, 'priority' => 'p3_unsecured', 'type' => 'تجاري', 'date' => now(), 'added_by_user_id' => $user->id]);
        $case->assets()->create(['name' => 'أصل', 'value' => 80000, 'location' => 'الرياض', 'added_by_user_id' => $user->id]);

        return $case->fresh();
    }

    public function test_claim_contains_debtor_name_and_totals(): void
    {
        $text = app(BankruptcyLegalDocuments::class)->claim($this->fixtureCase());

        $this->assertStringContainsString('شركة الاختبار المحدودة', $text);
        $this->assertStringContainsString('1010101010', $text);
        $this->assertStringContainsString('500,000', $text);
        $this->assertStringContainsString('80,000', $text);
        $this->assertStringContainsString('أحمد المحامي', $text);
        $this->assertStringContainsString('المادة (168)', $text);
    }

    public function test_shareholders_resolution_contains_attorney_license(): void
    {
        $text = app(BankruptcyLegalDocuments::class)->shareholdersResolution($this->fixtureCase());

        $this->assertStringContainsString('شركة الاختبار المحدودة', $text);
        $this->assertStringContainsString('41/892', $text);
    }

    public function test_creditors_notice_contains_representative_name_and_totals(): void
    {
        $text = app(BankruptcyLegalDocuments::class)->creditorsNotice($this->fixtureCase());

        $this->assertStringContainsString('محمد الاختبار', $text);
        $this->assertStringContainsString('500,000', $text);
        $this->assertStringContainsString('80,000', $text);
    }

    public function test_power_of_attorney_contains_poa_number_and_license(): void
    {
        $text = app(BankruptcyLegalDocuments::class)->powerOfAttorney($this->fixtureCase());

        $this->assertStringContainsString('رقم الوكالة: 99', $text);
        $this->assertStringContainsString('41/892', $text);
        $this->assertStringContainsString('محمد الاختبار', $text);
    }

    public function test_financial_statement_excuse_letter_cites_article_168(): void
    {
        $text = app(BankruptcyLegalDocuments::class)->financialStatementExcuseLetter($this->fixtureCase());

        $this->assertStringContainsString('المادة (168)', $text);
        $this->assertStringContainsString('أحمد المحامي', $text);
    }

    public function test_financial_transactions_statement_mentions_24_months(): void
    {
        $text = app(BankruptcyLegalDocuments::class)->financialTransactionsStatement($this->fixtureCase());

        $this->assertStringContainsString('24 شهراً', $text);
        $this->assertStringContainsString('1010101010', $text);
    }

    public function test_missing_optional_fields_render_as_placeholder_dots(): void
    {
        $text = app(BankruptcyLegalDocuments::class)->powerOfAttorney($this->fixtureCase(['poa_number' => null]));

        $this->assertStringContainsString('................', $text);
    }
}
