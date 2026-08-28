<?php

namespace App\Services;

use App\Enums\AccessReason;

/**
 * ناتج EntitlementResolver — Value Object بسيط، ليس Model.
 */
final readonly class AccessDecision
{
    public function __construct(
        public bool $allowed,
        public AccessReason $reason,
    ) {
    }
}
