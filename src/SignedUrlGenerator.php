<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

use InvalidArgumentException;

/**
 * Generate and validate signed (tamper-proof) URLs.
 *
 * Useful for email verification links, temporary download URLs, etc.
 *
 * Usage:
 *   $signed = $signedUrl->generate('verify-email', ['id' => 42], expiration: 3600);
 *   // → /verify-email/42?expires=1700000000&signature=abc123…
 *
 *   $isValid = $signedUrl->validate($signed);  // true if signature is valid and not expired
 */
class SignedUrlGenerator
{
    public function __construct(
        private UrlGenerator $urlGenerator,
        private string $secret
    ) {
        if (strlen($secret) < 16) {
            throw new InvalidArgumentException('Secret must be at least 16 characters.');
        }
    }

    /**
     * Generate a signed URL for a named route.
     *
     * @param string   $routeName   Named route identifier
     * @param array    $parameters  Route parameters
     * @param int|null $expiration  TTL in seconds (null = never expires)
     * @param string   $baseUrl     Optional base URL for absolute URLs
     */
    public function generate(
        string $routeName,
        array $parameters = [],
        ?int $expiration = null,
        string $baseUrl = ''
    ): string {
        $query = [];

        if ($expiration !== null) {
            $query['expires'] = (string)(time() + $expiration);
        }

        // Generate the base URL — if a custom baseUrl is provided,
        // temporarily set it on the UrlGenerator
        $previousBase = null;
        if ($baseUrl !== '') {
            $previousBase = $this->urlGenerator->getBaseUrl();
            $this->urlGenerator->setBaseUrl($baseUrl);
        }

        $url = $this->urlGenerator->generate(
            $routeName,
            $parameters,
            $baseUrl !== ''
        );

        // Restore previous base URL
        if ($previousBase !== null) {
            $this->urlGenerator->setBaseUrl($previousBase);
        } elseif ($baseUrl !== '') {
            $this->urlGenerator->setBaseUrl('');
        }

        if (!empty($query)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query);
        }

        // Compute HMAC signature over the full URL
        $signature = hash_hmac('sha256', $url, $this->secret);
        $url .= (str_contains($url, '?') ? '&' : '?') . 'signature=' . $signature;

        return $url;
    }

    /**
     * Validate that a URL has a correct, unexpired signature.
     *
     * @param string $url  The full URL to validate
     * @return bool
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
        if (isset($query['expires']) && (int)$query['expires'] < time()) {
            return false;
        }

        // Reconstruct URL without signature
        $baseUrl = ($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? '');
        if (!empty($query)) {
            $baseUrl .= '?' . http_build_query($query);
        }

        // Strip scheme://host if not present in original
        if (!isset($parts['scheme'])) {
            $baseUrl = $parts['path'] ?? '';
            if (!empty($query)) {
                $baseUrl .= '?' . http_build_query($query);
            }
        }

        $expected = hash_hmac('sha256', $baseUrl, $this->secret);

        return hash_equals($expected, $signature);
    }
}
