# Core SSO + Interest (Marketplace side)

> Counterpart to `hokm-wa-raqm/docs/integrations/marketplace-sso.md`.  
> Last updated: **2026-10-03**

## Customer auth

- `/login`, `/register` → `GET /auth/core/redirect` (Core identity)
- `/forgot-password` → Core `/user/password/forgot`
- Filament `/admin/login` → **local** (platform staff only)

## Local link

- Column: `users.core_user_id` (nullable, unique)
- JIT: linked / legacy email link (non-staff) / staff never auto-link / conflict block / create shadow

## Interest

- `POST /marketplace/{key}/interest` (auth) → Core `POST /api/integrations/marketplace/interests`
- Does **not** call `SubscriptionService`
- Guest email capture via existing `ServiceInterest` modal remains

## Env

```env
HW_URL=http://localhost:8001
HW_SSO_CLIENT_ID=hw-marketplace
HW_SSO_CLIENT_SECRET=...
HW_SSO_REDIRECT_URI="${APP_URL}/auth/core/callback"
```

`HW_URK` is legacy fallback only.

## Tests

- `tests/Feature/CoreSsoTest.php`
- `tests/Feature/MarketplaceInterestSsoTest.php`
