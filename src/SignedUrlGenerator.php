<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

use InvalidArgumentException;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Generate and validate signed (tamper-proof) URLs.
 *
 * Usage:
 *   $url = $signed->generate('verify-email', ['id' => 42], expiration: 3600);
 *   $isValid = $signed->validate($url);
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class SignedUrlGenerator
{
    public function __construct(
        private readonly UrlGenerator $urlGenerator,
        private readonly string       $secret,
    ) {
        if (strlen($secret) < 16) {
            throw new InvalidArgumentException('Secret must be at least 16 characters.');
        }
    }

    /**
     * Generate a signed URL for a named route.
     *
     * @param string              $routeName  Named route identifier.
     * @param array<string,mixed> $parameters Route parameters.
     * @param int|null            $expiration TTL in seconds (null = never expires).
     * @param string              $baseUrl    Optional base URL for absolute URLs.
     */
    public function generate(
        string $routeName,
        array  $parameters = [],
        ?int   $expiration = null,
        string $baseUrl = '',
    ): string {
        $query = [];

        if ($expiration !== null) {
            $query['expires'] = (string) (time() + $expiration);
        }

        // Temporarily set base URL
        $previousBase = null;
        if ($baseUrl !== '') {
            $previousBase = $this->urlGenerator->baseUrl;
            $this->urlGenerator->baseUrl = $baseUrl;
        }

        $url = $this->urlGenerator->generate($routeName, $parameters, $baseUrl !== '');

        // Restore previous base URL
        if ($previousBase !== null) {
            $this->urlGenerator->baseUrl = $previousBase;
        } elseif ($baseUrl !== '') {
            $this->urlGenerator->baseUrl = '';
        }

        if ($query !== []) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query);
        }

        // Compute HMAC signature
        $signature = hash_hmac('sha256', $url, $this->secret);
        $url .= (str_contains($url, '?') ? '&' : '?') . 'signature=' . $signature;

        return $url;
    }

    /**
     * Generate a temporary signed route (convenience shorthand).
     */
    public function temporarySignedRoute(
        string $routeName,
        int    $expiresInSeconds,
        array  $parameters = [],
        string $baseUrl = '',
    ): string {
        return $this->generate($routeName, $parameters, $expiresInSeconds, $baseUrl);
    }

    /**
     * Validate that a URL has a correct, unexpired signature.
     */
    public function validate(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);

        if (!isset($query['signature'])) {
            return false;
        }

        $signature = $query['signature'];
        unset($query['signature']);

        // Check expiration
        if (isset($query['expires']) && (int) $query['expires'] < time()) {
            return false;
        }

        // Reconstruct URL without signature
        $baseUrl = ($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? '');
        if ($query !== []) {
            $baseUrl .= '?' . http_build_query($query);
        }

        // Strip scheme://host if not present in original
        if (!isset($parts['scheme'])) {
            $baseUrl = $parts['path'] ?? '';
            if ($query !== []) {
                $baseUrl .= '?' . http_build_query($query);
            }
        }

        $expected = hash_hmac('sha256', $baseUrl, $this->secret);

        return hash_equals($expected, $signature);
    }
}
