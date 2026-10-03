<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Phase OI — نقطة الدخول الوحيدة لتغيير/حذف Membership، ونقل الملكية.
 * راجع docs/phase-oi-owner-integrity-implementation-specification.md.
 *
 * Security Hardening Pass (بعد Platform Authorization Foundation Finding #1)
 * — add() هي نقطة الدخول الوحيدة أيضًا لإنشاء Membership جديدة. لا
 * Membership::create() مباشر من Filament بعد الآن (كان الفجوة الحرجة
 * المكتشَفة: CreateAction بلا using() يتجاوز هذا الملف بالكامل). راجع
 * docs/platform-authorization-hardening-completion-report.md.
 *
 * Last Owner Rule: لا يجوز حذف أو تخفيض آخر Owner بمؤسسة إلا ضمن
 * transferOwnership() الذرية — لا فعل مستقل حتى لو النية تعيين بديل لاحقًا.
 * كل تابع يعيد التحقق من Authorization داخليًا (لا يثق باستدعاء خارجي) —
 * هذا هو الضمان الوحيد الذي يمنع أي Filament CRUD مباشر من تجاوز القاعدة.
 */
class MembershipService
{
    /**
     * من يملك حق إنشاء Membership؟ Owner/Admin/Staff (manageMembers) — نفس
     * حد الثقة المُستخدَم لتعديل/حذف عضوية غير-Owner.
     *
     * من يملك حق إنشاء Membership بدور Owner تحديدًا؟ راجع
     * authorizeGrantingOwnership() — القاعدة مصمَّمة تحديدًا لمنع نمط:
     * "Staff يمنح نفسه Owner بمؤسسة مُدارة فعليًا ← يُسحَب منه Staff ← يبقى
     * Owner دائم" (السيناريو المحوري بـFinding #1). Last Owner Rule لا
     * تنطبق على الإنشاء (الإضافة لا تُنقِص عدد الـOwners أبدًا — مُطبَّقة
     * فقط بـremove()/changeRole() عند التخفيض/الحذف).
     */
    public function add(User $actor, Organization $organization, User $target, MembershipRole $role): Membership
    {
        if ($role === MembershipRole::Owner) {
            $this->authorizeGrantingOwnership($actor, $organization);
        } else {
            Gate::forUser($actor)->authorize('manageMembers', $organization);
        }

        if (Membership::where('user_id', $target->id)->where('organization_id', $organization->id)->exists()) {
            throw new InvalidArgumentException('هذا المستخدم عضو بالفعل بهذي المؤسسة.');
        }

        return DB::transaction(function () use ($actor, $organization, $target, $role) {
            $membership = Membership::create([
                'user_id' => $target->id,
                'organization_id' => $organization->id,
                'role' => $role,
            ]);

            AuditLog::create([
                'organization_id' => $organization->id,
                'actor_user_id' => $actor->id,
                'event' => AuditEvent::MembershipCreated->value,
                'subject_type' => Membership::class,
                'subject_id' => $membership->id,
                'metadata' => ['role' => $role->value, 'target_user_id' => $target->id],
            ]);

            return $membership;
        });
    }

    public function changeRole(User $actor, Membership $membership, MembershipRole $newRole): void
    {
        // ترقية عضو غير-Owner إلى Owner لها نفس خطورة إنشاء Membership
        // بدور Owner مباشرة — نفس القاعدة (authorizeGrantingOwnership)،
        // لا manageMembers العادية (كانت ثغرة شقيقة لـFinding #1 لم
        // تُكتشَف بالمراجعة الأولى إلا عبر اختبار السيناريو الصريح).
        if ($newRole === MembershipRole::Owner && $membership->role !== MembershipRole::Owner) {
            $this->authorizeGrantingOwnership($actor, $membership->organization);
        } else {
            Gate::forUser($actor)->authorize('manageMembers', $membership->organization);
        }

        DB::transaction(function () use ($actor, $membership, $newRole) {
            Organization::whereKey($membership->organization_id)->lockForUpdate()->firstOrFail();

            $locked = Membership::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $previousRole = $locked->role;

            $isPromotionToOwner = $locked->role !== MembershipRole::Owner && $newRole === MembershipRole::Owner;

            if ($locked->role === MembershipRole::Owner && $newRole !== MembershipRole::Owner) {
                $this->assertNotLastOwner($locked->organization_id);
            }

            $locked->update(['role' => $newRole]);

            if ($isPromotionToOwner) {
                AuditLog::create([
                    'organization_id' => $locked->organization_id,
                    'actor_user_id' => $actor->id,
                    'event' => AuditEvent::OwnershipGranted->value,
                    'subject_type' => Membership::class,
                    'subject_id' => $locked->id,
                    'metadata' => ['target_user_id' => $locked->user_id, 'via' => 'change_role'],
                ]);
            }

            // AD-016 — كل تغيير Role (لا فقط الترقية لـOwner) أصبح مُدقَّقًا الآن.
            AuditLog::create([
                'organization_id' => $locked->organization_id,
                'actor_user_id' => $actor->id,
                'event' => AuditEvent::MembershipRoleChanged->value,
                'subject_type' => Membership::class,
                'subject_id' => $locked->id,
                'metadata' => ['target_user_id' => $locked->user_id, 'from' => $previousRole->value, 'to' => $newRole->value],
            ]);
        });
    }

    public function remove(User $actor, Membership $membership): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $membership->organization);

        DB::transaction(function () use ($actor, $membership) {
            Organization::whereKey($membership->organization_id)->lockForUpdate()->firstOrFail();

            $locked = Membership::whereKey($membership->id)->lockForUpdate()->first();

            if (! $locked) {
                return;
            }

            if ($locked->role === MembershipRole::Owner) {
                $this->assertNotLastOwner($locked->organization_id);
            }

            // AD-016 — يُسجَّل قبل الحذف (السجل يبقى بلا حاجة FK على صف مُزال).
            AuditLog::create([
                'organization_id' => $locked->organization_id,
                'actor_user_id' => $actor->id,
                'event' => AuditEvent::MembershipRemoved->value,
                'subject_type' => Membership::class,
                'subject_id' => $locked->id,
                'metadata' => ['target_user_id' => $locked->user_id, 'role' => $locked->role->value],
            ]);

            $locked->delete();
        });
    }

    public function transferOwnership(
        User $actor,
        Membership $from,
        Membership $to,
        MembershipRole $demoteFromTo = MembershipRole::Admin
    ): void {
        if ($from->organization_id !== $to->organization_id) {
            throw new InvalidArgumentException('لا يمكن نقل الملكية بين مؤسستين مختلفتين.');
        }

        if ($demoteFromTo === MembershipRole::Owner) {
            throw new InvalidArgumentException('الدور الجديد لصاحب الملكية القديم يجب ألا يكون Owner.');
        }

        // AD-017 — نفس authorizeGrantingOwnership() المستخدَمة بـadd()/changeRole()،
        // لا Gate::authorize('transferOwnership', ...) المباشرة بعد الآن. بما إن
        // $from مطلوب يكون Owner أصلًا (شرط مسبق أعلاه)، المؤسسة هنا دائمًا لها
        // Owner حقيقي — يعني الفرع الوحيد القابل للتحقق فعليًا هو: الفاعل نفسه
        // Owner حقيقي بنفس المؤسسة. Platform Staff بلا استثناء (Finding H1، Security
        // Review #2) — راجع docs/ownership-transfer-security-hardening-design.md.
        $this->authorizeGrantingOwnership($actor, $from->organization);

        DB::transaction(function () use ($actor, $from, $to, $demoteFromTo) {
            Organization::whereKey($from->organization_id)->lockForUpdate()->firstOrFail();

            $lockedFrom = Membership::whereKey($from->id)->lockForUpdate()->firstOrFail();
            $lockedTo = Membership::whereKey($to->id)->lockForUpdate()->firstOrFail();

            $lockedTo->update(['role' => MembershipRole::Owner]);
            $lockedFrom->update(['role' => $demoteFromTo]);

            // AD-016 — نقل الملكية لم يكن مُدقَّقًا إطلاقًا من قبل، رغم كونه
            // أخطر فعل بهذا الملف بأكمله.
            AuditLog::create([
                'organization_id' => $lockedFrom->organization_id,
                'actor_user_id' => $actor->id,
                'event' => AuditEvent::OwnershipTransferred->value,
                'subject_type' => Membership::class,
                'subject_id' => $lockedTo->id,
                'metadata' => [
                    'from_user_id' => $lockedFrom->user_id,
                    'to_user_id' => $lockedTo->user_id,
                    'from_new_role' => $demoteFromTo->value,
                ],
            ]);
        });
    }

    /**
     * منح Owner (إنشاءً أو ترقيةً) يتطلب أحد مسارين فقط، لا Staff بمفرده
     * دائمًا:
     *
     * (أ) المؤسسة بلا Owner فعلي إطلاقًا — تأسيس أول Owner، Owner/Staff
     *     مسموحان (نفس منطق transferOwnership) — يخدم تحديدًا سيناريو
     *     "مؤسسة يتيمة" اللي Option D صُمِّم لحله (راجع Attack #5 بالمراجعة
     *     الأمنية الأولى).
     * (ب) المؤسسة لها Owner فعلي بالفعل — الفاعل نفسه يجب يكون Owner حقيقي
     *     بها بالفعل. Platform Staff بمفرده (بلا Membership حقيقية) لا
     *     يكفي هنا — هذا تحديدًا ما يمنع نمط "Staff يمنح نفسه Owner
     *     بمؤسسة مُدارة فعليًا ثم يُسحَب منه Staff ويبقى Owner دائم".
     */
    private function authorizeGrantingOwnership(User $actor, Organization $organization): void
    {
        $organizationHasRealOwner = Membership::where('organization_id', $organization->id)
            ->where('role', MembershipRole::Owner)
            ->exists();

        if (! $organizationHasRealOwner) {
            Gate::forUser($actor)->authorize('transferOwnership', $organization);

            return;
        }

        $actorIsRealOwner = Membership::where('user_id', $actor->id)
            ->where('organization_id', $organization->id)
            ->where('role', MembershipRole::Owner)
            ->exists();

        if (! $actorIsRealOwner) {
            throw new AuthorizationException(
                'منح صلاحية Owner لمؤسسة تملك Owner فعلي بالفعل يتطلب أن يكون الفاعل Owner حقيقيًا بها — صلاحية Platform Staff وحدها لا تكفي هنا.'
            );
        }
    }

    /**
     * يُستدعى داخل معاملة تملك بالفعل قفل صف Organization — العدّ هنا يعكس
     * الحالة الحقيقية اللحظية، لا نسخة قديمة (AD-003 نفس المبدأ).
     */
    private function assertNotLastOwner(int $organizationId): void
    {
        $ownerCount = Membership::where('organization_id', $organizationId)
            ->where('role', MembershipRole::Owner)
            ->count();

        if ($ownerCount <= 1) {
            throw new InvalidArgumentException(
                'هذا آخر Owner بالمؤسسة — لا يمكن حذفه أو تخفيضه مباشرة. استخدم نقل الملكية لتعيين Owner بديل أولًا.'
            );
        }
    }
}
