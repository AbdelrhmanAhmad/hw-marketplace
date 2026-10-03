<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class CoreSsoProvisioningService
{
    /**
     * JIT link or create a local shadow user for a Core identity.
     *
     * @param  array{id: int, name: ?string, email: ?string, email_verified_at: ?string}  $coreUser
     */
    public function resolveLocalUser(array $coreUser): User
    {
        $coreUserId = (int) $coreUser['id'];
        $email = isset($coreUser['email']) ? mb_strtolower(trim((string) $coreUser['email'])) : '';

        if ($coreUserId < 1) {
            throw new RuntimeException('Invalid Core user id.');
        }

        $linked = User::query()->where('core_user_id', $coreUserId)->first();

        if ($linked) {
            $this->syncProfile($linked, $coreUser);

            return $linked->fresh();
        }

        if ($email !== '') {
            $byEmail = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($byEmail) {
                if ($byEmail->isPlatformStaff()) {
                    throw new RuntimeException('This email belongs to a platform staff account and cannot be linked via SSO.');
                }

                if ($byEmail->core_user_id !== null && (int) $byEmail->core_user_id !== $coreUserId) {
                    throw new RuntimeException('This Marketplace account is already linked to a different Core user.');
                }

                $byEmail->forceFill([
                    'core_user_id' => $coreUserId,
                ])->save();

                $this->syncProfile($byEmail, $coreUser);

                return $byEmail->fresh();
            }
        }

        if ($email === '') {
            throw new RuntimeException('Core user has no email; cannot provision Marketplace user.');
        }

        $user = new User;
        $user->forceFill([
            'core_user_id' => $coreUserId,
            'name' => filled($coreUser['name'] ?? null) ? (string) $coreUser['name'] : 'مستخدم حكم ورقم',
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => filled($coreUser['email_verified_at'] ?? null)
                ? now()
                : null,
            'is_platform_staff' => false,
        ])->save();

        return $user->fresh();
    }

    /**
     * @param  array{id: int, name: ?string, email: ?string, email_verified_at: ?string}  $coreUser
     */
    private function syncProfile(User $user, array $coreUser): void
    {
        $updates = [];

        if (filled($coreUser['name'] ?? null) && $user->name !== $coreUser['name']) {
            $updates['name'] = (string) $coreUser['name'];
        }

        if (filled($coreUser['email'] ?? null)) {
            $email = mb_strtolower(trim((string) $coreUser['email']));
            if ($user->email !== $email) {
                $conflict = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->where('id', '!=', $user->id)
                    ->exists();

                if (! $conflict) {
                    $updates['email'] = $email;
                }
            }
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }
    }
}
