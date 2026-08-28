<?php

namespace App\Services;

/**
 * ناتج App\Services\LegacyMigrationService::rollback() — Value Object بسيط.
 * راجع docs/legacy-subscription-l2-safe-migration-specification.md قسم ١١.
 */
final readonly class RollbackOutcome
{
    private function __construct(
        public bool $succeeded,
        public string $reason,
    ) {
    }

    public static function rolledBack(): self
    {
        return new self(true, 'تراجَع بنجاح — لا نشاط لاحق مسجَّل على هذا الاشتراك بعد إنشاء L2 له.');
    }

    public static function refused(string $reason): self
    {
        return new self(false, $reason);
    }
}
