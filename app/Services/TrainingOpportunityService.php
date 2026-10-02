<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\TrainingApplication;
use App\Models\TrainingApplicationDocument;
use App\Models\TrainingOpportunity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * بوابة التدريب التعاوني — نقطة الدخول الوحيدة لكل Mutation (BR-013، نفس
 * نمط ServiceListingService). لا بوابة موافقة إدارية — النشر فوري. التقديم
 * يتطلب دائمًا مستخدمًا مسجَّلًا حقيقيًا (بعكس استفسارات مجتمع الخدمات
 * العامة) — لا $actor اختياري هنا.
 */
class TrainingOpportunityService
{
    public const array VALID_CATEGORIES = ['قانوني', 'مالي', 'محاسبي', 'عام'];

    /** مستندات إلزامية بكل تقديم — تطابق متطلبات التدريب التعاوني الفعلية بالجامعات السعودية. */
    public const array REQUIRED_DOCUMENT_TYPES = ['cv', 'enrollment_letter'];

    public const array OPTIONAL_DOCUMENT_TYPES = ['transcript', 'national_id'];

    public function createOpportunity(User $actor, array $data): TrainingOpportunity
    {
        if (trim($data['title'] ?? '') === '' || trim($data['description'] ?? '') === '') {
            throw new InvalidArgumentException('العنوان والوصف مطلوبان.');
        }

        if (! in_array($data['category'] ?? null, self::VALID_CATEGORIES, true)) {
            throw new InvalidArgumentException('التصنيف غير صالح.');
        }

        return DB::transaction(function () use ($actor, $data) {
            $opportunity = TrainingOpportunity::create([
                'user_id' => $actor->id,
                'category' => $data['category'],
                'title' => $data['title'],
                'description' => $data['description'],
                'location' => $data['location'] ?? null,
                'duration' => $data['duration'] ?? null,
                'status' => 'open',
            ]);

            $this->log($actor, AuditEvent::TrainingOpportunityCreated, $opportunity, ['title' => $opportunity->title]);

            return $opportunity;
        });
    }

    public function closeOpportunity(User $actor, TrainingOpportunity $opportunity): void
    {
        Gate::forUser($actor)->authorize('close', $opportunity);

        if (! $opportunity->isOpen()) {
            throw new InvalidArgumentException('هذي الفرصة مُغلَقة بالفعل.');
        }

        DB::transaction(function () use ($actor, $opportunity) {
            $opportunity->update(['status' => 'closed', 'closed_at' => now()]);
            $this->log($actor, AuditEvent::TrainingOpportunityClosed, $opportunity, ['title' => $opportunity->title]);
        });
    }

    /**
     * التقديم يتطلب طالبًا مسجَّلًا حقيقيًا دائمًا — لا زائر ضيف (بعكس
     * استفسارات مجتمع الخدمات). $documents مصفوفة [type => UploadedFile|null]
     * — السيرة الذاتية وإفادة القيد إلزاميتان (REQUIRED_DOCUMENT_TYPES)،
     * كشف الدرجات وصورة الهوية اختياريتان.
     *
     * @param  array<string, UploadedFile|null>  $documents
     */
    public function submitApplication(User $actor, TrainingOpportunity $opportunity, ?string $message, array $documents = []): TrainingApplication
    {
        if (! $opportunity->isOpen()) {
            throw new InvalidArgumentException('هذي الفرصة مُغلَقة، لا يمكن التقديم عليها.');
        }

        if ($actor->id === $opportunity->user_id) {
            throw new InvalidArgumentException('لا يمكنك التقديم على فرصتك أنت.');
        }

        if (TrainingApplication::where('training_opportunity_id', $opportunity->id)->where('user_id', $actor->id)->exists()) {
            throw new InvalidArgumentException('قدَّمت على هذي الفرصة مسبقًا.');
        }

        foreach (self::REQUIRED_DOCUMENT_TYPES as $type) {
            if (empty($documents[$type])) {
                throw new InvalidArgumentException('المستند المطلوب ["'.(TrainingApplicationDocument::TYPES[$type] ?? $type).'"] لم يُرفَع.');
            }
        }

        return DB::transaction(function () use ($actor, $opportunity, $message, $documents) {
            $application = TrainingApplication::create([
                'training_opportunity_id' => $opportunity->id,
                'user_id' => $actor->id,
                'message' => $message,
                'status' => 'submitted',
            ]);

            foreach ([...self::REQUIRED_DOCUMENT_TYPES, ...self::OPTIONAL_DOCUMENT_TYPES] as $type) {
                /** @var UploadedFile|null $file */
                $file = $documents[$type] ?? null;

                if (! $file) {
                    continue;
                }

                // قرص local خاص (لا public) — مستندات شخصية حسّاسة (هوية،
                // كشف درجات)، نفس نمط bankruptcy_case_documents تمامًا.
                $path = $file->store('training-applications/'.$application->id, 'local');

                $application->documents()->create([
                    'type' => $type,
                    'original_filename' => $file->getClientOriginalName(),
                    'disk' => 'local',
                    'path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                ]);
            }

            $this->log($actor, AuditEvent::TrainingApplicationSubmitted, $opportunity, ['applicant_id' => $actor->id]);

            return $application;
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
