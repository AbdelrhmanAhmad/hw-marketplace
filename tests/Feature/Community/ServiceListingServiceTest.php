<?php

namespace Tests\Feature\Community;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\ServiceListing;
use App\Models\User;
use App\Services\ServiceListingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * مجتمع الخدمات — دليل عام (محامون/مختصون يعرضون خدماتهم للعامة). لا بوابة
 * موافقة إدارية (بعكس المقالات) — النشر فوري، المالك وحده يغلق إعلانه.
 * الاستفسار مسموح لأي زائر ضيف (بلا حساب) — راجع ServiceListingHttpTest
 * لتحقق مسار الزائر الضيف كاملًا عبر HTTP.
 */
class ServiceListingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function listing(User $owner): ServiceListing
    {
        return app(ServiceListingService::class)->createListing($owner, [
            'category' => 'قانوني', 'title' => 'استشارات تأسيس', 'description' => 'وصف كافٍ للخدمة.',
            'contact_method' => 'phone', 'contact_value' => '0500000000',
        ]);
    }

    public function test_user_can_create_a_listing(): void
    {
        $user = User::factory()->create();

        $listing = $this->listing($user);

        $this->assertSame('open', $listing->status);
        $this->assertTrue(AuditLog::where('event', AuditEvent::ServiceListingCreated->value)->exists());
    }

    public function test_invalid_contact_method_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        app(ServiceListingService::class)->createListing($user, [
            'category' => 'قانوني', 'title' => 'عنوان', 'description' => 'وصف',
            'contact_method' => 'carrier-pigeon', 'contact_value' => 'x',
        ]);
    }

    public function test_owner_can_close_their_listing(): void
    {
        $owner = User::factory()->create();
        $listing = $this->listing($owner);

        app(ServiceListingService::class)->closeListing($owner, $listing);

        $this->assertSame('closed', $listing->fresh()->status);
        $this->assertNotNull($listing->fresh()->closed_at);
    }

    public function test_stranger_cannot_close_another_users_listing(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $listing = $this->listing($owner);

        $this->expectException(AuthorizationException::class);
        app(ServiceListingService::class)->closeListing($stranger, $listing);
    }

    public function test_cannot_close_an_already_closed_listing(): void
    {
        $owner = User::factory()->create();
        $listing = $this->listing($owner);
        app(ServiceListingService::class)->closeListing($owner, $listing);

        $this->expectException(InvalidArgumentException::class);
        app(ServiceListingService::class)->closeListing($owner, $listing);
    }

    public function test_a_registered_user_can_submit_an_inquiry(): void
    {
        $owner = User::factory()->create();
        $inquirer = User::factory()->create(['name' => 'مستفسر', 'email' => 'inquirer@example.test']);
        $listing = $this->listing($owner);

        $inquiry = app(ServiceListingService::class)->submitInquiry($inquirer, $listing, [
            'name' => 'مستفسر', 'email' => 'inquirer@example.test', 'message' => 'أحتاج استشارة.',
        ]);

        $this->assertSame($inquirer->id, $inquiry->user_id);
        $this->assertTrue(AuditLog::where('event', AuditEvent::ServiceListingInquirySubmitted->value)->exists());
    }

    /** الزائر الضيف بلا حساب — لا actor حقيقي، فلا AuditLog (القيد إلزامي بجدول audit_logs)، لكن الاستفسار نفسه يُحفَظ. */
    public function test_a_guest_can_submit_an_inquiry_without_an_account(): void
    {
        $owner = User::factory()->create();
        $listing = $this->listing($owner);

        $inquiry = app(ServiceListingService::class)->submitInquiry(null, $listing, [
            'name' => 'زائر ضيف', 'email' => 'guest@example.test', 'message' => 'أحتاج الخدمة.',
        ]);

        $this->assertNull($inquiry->user_id);
        $this->assertSame('زائر ضيف', $inquiry->name);
        $this->assertFalse(AuditLog::where('event', AuditEvent::ServiceListingInquirySubmitted->value)->exists());
    }

    public function test_owner_cannot_submit_an_inquiry_to_their_own_listing(): void
    {
        $owner = User::factory()->create();
        $listing = $this->listing($owner);

        $this->expectException(InvalidArgumentException::class);
        app(ServiceListingService::class)->submitInquiry($owner, $listing, ['name' => $owner->name, 'email' => $owner->email]);
    }

    public function test_cannot_submit_an_inquiry_to_a_closed_listing(): void
    {
        $owner = User::factory()->create();
        $listing = $this->listing($owner);
        app(ServiceListingService::class)->closeListing($owner, $listing);

        $this->expectException(InvalidArgumentException::class);
        app(ServiceListingService::class)->submitInquiry(null, $listing, ['name' => 'زائر', 'email' => 'g@example.test']);
    }
}
