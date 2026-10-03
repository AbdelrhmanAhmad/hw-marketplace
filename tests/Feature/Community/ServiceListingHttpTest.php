<?php

namespace Tests\Feature\Community;

use App\Models\MarketplaceItem;
use App\Models\ServiceListing;
use App\Models\ServiceListingInquiry;
use App\Models\User;
use App\Services\ServiceListingService;
use App\Services\SubscriptionService;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * مجتمع الخدمات عبر HTTP — الصفحة العامة (/community) بلا Auth، مطابقة
 * تمامًا لبوابة المقالات؛ لوحة "إعلاناتي" (/apps/community) وحدها خلف
 * marketplace.entitled:community.
 */
class ServiceListingHttpTest extends TestCase
{
    use RefreshDatabase;

    private function activate(User $user): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $item = MarketplaceItem::where('key', 'community')->firstOrFail();
        app(SubscriptionService::class)->subscribeUserToFreeItem($user, $item);
    }

    private function openListing(User $owner): ServiceListing
    {
        return app(ServiceListingService::class)->createListing($owner, [
            'category' => 'قانوني', 'title' => 'استشارات تأسيس شركات', 'description' => 'وصف كافٍ للخدمة المعروضة.',
            'contact_method' => 'phone', 'contact_value' => '0500000000',
        ]);
    }

    public function test_guest_can_browse_the_public_directory(): void
    {
        $owner = User::factory()->create();
        $this->openListing($owner);

        $this->get('/community')->assertOk()->assertSee('استشارات تأسيس شركات');
    }

    public function test_guest_can_view_a_listing_and_see_contact_info(): void
    {
        $owner = User::factory()->create();
        $listing = $this->openListing($owner);

        $this->get("/community/{$listing->id}")->assertOk()->assertSee('0500000000');
    }

    public function test_guest_gets_404_for_a_closed_listing(): void
    {
        $owner = User::factory()->create();
        $listing = $this->openListing($owner);
        app(ServiceListingService::class)->closeListing($owner, $listing);

        $this->get("/community/{$listing->id}")->assertNotFound();
    }

    public function test_guest_can_submit_an_inquiry_without_logging_in(): void
    {
        $owner = User::factory()->create();
        $listing = $this->openListing($owner);

        $this->post("/community/{$listing->id}/inquiry", [
            'name' => 'زائر ضيف', 'email' => 'guest@example.test', 'message' => 'أحتاج الخدمة.',
        ])->assertRedirect();

        $this->assertDatabaseHas('service_listing_inquiries', ['email' => 'guest@example.test', 'user_id' => null]);
    }

    public function test_dashboard_requires_activating_the_free_app_first(): void
    {
        $this->seed(MarketplaceCatalogSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/apps/community')->assertForbidden();
    }

    public function test_full_listing_and_inquiry_flow_via_real_http_requests(): void
    {
        $owner = User::factory()->create();
        $this->activate($owner);

        $this->actingAs($owner)->get('/apps/community')->assertOk();

        $this->actingAs($owner)->post('/apps/community', [
            'category' => 'قانوني',
            'title' => 'استشارات عبر HTTP',
            'description' => 'وصف كافٍ للخدمة المعروضة.',
            'contact_method' => 'email',
            'contact_value' => 'lawyer@example.test',
        ])->assertRedirect();

        $listing = ServiceListing::where('title', 'استشارات عبر HTTP')->firstOrFail();

        // ظاهر مباشرة للعامة بلا مراجعة — لا بوابة موافقة هنا.
        $this->get('/community')->assertOk()->assertSee('استشارات عبر HTTP');

        // actingAs() يبقى فعّالًا للطلبات اللاحقة بنفس الاختبار — نستخدم
        // زائرًا آخر حقيقيًا (لا نفس المالك) لتفادي حظر "استفسار لنفسك".
        $otherVisitor = User::factory()->create();
        $this->actingAs($otherVisitor)->post("/community/{$listing->id}/inquiry", [
            'name' => 'عميل محتمل', 'email' => 'client@example.test', 'message' => 'أحتاج استشارة تأسيس.',
        ])->assertRedirect();

        $this->assertSame(1, ServiceListingInquiry::where('service_listing_id', $listing->id)->count());

        $this->actingAs($owner)->get("/apps/community/{$listing->id}")->assertOk()->assertSee('عميل محتمل');

        $this->actingAs($owner)->post("/apps/community/{$listing->id}/close")->assertRedirect();
        $this->assertSame('closed', $listing->fresh()->status);

        $this->get('/community')->assertOk()->assertDontSee('استشارات عبر HTTP');
        $this->get("/community/{$listing->id}")->assertNotFound();
    }

    public function test_stranger_cannot_close_another_users_listing_via_http(): void
    {
        $owner = User::factory()->create();
        $this->activate($owner);
        $listing = $this->openListing($owner);

        $stranger = User::factory()->create();
        $this->activate($stranger);

        $this->actingAs($stranger)->post("/apps/community/{$listing->id}/close")->assertForbidden();
        $this->assertSame('open', $listing->fresh()->status, 'لا يجب أن يقدر مستخدم غريب إغلاق إعلان غيره');
    }

    public function test_stranger_cannot_view_another_users_listing_management_page(): void
    {
        $owner = User::factory()->create();
        $this->activate($owner);
        $listing = $this->openListing($owner);

        $stranger = User::factory()->create();
        $this->activate($stranger);

        $this->actingAs($stranger)->get("/apps/community/{$listing->id}")->assertForbidden();
    }
}
