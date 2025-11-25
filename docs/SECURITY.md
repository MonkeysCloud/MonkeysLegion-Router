# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.1.x   | :white_check_mark: |
| 1.x.x   | :x:                |

## Reporting a Vulnerability

The MonkeysLegion team takes security bugs seriously. We appreciate your efforts to responsibly disclose your findings.

### How to Report

**Please do NOT report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to:

**jorge@monkeys.cloud**

Include the following information:

- Type of vulnerability
- Full paths of source file(s) related to the vulnerability
- Location of the affected source code (tag/branch/commit or direct URL)
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

### What to Expect

- **Acknowledgment**: We will acknowledge receipt of your vulnerability report within 48 hours.
- **Assessment**: We will assess the vulnerability and determine its severity within 5 business days.
- **Updates**: We will keep you informed of our progress toward fixing the issue.
- **Resolution**: We will release a security patch as soon as possible, depending on complexity.
- **Credit**: We will credit you in the security advisory (unless you prefer to remain anonymous).

### Timeline

- **Initial Response**: Within 48 hours
- **Status Update**: Within 5 business days
- **Fix Timeline**: Depends on severity
    - Critical: Within 7 days
    - High: Within 14 days
    - Medium: Within 30 days
    - Low: Next minor release

## Security Best Practices

When using MonkeysLegion Router, follow these security best practices:

### 1. Route Constraints

Always use route constraints to validate parameters:

```php
// ✅ Good - constrains ID to integers
$router->get('/users/{id:\d+}', $handler);

// ❌ Bad - accepts any input
$router->get('/users/{id}', $handler);
```

### 2. Authentication Middleware

Protect sensitive routes with authentication:

```php
$router->registerMiddleware('auth', AuthMiddleware::class);
$router->get('/admin', $handler, 'admin', ['auth']);
```

### 3. CORS Configuration

Properly configure CORS for production:

```php
$cors = new CorsMiddleware([
    'allowed_origins' => ['https://yourdomain.com'], // Not '*'
    'allowed_methods' => ['GET', 'POST'], // Only what you need
    'allowed_headers' => ['Content-Type', 'Authorization'],
    'credentials' => true,
]);
```

### 4. Input Validation

Always validate user input in handlers:

```php
$router->post('/users', function($request) {
    $data = $request->getParsedBody();
    
    // Validate input
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return new Response(Stream::createFromString('Invalid email'), 400);
    }
    
    // Process valid input
});
```

### 5. Error Handling

Don't expose sensitive information in errors:

```php
// ✅ Good - Generic error message
$router->setNotFoundHandler(function($request) {
    return new Response(
        Stream::createFromString('Not Found'),
        404
    );
});

// ❌ Bad - Exposes internal paths
$router->setNotFoundHandler(function($request) {
    return new Response(
        Stream::createFromString('Route not found: ' . $request->getUri()->getPath()),
        404
    );
});
```

### 6. Rate Limiting

Implement rate limiting on public endpoints:

```php
$router->registerMiddleware('throttle', new ThrottleMiddleware(60, 1));
$router->post('/api/login', $handler, 'login', ['throttle']);
```

### 7. Secure Headers

Add security headers via middleware:

```php
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $response = $next($request);
        
        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
```

### 8. Route Caching

Be cautious with route caching in development:

```php
$cache = new RouteCache(__DIR__ . '/cache');

// Only use cache in production
if (getenv('APP_ENV') === 'production' && $cache->has()) {
    $collection->import($cache->load());
} else {
    // Register routes normally
}
```

### 9. Parameter Injection

Avoid SQL injection by using prepared statements:

```php
$router->get('/users/{id:\d+}', function($request, $id) {
    // ✅ Good - Use prepared statements
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    
    // ❌ Bad - Direct concatenation
    // $result = $pdo->query("SELECT * FROM users WHERE id = $id");
});
```

### 10. HTTPS Only

Always use HTTPS in production:

```php
// Redirect HTTP to HTTPS
$router->addGlobalMiddleware('force-https');

class ForceHttpsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        if ($request->getUri()->getScheme() !== 'https' && getenv('APP_ENV') === 'production') {
            $httpsUri = $request->getUri()->withScheme('https');
            return new Response(
                Stream::createFromString(''),
                301,
                ['Location' => (string) $httpsUri]
            );
        }
        
        return $next($request);
    }
}
```

## Known Vulnerabilities

We maintain a list of known vulnerabilities and their status:

### CVE Database

No CVEs have been assigned to MonkeysLegion Router at this time.

### Security Advisories

Subscribe to security advisories:
- GitHub Security Advisories: [Watch this repository](https://github.com/MonkeysCloud/MonkeysLegion-Skeleton/security/advisories)
- Email notifications: jorge@monkeys.cloud

## Security Updates

Security updates are released as:

- **Patch releases** (1.1.x) for minor security fixes
- **Minor releases** (2.x.0) for security improvements
- **Out-of-band patches** for critical vulnerabilities

Always keep your dependencies updated:

```bash
composer update monkeyscloud/monkeyslegion-router
```

## Third-Party Dependencies

MonkeysLegion Router has minimal dependencies:

- `psr/http-message` - PSR-7 HTTP message interfaces
- `monkeyscloud/monkeyslegion-http` - HTTP implementation

We regularly monitor these dependencies for security issues.

## Security Checklist

Before deploying to production:

- [ ] All routes use appropriate constraints
- [ ] Authentication middleware on protected routes
- [ ] CORS properly configured (not `*`)
- [ ] Rate limiting on public endpoints
- [ ] Input validation in all handlers
- [ ] Error messages don't expose sensitive data
- [ ] HTTPS enforced
- [ ] Security headers implemented
- [ ] Dependencies up to date
- [ ] Route cache enabled for performance

## Disclosure Policy

When we receive a security report:

1. We confirm the issue and determine affected versions
2. We audit code to find similar problems
3. We prepare fixes for all maintained versions
4. We release security advisories and patches

We follow **responsible disclosure**:

- We will not disclose issues until patches are available
- We will credit researchers (unless they prefer anonymity)
- We request a 90-day embargo before public disclosure

## Hall of Fame

We thank the following researchers for responsibly disclosing vulnerabilities:

*No vulnerabilities reported yet*

---

## Contact

- **Security Issues**: jorge@monkeys.cloud
- **General Support**: jorge@monkeys.cloud
- **GitHub**: https://github.com/monkeyscloud/monkeyslegion-router

## PGP Key

For encrypted communications:

```
-----BEGIN PGP PUBLIC KEY BLOCK-----
[Your PGP public key here]
-----END PGP PUBLIC KEY BLOCK-----
```

## References

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [CWE Top 25](https://cwe.mitre.org/top25/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [PSR-7 Security Considerations](https://www.php-fig.org/psr/psr-7/)

---

**Last Updated**: November 2025  
**Version**: 1.1