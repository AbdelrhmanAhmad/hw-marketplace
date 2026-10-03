<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CoreSsoClient
{
    public function coreBaseUrl(): string
    {
        return rtrim((string) config('services.core.url', ''), '/');
    }

    public function clientId(): string
    {
        return (string) config('services.core.sso.client_id', '');
    }

    public function clientSecret(): string
    {
        return (string) config('services.core.sso.client_secret', '');
    }

    public function redirectUri(): string
    {
        return (string) config('services.core.sso.redirect_uri', '');
    }

    public function isConfigured(): bool
    {
        return filled($this->coreBaseUrl())
            && filled($this->clientId())
            && filled($this->clientSecret())
            && filled($this->redirectUri());
    }

    public function authorizeUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Core SSO is not configured.');
        }

        return $this->coreBaseUrl().'/sso/marketplace/authorize?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
        ]);
    }

    /**
     * @return array{id: int, name: ?string, email: ?string, email_verified_at: ?string}
     */
    public function exchange(string $code): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Core SSO is not configured.');
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(10)
                ->withBasicAuth($this->clientId(), $this->clientSecret())
                ->post($this->coreBaseUrl().'/api/sso/marketplace/exchange', [
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri(),
                ])
                ->throw();
        } catch (RequestException $e) {
            throw new RuntimeException('SSO exchange failed: '.$e->getMessage(), 0, $e);
        }

        $user = $response->json('user');

        if (! is_array($user) || ! isset($user['id'])) {
            throw new RuntimeException('SSO exchange returned an invalid user payload.');
        }

        return [
            'id' => (int) $user['id'],
            'name' => isset($user['name']) ? (string) $user['name'] : null,
            'email' => isset($user['email']) ? (string) $user['email'] : null,
            'email_verified_at' => isset($user['email_verified_at']) ? (string) $user['email_verified_at'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordInterest(int $coreUserId, string $marketplaceKey, string $marketplaceName, string $status = 'interested'): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Core SSO is not configured.');
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(10)
                ->withBasicAuth($this->clientId(), $this->clientSecret())
                ->post($this->coreBaseUrl().'/api/integrations/marketplace/interests', [
                    'core_user_id' => $coreUserId,
                    'marketplace_key' => $marketplaceKey,
                    'marketplace_name' => $marketplaceName,
                    'status' => $status,
                ])
                ->throw();
        } catch (RequestException $e) {
            throw new RuntimeException('Interest API failed: '.$e->getMessage(), 0, $e);
        }

        return $response->json('interest') ?? $response->json();
    }
}
