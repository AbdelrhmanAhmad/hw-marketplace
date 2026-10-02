<?php

namespace Tests\Feature\TechPortal;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\TechService;
use App\Models\User;
use App\Services\TechServiceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/** بوابة التقنية — إرسال طلب "سلة" الخدمات. مزوّد واحد (المنصة)، لا Policy/ملكية هنا. */
class TechServiceRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(array $overrides = []): TechService
    {
        return TechService::create(array_merge([
            'title' => 'تصميم موقع إلكتروني', 'slug' => 'design-'.uniqid(),
            'description' => 'وصف الخدمة', 'is_published' => true, 'sort_order' => 0,
        ], $overrides));
    }

    public function test_can_submit_a_request_with_multiple_services(): void
    {
        $s1 = $this->service();
        $s2 = $this->service(['title' => 'تطبيق جوال', 'slug' => 'app-'.uniqid()]);

        $request = app(TechServiceRequestService::class)->submitRequest(null, [$s1->id, $s2->id], [
            'name' => 'عميل محتمل', 'email' => 'client@example.test',
        ]);

        $this->assertSame(2, $request->items()->count());
    }

    public function test_empty_cart_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(TechServiceRequestService::class)->submitRequest(null, [], ['name' => 'أحمد', 'email' => 'a@example.test']);
    }

    public function test_unpublished_service_cannot_be_requested(): void
    {
        $hidden = $this->service(['is_published' => false]);

        $this->expectException(InvalidArgumentException::class);
        app(TechServiceRequestService::class)->submitRequest(null, [$hidden->id], ['name' => 'أحمد', 'email' => 'a@example.test']);
    }

    public function test_registered_user_request_is_audit_logged(): void
    {
        $user = User::factory()->create();
        $s = $this->service();

        app(TechServiceRequestService::class)->submitRequest($user, [$s->id], ['name' => $user->name, 'email' => $user->email]);

        $this->assertTrue(AuditLog::where('event', AuditEvent::TechServiceRequestSubmitted->value)->exists());
    }

    /** زائر ضيف بلا حساب حقيقي — audit_logs.actor_user_id إلزامي، فلا تسجيل تدقيقي، لكن الطلب نفسه يُحفَظ. */
    public function test_guest_request_is_not_audit_logged_but_is_saved(): void
    {
        $s = $this->service();

        $request = app(TechServiceRequestService::class)->submitRequest(null, [$s->id], ['name' => 'زائر', 'email' => 'guest@example.test']);

        $this->assertNull($request->user_id);
        $this->assertFalse(AuditLog::where('event', AuditEvent::TechServiceRequestSubmitted->value)->exists());
    }

    public function test_staff_can_update_request_status(): void
    {
        $s = $this->service();
        $request = app(TechServiceRequestService::class)->submitRequest(null, [$s->id], ['name' => 'زائر', 'email' => 'guest@example.test']);

        app(TechServiceRequestService::class)->updateStatus($request, 'contacted');

        $this->assertSame('contacted', $request->fresh()->status);
    }
}
