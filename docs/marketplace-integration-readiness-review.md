# Marketplace Integration Readiness Review — Pre-L2

**Status:** Audit and readiness assessment only. **No code, no file, no Header, no Dashboard, and no existing Hukm w Rakam functionality was modified to produce this document.** Nothing was fixed. L2 was not started.
**Method:** Every claim below is grounded in the actual current codebase (grep/read of real files) and the actual current database state, not memory or prior conversation summaries. Where a doc and the code disagreed, the code is treated as ground truth and the drift is logged explicitly.
**Governing constraint verified throughout:** **AD-015 — Marketplace Integrates Into Hukm w Rakam, Not The Reverse.** *"Hukm w Rakam is an existing, live production platform. The Marketplace is an additive capability integrated into Hukm w Rakam. It is not a replacement, rebrand, redesign, or architectural takeover of the existing Core Platform. No existing Core Platform behavior, navigation, identity, or user flow may be changed unless that change is explicitly approved as part of a separate scope."*

---

## Executive Readiness Verdict

# NOT READY FOR L2

Not because L1 failed — L1 is solid, verified, and approved. L2 is blocked by three independent gates, none of which are large, all of which are concrete and pre-existing (not created by this review):

1. **No L2 Safe Migration spec exists yet.** Every prior implementation phase in this project (1a, 1b, 2A, 2B) was gated by its own written spec before code. L2 has none. `legacy-subscription-l1-spec.md` §E (Migration Eligibility Matrix) is analysis, not a spec — it does not define transaction boundaries, idempotency guarantees, dry-run mode, or a concrete rollback procedure for L2 itself.
2. **A live, loaded, unpatched risk sits in the codebase today, independent of L2.** `app/Console/Commands/MarketplaceBackfillFreeAccess.php` still contains the exact pattern AD-014 prohibits (`active()->exists()` instead of `exists()`, §Blocking Issues #1). Anyone with CLI access can run `php artisan marketplace:backfill-free-access` right now and reproduce the exact reactivation bug already found once. This is not an L2 risk — it is a **present-tense** risk that predates and is independent of this review, and it must be closed (by a real spec-driven fix, not a quick patch — matching your own standing instruction) before L2 introduces any new migration tooling near it.
3. **No Parity Check tooling exists for the new system, unlike Phase 1a.** Phase 1a shipped with `MarketplaceCatalogParityCheck` as a first-class verification command before cutover. No equivalent exists for subscription/access parity — L2 would be flying without the same instrument that made 1a's 100% parity claim verifiable.

None of these are architectural flaws. All three are closable by writing one document and one small tool addition — exactly the shape of gate this project has cleared five times already.

---

## Topic-by-Topic Review

| Area | Finding | Status |
|---|---|---|
| **Core Platform (existing)** | `organizations`/`memberships`/profile/bookmarks unchanged by any Marketplace work to date. `AppSubscription`/`FreeAppProvisioner` class untouched (only 3 call sites removed in L1). | ✅ Intact |
| **Marketplace architecture** | Compatibility Layer (1a), Subscription/AccessAssignment (1b), Org Context (2A), Seats (2B) all present, tested, internally consistent with their own AD decisions. | ✅ Sound |
| **Authentication** | `RegisteredUserController`, `AuthenticatedSessionController` — stock Laravel Breeze, zero custom logic beyond the now-removed `FreeAppProvisioner::ensure()` calls. No Marketplace-specific auth logic exists anywhere in the auth flow. | ✅ Clean |
| **Organization Context** | `ActiveOrganizationContext::current()` re-queries `Membership` live on every call (no caching) — genuinely satisfies AD-012's "pointer, not source of truth" on every read path checked. | ✅ Correct |
| **Memberships** | `Membership::booted()` fires `MembershipRevoked` on delete only — additive, no existing behavior altered. | ✅ Clean |
| **Subscriptions / AccessAssignments / SubscriptionSeats** | All three enforce creation exclusively through their Service (`SubscriptionService`/`OrganizationSubscriptionService`/`SeatService`) — confirmed via grep, no direct `::create()` outside those three services. | ✅ Enforced |
| **EntitlementResolver** | Single decision point confirmed (AD-013) — `MyAppsController` and `marketplace-show.blade.php`'s CTA both route through it, no parallel re-implementation found in this pass. | ✅ Single source |
| **Authorization / Policies** | `OrganizationPolicy` (only Policy in the project) re-queries `Membership` directly per call, ignores session context entirely — matches AD-012 to the letter. | ✅ Correct |
| **Dashboard** | Still reads *only* `app_subscriptions` (Legacy) via `$user->subscriptions()->active()` — confirmed unchanged post-L1 except the two deleted lines. Does not read `EntitlementResolver`/`subscriptions` at all today. | ⚠️ See Dashboard Integration Prerequisites |
| **Header / Navigation** | **Two independent, inconsistent navigation systems exist today** — see Architecture Risks #1. Pre-existing, not caused by L1 or this review. | ⚠️ See Header Integration Prerequisites |
| **Legacy `app_subscriptions`** | 4 rows, untouched, still readable by Dashboard and Filament's `AppSubscriptionResource` (manual admin writes still possible, by design, AD-006). | ✅ As designed |
| **L1 implementation** | Verified again in this pass: 0 new legacy writes across a fresh register→dashboard→marketplace→my-apps cycle (see DB snapshot below), 109/109 tests still passing. | ✅ Confirmed stable |
| **L2 migration readiness** | No spec document. Eligibility Matrix exists (analysis only) but no transaction design, no dry-run mode, no parity tool. | ❌ Not ready |
| **Database integrity** | FKs present on every relevant table; `subscriptions`/`access_assignments`/`subscription_seats` all carry the correct `UNIQUE` constraints for their invariants. One documented, accepted gap: `subscriber_type` has no DB-level `CHECK` (relies on `enforceMorphMap` + Service-only writes) — this is AD-002 point 1's known, already-accepted residual risk, not a new finding. | ✅ Sound (with one pre-accepted gap) |
| **Tenant isolation** | `EntitlementResolver`'s organization branch depends on the caller passing a freshly-resolved `ActiveOrganizationContext::current()` — every current caller (`MyAppsController`) does this correctly. Risk noted below (Non-blocking). | ✅ Correct today |
| **Security** | No new attack surface found beyond what `marketplace-access-control-audit.md` already documented. No regression from L1 (L1 removed code, added none). | ✅ Unchanged |
| **Audit trail** | Append-only enforcement (`update()`/`delete()` throw) verified still in place. `AuditLog` model docblock says it's written "حصرًا من SubscriptionService" — **inaccurate**, `OrganizationSubscriptionService` and `SeatService` also write to it (grep-confirmed). Doc drift, not a behavioral bug. | ⚠️ Non-blocking doc drift |
| **UX consistency** | Two disconnected header systems (see above) mean a user's available actions (logout, profile, org switch) differ depending on which page they're on. Pre-existing. | ⚠️ See below |
| **Regression risk** | Full suite green (109/109, 305 assertions). No test was weakened to pass — confirmed by re-reading the two tests changed in L1 (§Test Requirements below). | ✅ Low |
| **Test coverage** | 49 Marketplace tests, 27 Organization, 18 Auth, 8 Org Context = 102 domain-specific tests + 7 framework scaffolding tests. No test exists yet for L2 (correctly — L2 doesn't exist). | ✅ Adequate for current scope |
| **Rollback capability** | L1: verified trivial (§ below). 2B/2A: documented in their own completion reports. No rollback plan exists for L2 because L2 has no spec yet — this is expected, not a gap. | ✅ For what exists |

---

## Blocking Issues (must close before L2)

### 1. `MarketplaceBackfillFreeAccess` still contains the AD-014-violating pattern — live in the codebase today
**File:** `app/Console/Commands/MarketplaceBackfillFreeAccess.php:37-40`
```php
$alreadyMigrated = $user->marketplaceSubscriptions()
    ->where('marketplace_item_id', $item->id)
    ->active()          // ← checks "currently active", not "exists in any state"
    ->exists();
```
This is the exact bug empirically confirmed to reactivate a user's deliberately-cancelled subscription (`legacy-subscription-closure-plan.md` §4). The command was never deleted, patched, or guarded — it is still registered (`marketplace:backfill-free-access`), still callable by anyone with shell/deploy access, today, independent of L2's authorization status. **This is not new — it is the same risk already found, still sitting unresolved.** Per your own standing rule, this cannot be closed with a one-line patch; it needs the same treatment as any other spec-gated fix (a short L2-adjacent spec that also retires or corrects this specific command as its first concrete deliverable).

### 2. No L2 Safe Migration specification exists
Every implemented phase to date (1a/1b/2A/2B) had its own spec document reviewed and approved before code. `legacy-subscription-l1-spec.md` §E gives *principles* (Eligibility Matrix) but not an executable design: no transaction scope, no idempotency contract beyond "check `exists()`", no dry-run/preview mode, no defined success criteria beyond "Parity = 0 gap excluding intentional cancellations." Writing this spec is a prerequisite, not an implementation.

### 3. No Parity Check tooling for the post-L2 state
Phase 1a shipped `php artisan marketplace:catalog-parity-check` as a hard verification gate before the catalog cutover was trusted. Nothing equivalent exists for subscriptions/access. Without it, "100% migrated, 0 residual gap" (the stated L2 success criterion) has no way to be verified other than manual counting — not acceptable for a migration governed by AD-014's "never silently reactivate" rule.

---

## Non-Blocking Issues

1. **`AuditLog` model docblock inaccuracy** — says written "حصرًا من SubscriptionService"; actually three services write to it. Cosmetic, does not affect behavior, but should be corrected the next time that file is touched for any other reason.
2. **`DashboardController`/`MyAppsController` logic duplication risk (soft)** — not yet a duplication (Dashboard doesn't touch `EntitlementResolver` at all today), but flagged in `dashboard-marketplace-transition-decision.md` as the exact trap to avoid when Unified Home is eventually built. No action needed now.
3. **`SubscriptionSeat::scopeActive()` uses `status = 'assigned'`** while `Subscription`/`AccessAssignment` use `status = 'active'` — intentional per AD-008 (different vocabulary for a genuinely different concept), but worth a one-line comment for future readers who might assume all four "active" scopes mean the same status string. Cosmetic only.

---

## Security Risks

**None new.** This review re-verified (not re-litigated) the isolation model already covered by `marketplace-access-control-audit.md`: `OrganizationPolicy` re-checks `Membership` per call, `ActiveOrganizationContext` re-checks per call, `EntitlementResolver`'s org branch depends on correctly-scoped callers (verified true for the one real caller today, `MyAppsController`). L1 removed code and added none — it cannot have introduced a new attack surface, and inspection confirms it didn't.

**One thing to carry into L2 specifically:** any new L2 migration command will, by definition, run with elevated trust (it creates records on behalf of users who aren't in the request cycle). It must go through `SubscriptionService`/`OrganizationSubscriptionService` like every other writer (BR-013) — never a direct `Subscription::create()` — and must write an `AuditLog` entry with a distinguishable actor/source per migrated record (per `legacy-subscription-l1-spec.md` §D's "second line of defense" recommendation), so a future audit can tell "user-initiated" from "migration-initiated" activations apart.

---

## Data Migration Risks

- **The reactivation risk (AD-014)** is the headline risk and is fully understood, matrixed (`legacy-subscription-l1-spec.md` §E), and governed by a Domain Rule — but understanding it is not the same as having tooling that enforces it. See Blocking Issue #1.
- **No dry-run capability exists for any future migration command** — every prior one-off command in this codebase (`MarketplaceBackfillFreeAccess`, `MarketplaceCatalogSeeder`) runs directly against the database with no preview mode. An L2 spec should require one, given the stakes AD-014 established.
- **Row count is small today (4 legacy, 5 new, 3 "legacy-only" per closure plan §4)** — confirmed still true in this pass. Re-stating the standing caution already on record: this is dev data, not a basis for estimating real hw.sa migration scope, only for validating the migration *logic*.

---

## UX / Integration Risks

### 1. Two independent, inconsistent header/navigation systems exist today (pre-existing, not caused by this work)
Confirmed by direct inspection of both layout files:

| | `layouts/app.blade.php` (→ `layouts/navigation.blade.php`) | `layouts/platform.blade.php` |
|---|---|---|
| **Used by** | `/marefa`, `/laws`, `/laws/{id}`, `/updates`, `/bookmarks`, `/profile` | `/`, `/dashboard`, `/marketplace`, `/marketplace/{key}`, `/my/apps`, `/organizations/{id}/seats` |
| **Branding** | Logo carries subtitle "بوابة معرفة" | Logo alone, platform-level |
| **Nav links** | الرئيسية / الأنظمة / آخر التحديثات / الحاسبات / المفضلة | (none — no persistent link to Marketplace itself, even while on a Marketplace page) |
| **Auth actions** | Profile + Logout dropdown | **No profile/logout control at all** — only org switcher + "لوحتي" + a single CTA button to `/marefa` |
| **Org switcher** | Absent | Present (`@auth`, if user has organizations) |

**Concrete consequence:** a user on `/dashboard` or `/marketplace` today has no way to log out or reach their profile without first clicking through to `/marefa`. This predates L1 and this review entirely (it originates from Phase 1's Core Platform work layering a new `platform.blade.php` shell alongside the pre-existing بوابة معرفة `app.blade.php`/`navigation.blade.php`, without reconciling the two). **Per AD-015, this review does not fix it** — fixing it would itself be a Header/Navigation change requiring separate explicit authorization. It is logged here specifically because any future "add Marketplace to the Header" work will run directly into this split and must decide, consciously, which nav is the one being extended (or whether unifying the two nav systems is itself a prerequisite) — see Header Integration Prerequisites below.

### 2. Dashboard and My Apps show different content, with no cross-link
A user with an active Marketplace subscription (new system) sees it on `/my/apps` but never on `/dashboard`, and there is no link from Dashboard to My Apps anywhere in `dashboard.blade.php` today. Already known and intentionally accepted as the current state (Path B), not a new finding — restated here because it's directly relevant to L2 sequencing (fixing this is Dashboard Integration work, not L2).

---

## Architecture Risks

- **None found that weren't already known and governed by an existing AD.** This pass specifically looked for places where Marketplace code could be silently reaching into Core behavior (per your AD-015 instruction) — found none. The dual-header issue above is the inverse case (Core's own pre-existing inconsistency, not Marketplace overreach) and is called out precisely because it's the kind of thing an eventual integration could mistake for "ours to fix" without a separate authorization.
- **`subscriber_type` has no DB-level `CHECK` constraint** (AD-002 point 1) — already accepted as sufficient given `enforceMorphMap()` + Service-only writes; re-verified still true and still the only enforcement layer. Not a new risk, flagged for completeness only.

---

## Legacy Risks

- **`MarketplaceBackfillFreeAccess` — see Blocking Issue #1.** This is the only live legacy risk found. Everything else legacy-related (`app_subscriptions` table, `FreeAppProvisioner` class, `AppSubscriptionResource`) is inert or working exactly as designed (AD-006).

---

## Exact Prerequisites for L2

1. Write `docs/legacy-subscription-l2-safe-migration-spec.md` (design only, same gate as every prior phase) covering: transaction boundaries, idempotency contract, dry-run mode, and the exact `exists()`-based check from AD-014 as executable pseudocode, not prose.
2. That spec's first concrete deliverable must be resolving `MarketplaceBackfillFreeAccess` (Blocking Issue #1) — either retiring the command entirely in favor of the new L2 tooling, or rewriting it to the corrected check, decided in the spec, not improvised in code.
3. Design a Parity Check command (mirroring `MarketplaceCatalogParityCheck` from Phase 1a) as part of that same spec, not as an afterthought after migration runs.
4. Explicit test scenarios required before any L2 code: cancelled-stays-cancelled (the AD-014 regression test), idempotent re-run (run twice, same result), and a dry-run assertion (no writes occur in preview mode).

## Exact Prerequisites for Eventual Header Integration

1. **Decide what to do about the two-navigation split first** — this is a Core Platform decision (which nav system is authoritative, or whether both persist for different contexts intentionally), not a Marketplace decision, and must be authorized as its own separate scope per AD-015.
2. Any "Marketplace" entry added to whichever header is chosen must link to `/marketplace` (existing route, unchanged) — no new route needed.
3. The org switcher currently only in `platform.blade.php` needs an explicit decision on whether it also belongs in the بوابة معرفة nav, or stays platform-only — currently undecided, not yet a problem because Marketplace isn't referenced from that nav at all today.

## Exact Prerequisites for Eventual Dashboard Integration

1. Per `dashboard-marketplace-transition-decision.md` (already approved as direction): implement the shared Marketplace-section data source (`DashboardController` consuming the same method/service `MyAppsController` uses) — never a parallel query, per AD-013.
2. Decide (at implementation-spec time, not now) whether `DashboardController` and `MyAppsController` merge into one controller or remain two controllers sharing one service — explicitly deferred by the transition decision doc, still open.
3. The empty-state copy already drafted ("اكتشف التطبيقات والخدمات المتاحة لك") is ready to reuse verbatim when this is implemented — no further design work needed on that specific piece.

---

## Rollback Requirements

| Phase | Rollback path (verified, not just claimed) |
|---|---|
| L1 | Re-add 2 deleted lines + 1 `use` statement across 3 files. No data was touched. **Verified in this pass:** the removed lines are fully documented verbatim in `phase-l1-legacy-write-cutoff-completion-report.md` §12 — rollback is copy-paste, not reconstruction. |
| 2B/2A | Already documented in their own completion reports; unaffected by this review. |
| L2 (future) | **Does not exist yet** — must be a required section of the L2 spec itself (Prerequisite #1 above), specifically: how to un-migrate a record created in error without violating AD-001's append-only audit constraint (i.e., rollback = `cancel()` the erroneously-created `Subscription`, never delete the `AuditLog` entry that recorded the mistake — the mistake itself must remain auditable). |

---

## Test Requirements (before L2 code, not before this review)

- Cancelled-stays-cancelled regression test (the direct AD-014 enforcement test — does not exist yet, because L2 doesn't exist yet).
- Idempotent double-run test for any migration command.
- Dry-run mode test (assert zero writes).
- Parity Check command test (mirroring `CatalogParityTest` from Phase 1a).

None of these can be written meaningfully before the L2 spec defines the exact command surface — listed here as a checklist for that spec to satisfy, not as work to do now.

---

## Database Snapshot (this pass, live dev DB — confirms L1 remains stable)

```
app_subscriptions:   4   (unchanged since L1 completion)
subscriptions:        5   (unchanged since L1 completion)
access_assignments:   6   (unchanged since L1 completion)
subscription_seats:   2
audit_logs:           30
users:                8
organizations:        3
```
Full suite re-run in this pass: **109/109 passed, 305 assertions.** No test was modified or added by this review — this is the same suite left by L1, re-run only to confirm nothing has drifted since.

---

## Final Decision

# NOT READY FOR L2

**What would flip this to READY:** closing the three Blocking Issues — none of which require touching Header, Dashboard, or any existing Hukm w Rakam behavior. All three are Marketplace-side, additive, spec-then-code work, exactly the shape this project has executed five times already (1a, 1b, 2A, 2B, L1). AD-015 is not what's blocking L2 — it's already respected by everything currently built. What's blocking L2 is unfinished process (no spec, no parity tool) and one specific unpatched file.

**Nothing was fixed, modified, or implemented in the course of producing this review**, per your explicit instruction.
