<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\ServiceListing;
use App\Models\ServiceListingInquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * مجتمع الخدمات — نقطة الدخول الوحيدة لكل Mutation (BR-013، نفس نمط
 * ArticleService/BankruptcyCaseService). دليل عام (محامون/مختصون يعرضون
 * خدماتهم للعامة، تصفح بلا Auth مطابق للمقالات) — لا بوابة موافقة إدارية،
 * النشر فوري، المالك وحده يتحكم بإيقاف ظهور إعلانه.
 */
class ServiceListingService
{
    public const array VALID_CATEGORIES = ['قانوني', 'مالي', 'محاسبي', 'عام'];

    public const array VALID_CONTACT_METHODS = ['phone', 'email', 'whatsapp'];

    public function createListing(User $actor, array $data): ServiceListing
    {
        if (trim($data['title'] ?? '') === '' || trim($data['description'] ?? '') === '') {
            throw new InvalidArgumentException('العنوان والوصف مطلوبان.');
        }

        if (! in_array($data['category'] ?? null, self::VALID_CATEGORIES, true)) {
            throw new InvalidArgumentException('التصنيف غير صالح.');
        }

        if (! in_array($data['contact_method'] ?? null, self::VALID_CONTACT_METHODS, true)) {
            throw new InvalidArgumentException('وسيلة التواصل غير صالحة.');
        }

        if (trim($data['contact_value'] ?? '') === '') {
            throw new InvalidArgumentException('بيانات التواصل مطلوبة.');
        }

        return DB::transaction(function () use ($actor, $data) {
            $listing = ServiceListing::create([
                'user_id' => $actor->id,
                'category' => $data['category'],
                'title' => $data['title'],
                'description' => $data['description'],
                'contact_method' => $data['contact_method'],
                'contact_value' => $data['contact_value'],
                'status' => 'open',
            ]);

            $this->log($actor, AuditEvent::ServiceListingCreated, $listing, ['title' => $listing->title]);

            return $listing;
        });
    }

    public function closeListing(User $actor, ServiceListing $listing): void
    {
        Gate::forUser($actor)->authorize('close', $listing);

        if (! $listing->isOpen()) {
            throw new InvalidArgumentException('هذا الإعلان مُغلَق بالفعل.');
        }

        DB::transaction(function () use ($actor, $listing) {
            $listing->update(['status' => 'closed', 'closed_at' => now()]);
            $this->log($actor, AuditEvent::ServiceListingClosed, $listing, ['title' => $listing->title]);
        });
    }

    /**
     * استفسار عام — أي زائر يقدر يرسله (لا يتطلب تسجيل دخول، مطابق لنمط
     * ServiceInterest العام بالمنصة). $actor يبقى null لزائر ضيف.
     */
    public function submitInquiry(?User $actor, ServiceListing $listing, array $data): ServiceListingInquiry
    {
        if (! $listing->isOpen()) {
            throw new InvalidArgumentException('هذا الإعلان مُغلَق، لا يمكن إرسال استفسار له.');
        }

        if ($actor && $actor->id === $listing->user_id) {
            throw new InvalidArgumentException('لا يمكنك إرسال استفسار لإعلانك أنت.');
        }

        if (trim($data['name'] ?? '') === '' || trim($data['email'] ?? '') === '') {
            throw new InvalidArgumentException('الاسم والبريد الإلكتروني مطلوبان.');
        }

        return DB::transaction(function () use ($actor, $listing, $data) {
            $inquiry = ServiceListingInquiry::create([
                'service_listing_id' => $listing->id,
                'user_id' => $actor?->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'message' => $data['message'] ?? null,
            ]);

            // audit_logs.actor_user_id إلزامي (بلا NULL، قيد قديم من Append-Only
            // Trigger) — زائر ضيف بلا حساب حقيقي، لا يُسجَّل تدقيقيًا (نسبته
            // لصاحب الإعلان تضليل، والاستفسار نفسه محفوظ بجدوله على أي حال).
            if ($actor) {
                $this->log($actor, AuditEvent::ServiceListingInquirySubmitted, $listing, [
                    'inquirer_email' => $inquiry->email,
                ]);
            }

            return $inquiry;
        });
    }

    private function log(User $actor, AuditEvent $event, $subject, array $metadata = []): void
    {
        AuditLog::create([
            'organization_id' => null,
            'actor_user_id' => $actor->id,
            'event' => $event->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'metadata' => $metadata,
        ]);
    }
}
