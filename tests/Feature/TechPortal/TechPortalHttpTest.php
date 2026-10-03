<?php

namespace Tests\Feature\TechPortal;

use App\Models\TechService;
use App\Models\TechServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** بوابة التقنية عبر HTTP — عامة بالكامل بلا Auth، السلة Session حتى الإرسال. */
class TechPortalHttpTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TechService
    {
        return TechService::create([
            'title' => 'تصميم موقع إلكتروني', 'slug' => 'design-'.uniqid(),
            'description' => 'وصف الخدمة', 'category' => 'تطوير مواقع', 'is_published' => true, 'sort_order' => 0,
        ]);
    }

    public function test_guest_can_browse_the_catalog(): void
    {
        $service = $this->service();

        $this->get('/tech-portal')->assertOk()->assertSee($service->title);
    }

    public function test_unpublished_services_are_hidden_from_the_public_catalog(): void
    {
        $hidden = TechService::create([
            'title' => 'خدمة غير منشورة', 'slug' => 'hidden-'.uniqid(),
            'description' => 'وصف', 'is_published' => false, 'sort_order' => 0,
        ]);

        $this->get('/tech-portal')->assertOk()->assertDontSee($hidden->title);
    }

    public function test_guest_can_add_and_remove_items_from_the_session_cart(): void
    {
        $service = $this->service();

        $this->post("/tech-portal/cart/{$service->id}")->assertRedirect();
        $this->get('/tech-portal/cart')->assertOk()->assertSee($service->title);

        // ملاحظة: لا نستخدم assertDontSee($service->title) هنا — رسالة
        // التأكيد نفسها ("أُزيلت X من السلة") تذكر العنوان شرعًا، فتفشل
        // الفحص دائمًا. نتحقق من حالة "السلة فارغة" الفعلية بدل ذلك.
        $this->delete("/tech-portal/cart/{$service->id}")->assertRedirect();
        $this->get('/tech-portal/cart')->assertOk()->assertSee('سلتك فارغة');
    }

    public function test_guest_can_submit_a_cart_request_without_an_account(): void
    {
        $service = $this->service();

        $this->post("/tech-portal/cart/{$service->id}")->assertRedirect();

        $this->post('/tech-portal/cart/submit', [
            'name' => 'عميل زائر', 'email' => 'visitor@example.test', 'message' => 'أحتاج تصميم موقع.',
        ])->assertRedirect(route('tech-portal.index'));

        $this->assertDatabaseHas('tech_service_requests', ['email' => 'visitor@example.test', 'user_id' => null]);

        // السلة تُفرَّغ بعد الإرسال.
        $this->get('/tech-portal/cart')->assertOk()->assertDontSee($service->title);
    }

    public function test_submitting_an_empty_cart_is_rejected(): void
    {
        $this->post('/tech-portal/cart/submit', [
            'name' => 'عميل', 'email' => 'client@example.test',
        ])->assertRedirect()->assertSessionHasErrors('cart');

        $this->assertSame(0, TechServiceRequest::count());
    }

    public function test_registered_user_submission_links_their_account(): void
    {
        $user = User::factory()->create();
        $service = $this->service();

        $this->actingAs($user)->post("/tech-portal/cart/{$service->id}")->assertRedirect();
        $this->actingAs($user)->post('/tech-portal/cart/submit', [
            'name' => $user->name, 'email' => $user->email,
        ])->assertRedirect();

        $this->assertDatabaseHas('tech_service_requests', ['email' => $user->email, 'user_id' => $user->id]);
    }
}
