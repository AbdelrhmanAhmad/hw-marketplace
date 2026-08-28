<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Phase OL — نقطة الدخول الوحيدة لأرشفة/استعادة مؤسسة. راجع
 * docs/phase-ol-implementation-specification.md.
 *
 * Hard Delete أُلغي نهائيًا من الـDomain — Archive هو المسار الوحيد. الوصول
 * الفعلي بعد الأرشفة يُحسَم بالكامل عبر EntitlementResolver (بلا أي تعديل
 * عليه) لأن كل Subscription نشط يُلغى صراحة هنا — لا Query جديدة مكرِّرة.
 */
class OrganizationLifecycleService
{
    public function __construct(private readonly OrganizationSubscriptionService $subscriptions)
    {
    }

    public function archive(User $actor, Organization $organization): void
    {
        Gate::forUser($actor)->authorize('archive', $organization);

        DB::transaction(function () use ($actor, $organization) {
            $locked = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'archived') {
                return;
            }

            foreach ($locked->marketplaceSubscriptions()->where('status', 'active')->get() as $subscription) {
                $this->subscriptions->cancel($actor, $subscription);
            }

            $locked->update(['status' => 'archived']);

            $this->log($actor, AuditEvent::OrganizationArchived, $locked);
        });
    }

    public function restore(User $actor, Organization $organization): void
    {
        Gate::forUser($actor)->authorize('restore', $organization);

        DB::transaction(function () use ($actor, $organization) {
            $locked = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'active') {
                return;
            }

            // عمدًا لا خطوة أخرى هنا — لا إعادة تفعيل لأي Subscription/Access
            // تلقائيًا (القرار المعتمَد، يطابق روح AD-014 بسياق جديد).
            $locked->update(['status' => 'active']);

            $this->log($actor, AuditEvent::OrganizationRestored, $locked);
        });
    }

    private function log(User $actor, AuditEvent $event, Organization $organization): void
    {
        AuditLog::create([
            'organization_id' => $organization->id,
            'actor_user_id' => $actor->id,
            'event' => $event->value,
            'subject_type' => Organization::class,
            'subject_id' => $organization->id,
            'metadata' => null,
        ]);
    }
}
