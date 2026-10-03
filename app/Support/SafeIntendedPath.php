<?php

namespace App\Support;

/**
 * Reject open redirects: only same-app relative paths are allowed as SSO "intended".
 */
class SafeIntendedPath
{
    public static function sanitize(?string $intended, string $fallback = '/marketplace'): string
    {
        if (! self::isRelativeSafePath($fallback)) {
            $fallback = '/marketplace';
        }

        if (! is_string($intended) || trim($intended) === '') {
            return $fallback;
        }

        $candidate = trim($intended);

        return self::isRelativeSafePath($candidate) ? self::pathWithQuery($candidate) : $fallback;
    }

    public static function normalize(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return self::isRelativeSafePath($path) ? self::pathWithQuery(trim($path)) : null;
    }

    private static function isRelativeSafePath(string $candidate): bool
    {
        if (str_contains($candidate, '://') || str_starts_with($candidate, '//')) {
            return false;
        }

        if (str_contains($candidate, "\0") || str_contains($candidate, '\\')) {
            return false;
        }

        $parts = parse_url($candidate);

        if ($parts === false) {
            return false;
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            return false;
        }

        $path = $parts['path'] ?? '';

        if ($path === '' || ! str_starts_with($path, '/')) {
            return false;
        }

        if (str_starts_with($path, '/auth/core')) {
            return false;
        }

        return true;
    }

    private static function pathWithQuery(string $candidate): string
    {
        $parts = parse_url($candidate);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $path.$query;
    }
}
