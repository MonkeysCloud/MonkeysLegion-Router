# Changelog

All notable changes to the MonkeysLegion Router will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-11-23

### Added

#### Core Features
- **Named Routes**: Full support for named routes with URL generation
- **URL Generator**: Generate URLs from route names with parameters
- **Route Groups**: Organize routes with shared prefixes, middleware, and constraints
- **Middleware System**: Complete middleware pipeline with interface and built-in implementations
- **Route Constraints**: Built-in constraints (int, slug, uuid, email, alpha, numeric, alphanumeric)
- **Optional Parameters**: Support for optional route segments `{param?}`
- **Route Caching**: Production-ready route caching system
- **HTTP Method Helpers**: Convenience methods `get()`, `post()`, `put()`, `delete()`, `patch()`, `options()`
- **Method Not Allowed**: Proper 405 responses with Allow header

#### Attributes
- Enhanced `Route` attribute with middleware, constraints, defaults, domain, description, and metadata
- New `RoutePrefix` attribute for controller-level path prefixes
- New `Middleware` attribute for controller and method-level middleware

#### Middleware
- `MiddlewareInterface`: Standard interface for middleware
- `MiddlewarePipeline`: Middleware chain processor
- `CorsMiddleware`: Built-in CORS handling with full configuration
- `AuthMiddleware`: Example authentication middleware
- `ThrottleMiddleware`: Example rate limiting middleware
- `LoggingMiddleware`: Example request/response logging
- `JsonMiddleware`: JSON request validation

#### Constraints
- `RouteConstraintInterface`: Interface for custom constraints
- `IntegerConstraint`: Validates integer parameters
- `NumericConstraint`: Validates numeric values with decimals
- `AlphaConstraint`: Validates alphabetic characters
- `AlphanumericConstraint`: Validates alphanumeric characters
- `SlugConstraint`: Validates URL slugs
- `UuidConstraint`: Validates UUID format
- `EmailConstraint`: Validates email format
- `RegexConstraint`: Custom regex patterns

#### Route Collection Enhancements
- Named route registration and lookup
- Route constraints storage
- Route defaults support
- Domain constraints
- Metadata storage
- Import/export for caching
- Enhanced specificity calculation for optional parameters

#### Router Enhancements
- Middleware registration by name
- Middleware groups
- Global middleware
- Controller registration with prefix and middleware inheritance
- Custom 404 handler
- Custom 405 handler
- Route group context management
- URL generation helper method

#### Developer Experience
- Comprehensive usage examples
- Complete API documentation
- README with all features documented
- Example middleware implementations
- Complete controller examples

### Changed
- **Breaking**: Enhanced `Route` attribute constructor with new parameters
- **Breaking**: `RouteCollection::add()` signature includes new parameters
- **Breaking**: `Router::add()` signature includes new parameters
- Improved route specificity calculation
- Enhanced parameter extraction to support optional parameters
- Better error messages and validation

### Improved
- Route matching algorithm now handles optional parameters
- Specificity scoring accounts for static vs dynamic segments
- Middleware pipeline uses proper functional composition
- Request attributes now include default parameter values
- Controller registration extracts controller-level attributes

### Performance
- Route caching system for production deployments
- Optimized route sorting algorithm
- Lazy middleware resolution
- Efficient parameter extraction

## [1.0.0] - 2024-06-15

### Initial Release
- Basic route registration
- HTTP method support (GET, POST, etc.)
- Path parameter extraction
- Attribute-based routing with `#[Route]`
- Controller registration
- PSR-7 compatibility
- Basic 404 handling
- Route specificity sorting

---

## Migration Guide v1.0 → v2.0

### Route Definition
```php
// v1.0
$router->add('GET', '/users/{id}', $handler);

// v2.0 - Basic (backward compatible)
$router->add('GET', '/users/{id}', $handler);

// v2.0 - With new features
$router->get('/users/{id:\d+}', $handler, 'users.show');
```

### Attribute Changes
```php
// v1.0
#[Route('GET', '/users', name: 'user_list')]

// v2.0 - Enhanced
#[Route(
    'GET', 
    '/users',
    name: 'users.index',
    middleware: ['auth'],
    summary: 'List users',
    tags: ['Users']
)]
```

### Controller Registration
```php
// v1.0 - Still works
$router->registerController(new UserController());

// v2.0 - With prefix and middleware
#[RoutePrefix('/api/users')]
#[Middleware(['cors', 'throttle'])]
class UserController { ... }
```

### Middleware
```php
// v1.0 - New feature
$router->registerMiddleware('auth', AuthMiddleware::class);
$router->addGlobalMiddleware('cors');

$router->get('/admin', $handler, 'admin', ['auth']);
```

### URL Generation
```php
// v2.0 - New feature
$router->get('/users/{id}', $handler, 'users.show');
$url = $router->url('users.show', ['id' => 123]);
// Output: /users/123
```

### Route Groups
```php
// v2 - New feature
$router->group(function ($router) {
    $router->get('/users', $handler);
    $router->get('/posts', $handler);
})
->prefix('/api')
->middleware(['cors'])
->group(fn() => null);
```

## Upgrading

### Composer
Update your `composer.json`:
```json
{
    "require": {
        "monkeyscloud/monkeyslegion-router": "^2.0"
    }
}
```

Then run:
```bash
composer update monkeyscloud/monkeyslegion-router
```

### Code Changes
Most v1.0 code will continue to work in v2.0. The main changes to watch for:

1. If you were extending `Route` or `RouteCollection`, check the new signatures
2. Custom route matching logic may need updates for optional parameters
3. Consider adopting new features like middleware and constraints

### Testing
Thoroughly test your routes after upgrading, especially:
- Routes with multiple parameters
- Custom route extensions
- Controller attribute scanning

## Support

- **Documentation**: See README.md
- **Issues**: https://github.com/MonkeysCloud/MonkeysLegion-Skeleton/issues