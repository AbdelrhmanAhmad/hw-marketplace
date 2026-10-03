<?php

namespace Database\Seeders;

use App\Enums\MembershipRole;
use App\Models\BankruptcyCase;
use App\Models\Membership;
use App\Models\MarketplaceItem;
use App\Models\Organization;
use App\Models\User;
use App\Services\BankruptcyCaseService;
use App\Services\OrganizationSubscriptionService;
use App\Services\SeatService;
use Illuminate\Database\Seeder;

/**
 * Final Execution Sprint — بيانات Demo واضحة وموسومة (لا Mock يُقدَّم كحقيقي).
 * **لا تُشغَّل تلقائيًا** (غير مُدرَجة بـDatabaseSeeder) — اختيارية صراحة لمن
 * يحتاج تجربة التطبيق بصريًا: `php artisan db:seed --class=BankruptcyTechDemoSeeder`.
 * كل اسم يحمل "(Demo)" صراحة — لا لبس مع بيانات حقيقية.
 *
 * يمنح اشتراكًا مؤسسيًا حقيقيًا + مقعدًا فعليًا (لا يكتفي بإنشاء القضية
 * مباشرة عبر الـService) — بدون هذا، الحساب يملك بيانات لكن يُرفَض فعليًا
 * عبر المتصفح (EnsureMarketplaceEntitlement)، فجوة اكتُشفت ومُصلَحة الآن.
 */
class BankruptcyTechDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'demo-bankruptcy-tech-owner@example.test'],
            ['name' => 'مستخدم تجريبي (Demo)', 'password' => bcrypt('demo-password-not-for-production')],
        );

        $organization = Organization::firstOrCreate(
            ['name' => 'مكتب تجريبي لإفلاس تك (Demo)'],
            ['type' => 'firm', 'owner_id' => $owner->id],
        );

        Membership::firstOrCreate(
            ['user_id' => $owner->id, 'organization_id' => $organization->id],
            ['role' => MembershipRole::Owner],
        );

        $item = MarketplaceItem::where('key', 'bankruptcy-tech')->firstOrFail();
        $subscription = $organization->marketplaceSubscriptions()->where('marketplace_item_id', $item->id)->first()
            ?? app(OrganizationSubscriptionService::class)->create($owner, $organization, $item, 'Professional (Demo)', 5);

        app(SeatService::class)->assign($owner, $subscription, $owner);

        // Idempotent — لا قضية Demo مكرَّرة عند إعادة تشغيل الـSeeder.
        $case = BankruptcyCase::where('organization_id', $organization->id)
            ->where('title', 'قضية تجريبية للعرض (Demo)')
            ->first();

        if ($case) {
            return;
        }

        $case = app(BankruptcyCaseService::class)->createCase(
            $owner,
            $organization,
            'قضية تجريبية للعرض (Demo)',
            'قضية Demo لغرض التجربة البصرية فقط — لا تمثّل بيانات حقيقية.',
        );

        app(BankruptcyCaseService::class)->addParty($owner, $case, [
            'name' => 'منشأة تجريبية (Demo)', 'role' => 'debtor', 'identifier' => '0000000000',
        ]);

        app(BankruptcyCaseService::class)->addProcedure($owner, $case, [
            'title' => 'إخطار الدائنين (Demo)',
        ]);

        app(BankruptcyCaseService::class)->addNote($owner, $case, 'ملاحظة تجريبية — بيانات Demo فقط.');
    }
}
