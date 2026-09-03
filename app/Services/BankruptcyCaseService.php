<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\BankruptcyCase;
use App\Models\CaseAsset;
use App\Models\CaseCreditor;
use App\Models\CaseDocument;
use App\Models\CaseEmployee;
use App\Models\CaseHearing;
use App\Models\CaseNote;
use App\Models\CaseParty;
use App\Models\CaseProcedure;
use App\Models\CaseTimelineEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * إفلاس تك — نقطة الدخول الوحيدة لكل Mutation (BR-013، نفس نمط
 * OrganizationSubscriptionService/SeatService/MembershipService بالضبط). لا
 * Model::update()/create() مباشر من Controller/Filament. كل تابع يتحقق من
 * BankruptcyCasePolicy داخليًا أولًا (Authorization)، بمعزل تام عن أي فحص
 * Entitlement (يحدث بمستوى أعلى، قبل الوصول لهذا الملف إطلاقًا — الفصل
 * الصريح بين "يقدر يستخدم التطبيق؟" و"يقدر يفعل هذا بالقضية؟").
 */
class BankruptcyCaseService
{
    private const array VALID_STATUSES = ['draft', 'preparing', 'submitted', 'decided', 'closed'];

    private const array VALID_PARTY_ROLES = ['debtor', 'creditor', 'trustee', 'other'];

    private const array VALID_PROCEDURE_STATUSES = ['pending', 'in_progress', 'completed'];

    private const array VALID_CREDITOR_PRIORITIES = [
        'p1_expenses', 'p1_employees', 'p1_government', 'p2_secured', 'p3_unsecured', 'p4_deferred',
    ];

    private const array VALID_HEARING_TYPES = ['جلسة_أولى', 'جلسة_موضوع', 'جلسة_قرار', 'أخرى'];

    private const array VALID_WIZARD_YES_NO_FIELDS = [
        'is_active', 'has_assets', 'assets_cover_expenses', 'financial_statements_available',
        'financial_transactions_available', 'creditors_notified', 'operated_twelve_months', 'previous_settlement',
    ];

    public function createCase(User $actor, ?Organization $organization, string $title, ?string $description = null): BankruptcyCase
    {
        Gate::forUser($actor)->authorize('createForOrganization', [BankruptcyCase::class, $organization]);

        if (trim($title) === '') {
            throw new InvalidArgumentException('عنوان القضية مطلوب.');
        }

        return DB::transaction(function () use ($actor, $organization, $title, $description) {
            $case = BankruptcyCase::create([
                'organization_id' => $organization?->id,
                'created_by_user_id' => $actor->id,
                'title' => $title,
                'description' => $description,
                'status' => 'draft',
                'opened_at' => now()->toDateString(),
            ]);

            // رقم قضية مستقر يعتمد على id بعد إنشاء الصف — لا سباق تسلسل ممكن.
            $case->update(['case_number' => 'BK-'.now()->year.'-'.str_pad((string) $case->id, 5, '0', STR_PAD_LEFT)]);

            // زرع الجدول الزمني القانوني الثابت (8 مراحل نظامية) — نفس
            // DEFAULT_TIMELINE_EVENTS بـhw-eflas حرفيًا.
            foreach (CaseTimelineEvent::DEFAULTS as $index => $event) {
                CaseTimelineEvent::create([
                    'bankruptcy_case_id' => $case->id,
                    'label' => $event['label'],
                    'day_offset' => $event['day_offset'],
                    'category' => $event['category'],
                    'sort_order' => $index,
                ]);
            }

            $this->log($actor, AuditEvent::CaseCreated, $case, $organization, ['title' => $title]);

            return $case->fresh();
        });
    }

    public function changeStatus(User $actor, BankruptcyCase $case, string $status): void
    {
        Gate::forUser($actor)->authorize('manage', $case);

        if (! in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('حالة قضية غير معروفة.');
        }

        DB::transaction(function () use ($actor, $case, $status) {
            $locked = BankruptcyCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $previousStatus = $locked->status;

            if ($previousStatus === $status) {
                return;
            }

            $locked->update([
                'status' => $status,
                'closed_at' => $status === 'closed' ? now()->toDateString() : null,
            ]);

            $this->log($actor, AuditEvent::CaseStatusChanged, $locked, $locked->organization, ['from' => $previousStatus, 'to' => $status]);
        });
    }

    public function addParty(User $actor, BankruptcyCase $case, array $data): CaseParty
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        if (trim($data['name'] ?? '') === '') {
            throw new InvalidArgumentException('اسم الطرف مطلوب.');
        }

        if (! in_array($data['role'] ?? null, self::VALID_PARTY_ROLES, true)) {
            throw new InvalidArgumentException('صفة الطرف غير معروفة.');
        }

        return DB::transaction(function () use ($actor, $case, $data) {
            $party = CaseParty::create([
                'bankruptcy_case_id' => $case->id,
                'name' => $data['name'],
                'role' => $data['role'],
                'identifier' => $data['identifier'] ?? null,
                'contact' => $data['contact'] ?? null,
                'notes' => $data['notes'] ?? null,
                'added_by_user_id' => $actor->id,
            ]);

            $this->log($actor, AuditEvent::CasePartyAdded, $party, $case->organization, ['case_id' => $case->id, 'name' => $party->name]);

            return $party;
        });
    }

    public function addProcedure(User $actor, BankruptcyCase $case, array $data): CaseProcedure
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        if (trim($data['title'] ?? '') === '') {
            throw new InvalidArgumentException('عنوان الإجراء مطلوب.');
        }

        return DB::transaction(function () use ($actor, $case, $data) {
            $procedure = CaseProcedure::create([
                'bankruptcy_case_id' => $case->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => 'pending',
                'due_date' => $data['due_date'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            $this->log($actor, AuditEvent::CaseProcedureAdded, $procedure, $case->organization, ['case_id' => $case->id, 'title' => $procedure->title]);

            return $procedure;
        });
    }

    public function updateProcedureStatus(User $actor, CaseProcedure $procedure, string $status): void
    {
        $case = $procedure->bankruptcyCase;
        Gate::forUser($actor)->authorize('contribute', $case);

        if (! in_array($status, self::VALID_PROCEDURE_STATUSES, true)) {
            throw new InvalidArgumentException('حالة إجراء غير معروفة.');
        }

        DB::transaction(function () use ($actor, $procedure, $status, $case) {
            $locked = CaseProcedure::whereKey($procedure->id)->lockForUpdate()->firstOrFail();
            $previousStatus = $locked->status;

            if ($previousStatus === $status) {
                return;
            }

            $locked->update([
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
            ]);

            $this->log($actor, AuditEvent::CaseProcedureStatusChanged, $locked, $case->organization, ['case_id' => $case->id, 'from' => $previousStatus, 'to' => $status]);
        });
    }

    public function addNote(User $actor, BankruptcyCase $case, string $body): CaseNote
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        if (trim($body) === '') {
            throw new InvalidArgumentException('نص الملاحظة مطلوب.');
        }

        return DB::transaction(function () use ($actor, $case, $body) {
            $note = CaseNote::create([
                'bankruptcy_case_id' => $case->id,
                'user_id' => $actor->id,
                'body' => $body,
            ]);

            $this->log($actor, AuditEvent::CaseNoteAdded, $note, $case->organization, ['case_id' => $case->id]);

            return $note;
        });
    }

    public function uploadDocument(User $actor, BankruptcyCase $case, UploadedFile $file, string $title): CaseDocument
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        return $this->storeDocument($actor, $case, $file, $title);
    }

    /** المرحلة 2 — نفس الفعل، لكن بصلاحية العميل (contributeAsClient) بدل صلاحية أعضاء المكتب. */
    public function uploadDocumentAsClient(User $client, BankruptcyCase $case, UploadedFile $file, string $title): CaseDocument
    {
        Gate::forUser($client)->authorize('contributeAsClient', $case);

        return $this->storeDocument($client, $case, $file, $title);
    }

    private function storeDocument(User $actor, BankruptcyCase $case, UploadedFile $file, string $title): CaseDocument
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException('عنوان المستند مطلوب.');
        }

        return DB::transaction(function () use ($actor, $case, $file, $title) {
            // قرص local خاص (لا public) — لا رابط مباشر، التنزيل يمر دائمًا عبر Controller مُتحقَّق منه.
            $path = $file->store('bankruptcy-cases/'.$case->id, 'local');

            $document = CaseDocument::create([
                'bankruptcy_case_id' => $case->id,
                'title' => $title,
                'original_filename' => $file->getClientOriginalName(),
                'disk' => 'local',
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by_user_id' => $actor->id,
            ]);

            $this->log($actor, AuditEvent::CaseDocumentUploaded, $document, $case->organization, ['case_id' => $case->id, 'filename' => $document->original_filename]);

            return $document;
        });
    }

    public function deleteDocument(User $actor, CaseDocument $document): void
    {
        $case = $document->bankruptcyCase;
        Gate::forUser($actor)->authorize('manage', $case);

        DB::transaction(function () use ($document) {
            Storage::disk($document->disk)->delete($document->path);
            $document->delete();
        });
    }

    public function addCreditor(User $actor, BankruptcyCase $case, array $data): CaseCreditor
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        if (trim($data['name'] ?? '') === '') {
            throw new InvalidArgumentException('اسم الدائن مطلوب.');
        }

        if (! in_array($data['priority'] ?? null, self::VALID_CREDITOR_PRIORITIES, true)) {
            throw new InvalidArgumentException('أولوية الدائن غير معروفة.');
        }

        if (! is_numeric($data['amount'] ?? null) || (float) $data['amount'] <= 0) {
            throw new InvalidArgumentException('مبلغ الدين يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($actor, $case, $data) {
            $creditor = CaseCreditor::create([
                'bankruptcy_case_id' => $case->id,
                'name' => $data['name'],
                'amount' => $data['amount'],
                'priority' => $data['priority'],
                'type' => $data['type'] ?? '',
                'date' => $data['date'] ?? now()->toDateString(),
                'contact' => $data['contact'] ?? null,
                'pledge_type' => $data['pledge_type'] ?? null,
                'pledge_registered' => $data['pledge_registered'] ?? null,
                'added_by_user_id' => $actor->id,
            ]);

            $this->log($actor, AuditEvent::CaseCreditorAdded, $creditor, $case->organization, ['case_id' => $case->id, 'name' => $creditor->name, 'amount' => $creditor->amount]);

            return $creditor;
        });
    }

    public function addAsset(User $actor, BankruptcyCase $case, array $data): CaseAsset
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        if (trim($data['name'] ?? '') === '') {
            throw new InvalidArgumentException('اسم الأصل مطلوب.');
        }

        if (! is_numeric($data['value'] ?? null) || (float) $data['value'] <= 0) {
            throw new InvalidArgumentException('قيمة الأصل يجب أن تكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($actor, $case, $data) {
            $asset = CaseAsset::create([
                'bankruptcy_case_id' => $case->id,
                'name' => $data['name'],
                'value' => $data['value'],
                'location' => $data['location'] ?? '',
                'description' => $data['description'] ?? null,
                'added_by_user_id' => $actor->id,
            ]);

            $this->log($actor, AuditEvent::CaseAssetAdded, $asset, $case->organization, ['case_id' => $case->id, 'name' => $asset->name, 'value' => $asset->value]);

            return $asset;
        });
    }

    public function addEmployee(User $actor, BankruptcyCase $case, array $data): CaseEmployee
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        if (trim($data['name'] ?? '') === '') {
            throw new InvalidArgumentException('اسم الموظف مطلوب.');
        }

        if (! is_numeric($data['salary'] ?? null) || (float) $data['salary'] <= 0) {
            throw new InvalidArgumentException('راتب الموظف يجب أن يكون أكبر من صفر.');
        }

        if (trim($data['join_date'] ?? '') === '') {
            throw new InvalidArgumentException('تاريخ الالتحاق مطلوب.');
        }

        return DB::transaction(function () use ($actor, $case, $data) {
            $employee = CaseEmployee::create([
                'bankruptcy_case_id' => $case->id,
                'name' => $data['name'],
                'nationality' => $data['nationality'] ?? '',
                'iqama' => $data['iqama'] ?? '',
                'salary' => $data['salary'],
                'join_date' => $data['join_date'],
                'added_by_user_id' => $actor->id,
            ]);

            $this->log($actor, AuditEvent::CaseEmployeeAdded, $employee, $case->organization, ['case_id' => $case->id, 'name' => $employee->name]);

            return $employee;
        });
    }

    public function addHearing(User $actor, BankruptcyCase $case, array $data): CaseHearing
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        if (trim($data['date'] ?? '') === '') {
            throw new InvalidArgumentException('تاريخ الجلسة مطلوب.');
        }

        if (! in_array($data['type'] ?? null, self::VALID_HEARING_TYPES, true)) {
            throw new InvalidArgumentException('نوع الجلسة غير معروف.');
        }

        return DB::transaction(function () use ($actor, $case, $data) {
            $hearing = CaseHearing::create([
                'bankruptcy_case_id' => $case->id,
                'date' => $data['date'],
                'type' => $data['type'],
                'notes' => $data['notes'] ?? null,
                'result' => $data['result'] ?? null,
                'added_by_user_id' => $actor->id,
            ]);

            $this->log($actor, AuditEvent::CaseHearingAdded, $hearing, $case->organization, ['case_id' => $case->id, 'date' => (string) $hearing->date]);

            return $hearing;
        });
    }

    public function toggleTimelineEvent(User $actor, CaseTimelineEvent $event): void
    {
        $case = $event->bankruptcyCase;
        Gate::forUser($actor)->authorize('contribute', $case);

        DB::transaction(function () use ($actor, $event, $case) {
            $locked = CaseTimelineEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $locked->update(['done' => ! $locked->done]);

            $this->log($actor, AuditEvent::CaseTimelineEventToggled, $locked, $case->organization, ['case_id' => $case->id, 'label' => $locked->label, 'done' => $locked->done]);
        });
    }

    /**
     * بيانات الملف الشخصي (المدين/الممثل/المحامي/المحكمة) + حقول توليد
     * المستندات (المرحلة 3: تاريخ/وقت المستند، بيانات الوكالة الشرعية).
     */
    public function updateDebtorProfile(User $actor, BankruptcyCase $case, array $data): void
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        DB::transaction(function () use ($actor, $case, $data) {
            $case->update(array_intersect_key($data, array_flip([
                'court_case_number', 'debtor_name', 'legal_form', 'cr_number', 'cr_city', 'court_city',
                'representative_name', 'representative_title', 'representative_id',
                'attorney_name', 'attorney_license', 'submission_date', 'trustee_name',
                'document_date', 'document_time', 'poa_number', 'poa_date', 'poa_city',
            ])));

            $this->log($actor, AuditEvent::CaseProfileUpdated, $case, $case->organization, ['case_id' => $case->id]);
        });
    }

    /**
     * المرحلة 3 — توقيع حقيقي مرسوم بـCanvas (لا علاقة بأي تحقق هوية
     * حكومي — لا بوابة Nafath وهمية هنا عمدًا). تحقق أساسي على شكل/حجم
     * الـdata URL قبل الحفظ (منع إساءة استخدام الحقل كمخزن ملفات عشوائي).
     */
    public function saveSignature(User $actor, BankruptcyCase $case, string $role, string $dataUrl): void
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        if (! in_array($role, ['lawyer', 'representative'], true)) {
            throw new InvalidArgumentException('دور توقيع غير معروف.');
        }

        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw new InvalidArgumentException('صيغة التوقيع غير صالحة.');
        }

        if (strlen($dataUrl) > 700_000) {
            throw new InvalidArgumentException('حجم التوقيع كبير جدًا.');
        }

        $column = $role === 'lawyer' ? 'lawyer_signature_data' : 'representative_signature_data';

        DB::transaction(function () use ($actor, $case, $column, $dataUrl, $role) {
            $case->update([$column => $dataUrl]);
            $this->log($actor, AuditEvent::CaseSignatureSaved, $case, $case->organization, ['case_id' => $case->id, 'role' => $role]);
        });
    }

    /** إجابات معالج التشخيص العشرة — تغذّي BankruptcyRecommendationEngine مباشرة. */
    public function updateWizardAnswers(User $actor, BankruptcyCase $case, array $data): void
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        if (isset($data['insolvency_status']) && ! in_array($data['insolvency_status'], ['actual', 'upcoming'], true)) {
            throw new InvalidArgumentException('حالة الإعسار غير معروفة.');
        }

        if (isset($data['is_establishment']) && ! in_array($data['is_establishment'], ['company', 'individual'], true)) {
            throw new InvalidArgumentException('نوع المنشأة غير معروف.');
        }

        foreach (self::VALID_WIZARD_YES_NO_FIELDS as $field) {
            if (isset($data[$field]) && ! in_array($data[$field], ['yes', 'no'], true)) {
                throw new InvalidArgumentException('إجابة غير معروفة على أحد أسئلة المعالج.');
            }
        }

        DB::transaction(function () use ($actor, $case, $data) {
            $case->update(array_intersect_key($data, array_flip([
                'is_establishment', 'is_active', 'has_assets', 'assets_cover_expenses', 'insolvency_status',
                'financial_statements_available', 'financial_transactions_available', 'creditors_notified',
                'operated_twelve_months', 'previous_settlement',
            ])));

            $this->log($actor, AuditEvent::CaseWizardAnswersUpdated, $case, $case->organization, ['case_id' => $case->id]);
        });
    }

    /** القوائم التنظيمية (ZATCA/GOSI/HR) + علما الجهات المستقلَّين. */
    public function updateChecklists(User $actor, BankruptcyCase $case, array $data): void
    {
        Gate::forUser($actor)->authorize('contribute', $case);

        DB::transaction(function () use ($actor, $case, $data) {
            $case->update(array_intersect_key($data, array_flip([
                'zatca_file_number', 'zatca_checklist', 'gosi_file_number', 'gosi_checklist', 'hr_checklist',
                'commerce_cr_cancellation_requested', 'sama_notified',
            ])));

            $this->log($actor, AuditEvent::CaseChecklistsUpdated, $case, $case->organization, ['case_id' => $case->id]);
        });
    }

    /**
     * المرحلة 2 — دعوة عميل (المدين) للقضية. حساب User حقيقي جديد بكلمة
     * مرور عشوائية غير قابلة للاستخدام؛ التفعيل عبر نفس رابط "نسيت كلمة
     * المرور" الموجود فعليًا (Password::sendResetLink) — لا آلية Token جديدة.
     */
    public function inviteClient(User $actor, BankruptcyCase $case, string $name, string $email): User
    {
        Gate::forUser($actor)->authorize('manage', $case);

        if (trim($name) === '') {
            throw new InvalidArgumentException('اسم العميل مطلوب.');
        }

        if ($case->client_user_id !== null) {
            throw new InvalidArgumentException('هذي القضية عندها عميل مدعو بالفعل.');
        }

        if (User::where('email', $email)->exists()) {
            throw new InvalidArgumentException('هذا البريد مسجَّل مسبقًا بالمنصة.');
        }

        return DB::transaction(function () use ($actor, $case, $name, $email) {
            $client = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ]);

            $case->update(['client_user_id' => $client->id, 'client_access_revoked_at' => null]);

            Password::sendResetLink(['email' => $email]);

            $this->log($actor, AuditEvent::CaseClientInvited, $case, $case->organization, ['case_id' => $case->id, 'client_email' => $email]);

            return $client;
        });
    }

    public function revokeClientAccess(User $actor, BankruptcyCase $case): void
    {
        Gate::forUser($actor)->authorize('manage', $case);

        if ($case->client_user_id === null) {
            throw new InvalidArgumentException('لا يوجد عميل مدعو بهذي القضية.');
        }

        DB::transaction(function () use ($actor, $case) {
            $case->update(['client_access_revoked_at' => now()]);
            $this->log($actor, AuditEvent::CaseClientAccessRevoked, $case, $case->organization, ['case_id' => $case->id]);
        });
    }

    public function restoreClientAccess(User $actor, BankruptcyCase $case): void
    {
        Gate::forUser($actor)->authorize('manage', $case);

        if ($case->client_user_id === null) {
            throw new InvalidArgumentException('لا يوجد عميل مدعو بهذي القضية.');
        }

        DB::transaction(function () use ($actor, $case) {
            $case->update(['client_access_revoked_at' => null]);
            $this->log($actor, AuditEvent::CaseClientAccessRestored, $case, $case->organization, ['case_id' => $case->id]);
        });
    }

    /**
     * حذف نهائي — أُبلِغ عن غيابه كخلل حقيقي (لا خيار حذف بصفحة القضايا
     * إطلاقًا). أضيق صلاحية (`manage`) لأنه فعل غير قابل للتراجع. تُحذَف
     * الملفات الفعلية من التخزين أولًا (وإلا تبقى يتيمة على القرص للأبد بعد
     * حذف صف DB)، ثم القضية نفسها — كل الجداول التابعة (دائنون/أصول/موظفون/
     * جلسات/جدول زمني/أطراف/إجراءات/ملاحظات/مستندات) تُحذَف تلقائيًا
     * (cascadeOnDelete بكل migration). حساب العميل المدعو (لو وُجد) لا يُحذَف
     * (FK client_user_id على bankruptcy_cases، nullOnDelete بالاتجاه المعاكس
     * فقط) — يبقى حسابًا حقيقيًا بالمنصة، فقط يفقد ربطه بقضية محذوفة.
     */
    public function deleteCase(User $actor, BankruptcyCase $case): void
    {
        Gate::forUser($actor)->authorize('manage', $case);

        DB::transaction(function () use ($actor, $case) {
            $this->log($actor, AuditEvent::CaseDeleted, $case, $case->organization, ['case_id' => $case->id, 'title' => $case->title]);

            foreach ($case->documents as $document) {
                Storage::disk($document->disk)->delete($document->path);
            }

            $case->delete();
        });
    }

    private function log(User $actor, AuditEvent $event, $subject, ?Organization $organization, array $metadata = []): void
    {
        AuditLog::create([
            'organization_id' => $organization?->id,
            'actor_user_id' => $actor->id,
            'event' => $event->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id,
            'metadata' => $metadata,
        ]);
    }
}
