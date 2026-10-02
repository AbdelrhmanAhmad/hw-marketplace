<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\TechService;
use App\Models\TechServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * بوابة التقنية — نقطة الدخول الوحيدة لإرسال طلب سلة الخدمات (BR-013).
 * السلة نفسها Session فقط (لا مطلب Auth للتصفح أو الإضافة) — هذي الخدمة
 * تُستدعى فقط عند "إرسال الطلب" النهائي، حيث يصبح تسجيل حقيقي بقاعدة البيانات.
 */
class TechServiceRequestService
{
    /** @param  int[]  $serviceIds */
    public function submitRequest(?User $actor, array $serviceIds, array $leadData): TechServiceRequest
    {
        $serviceIds = array_values(array_unique($serviceIds));

        if (empty($serviceIds)) {
            throw new InvalidArgumentException('السلة فارغة — أضف خدمة واحدة على الأقل قبل الإرسال.');
        }

        if (trim($leadData['name'] ?? '') === '' || trim($leadData['email'] ?? '') === '') {
            throw new InvalidArgumentException('الاسم والبريد الإلكتروني مطلوبان.');
        }

        $services = TechService::published()->whereIn('id', $serviceIds)->get();

        if ($services->count() !== count($serviceIds)) {
            throw new InvalidArgumentException('إحدى الخدمات بالسلة لم تعد متاحة — يرجى تحديث السلة.');
        }

        return DB::transaction(function () use ($actor, $services, $leadData) {
            $request = TechServiceRequest::create([
                'user_id' => $actor?->id,
                'name' => $leadData['name'],
                'email' => $leadData['email'],
                'phone' => $leadData['phone'] ?? null,
                'message' => $leadData['message'] ?? null,
                'status' => 'new',
            ]);

            foreach ($services as $service) {
                $request->items()->create(['tech_service_id' => $service->id]);
            }

            // audit_logs.actor_user_id إلزامي (بلا NULL) — زائر ضيف بلا حساب
            // حقيقي لا يُسجَّل تدقيقيًا، الطلب نفسه محفوظ بجدوله على أي حال
            // (نفس نمط ServiceListingService::submitInquiry).
            if ($actor) {
                AuditLog::create([
                    'organization_id' => null,
                    'actor_user_id' => $actor->id,
                    'event' => AuditEvent::TechServiceRequestSubmitted->value,
                    'subject_type' => TechServiceRequest::class,
                    'subject_id' => $request->id,
                    'metadata' => ['service_ids' => $services->pluck('id')->all()],
                ]);
            }

            return $request;
        });
    }

    /** تحديث حالة المتابعة (تم التواصل/مغلق) — يُستدعى حصرًا من Filament (is_platform_staff هو الحارس الوحيد). */
    public function updateStatus(TechServiceRequest $request, string $status): void
    {
        if (! in_array($status, ['new', 'contacted', 'closed'], true)) {
            throw new InvalidArgumentException('حالة غير صالحة.');
        }

        $request->update(['status' => $status]);
    }
}
