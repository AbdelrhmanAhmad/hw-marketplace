<?php

namespace App\Services;

use App\Models\MarketplaceItem;
use App\Models\User;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records interest against Core identity. Intentionally does NOT touch SubscriptionService.
 */
class MarketplaceInterestService
{
    public function __construct(
        private readonly CoreSsoClient $coreSso,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function express(User $user, string $marketplaceKey): array
    {
        if ($user->isPlatformStaff() && $user->core_user_id === null) {
            throw new RuntimeException('Platform staff accounts without a Core link cannot record interest via SSO.');
        }

        if ($user->core_user_id === null) {
            throw new RuntimeException('يجب الدخول عبر حساب حكم ورقم قبل تسجيل الاهتمام.');
        }

        $item = MarketplaceItem::query()->where('key', $marketplaceKey)->first();

        if (! $item) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $this->coreSso->recordInterest(
            (int) $user->core_user_id,
            $item->key,
            $item->name,
            'interested',
        );
    }
}
