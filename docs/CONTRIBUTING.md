# Contributing to MonkeysLegion Router

Thank you for your interest in contributing to MonkeysLegion Router! This document provides guidelines and instructions for contributing.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [How to Contribute](#how-to-contribute)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Documentation](#documentation)
- [Pull Request Process](#pull-request-process)
- [Release Process](#release-process)

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inclusive environment for all contributors. We expect all participants to:

- Use welcoming and inclusive language
- Be respectful of differing viewpoints and experiences
- Gracefully accept constructive criticism
- Focus on what is best for the community
- Show empathy towards other community members

### Unacceptable Behavior

- Harassment, discrimination, or offensive comments
- Personal attacks or trolling
- Publishing others' private information
- Any conduct that could reasonably be considered inappropriate

## Getting Started

### Prerequisites

- PHP 8.4 or higher
- Composer
- Git
- PHPUnit (installed via Composer)

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:

```bash
git clone https://github.com/YOUR-USERNAME/monkeyslegion-router.git
cd monkeyslegion-router
```

3. Add the upstream repository:

```bash
git remote add upstream https://github.com/monkeyscloud/monkeyslegion-router.git
```

## Development Setup

### Install Dependencies

```bash
composer install
```

### Run Tests

```bash
composer test
# or
./vendor/bin/phpunit
```

### Code Analysis

```bash
composer analyse
# or
./vendor/bin/phpstan analyse src --level=8
```

### Check Code Style

```bash
composer cs-check
# or
./vendor/bin/php-cs-fixer fix --dry-run --diff
```

## How to Contribute

### Reporting Bugs

Before creating a bug report:

1. Check the [issue tracker](https://github.com/monkeyscloud/monkeyslegion-router/issues) for existing reports
2. Check the [documentation](README.md) for solutions
3. Try to reproduce the issue with the latest version

When creating a bug report, include:

- **Clear title**: Descriptive summary of the issue
- **Description**: Detailed explanation of the problem
- **Steps to reproduce**: Numbered list of steps
- **Expected behavior**: What should happen
- **Actual behavior**: What actually happens
- **Environment**: PHP version, OS, dependencies
- **Code samples**: Minimal code to reproduce the issue
- **Stack traces**: If applicable

**Template:**

```markdown
## Bug Description
[Clear description of the bug]

## Steps to Reproduce
1. First step
2. Second step
3. ...

## Expected Behavior
[What should happen]

## Actual Behavior
[What actually happens]

## Environment
- PHP Version: 8.4.0
- Router Version: 1.1.0
- OS: Ubuntu 24.04

## Code Sample
```php
// Minimal code to reproduce
```

## Stack Trace
```
[If applicable]
```
```

### Suggesting Features

Before suggesting a feature:

1. Check if it already exists
2. Check the [issue tracker](https://github.com/monkeyscloud/monkeyslegion-router/issues) for similar requests
3. Consider if it fits the project's scope

When suggesting a feature, include:

- **Clear title**: Descriptive feature name
- **Problem statement**: What problem does this solve?
- **Proposed solution**: How should it work?
- **Alternatives**: Other approaches you've considered
- **Examples**: Code examples of the proposed API
- **Use cases**: Real-world scenarios

**Template:**

```markdown
## Feature Request

### Problem
[What problem does this solve?]

### Proposed Solution
[How should it work?]

### Example Usage
```php
// Example code showing how the feature would be used
```

### Alternatives Considered
[Other approaches you've thought about]

### Additional Context
[Any other relevant information]
```

### Submitting Pull Requests

1. Create a new branch for your feature/fix:

```bash
git checkout -b feature/my-new-feature
# or
git checkout -b fix/bug-description
```

2. Make your changes following our [coding standards](#coding-standards)

3. Add tests for your changes

4. Ensure all tests pass:

```bash
composer test
```

5. Update documentation if needed

6. Commit your changes with a clear message:

```bash
git commit -m "Add feature: description of feature"
```

7. Push to your fork:

```bash
git push origin feature/my-new-feature
```

8. Create a Pull Request on GitHub

## Coding Standards

### PSR Standards

We follow these PHP-FIG standards:

- **PSR-1**: Basic Coding Standard
- **PSR-4**: Autoloading Standard
- **PSR-7**: HTTP Message Interface
- **PSR-12**: Extended Coding Style Guide

### Code Style

#### Declare Strict Types

Always declare strict types at the top of files:

```php
<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;
```

#### Type Hints

Use type hints for all parameters and return values:

```php
// ✅ Good
public function add(string $method, string $path, callable $handler): void
{
    // ...
}

// ❌ Bad
public function add($method, $path, $handler)
{
    // ...
}
```

#### Naming Conventions

- **Classes**: PascalCase (`RouteCollection`)
- **Methods**: camelCase (`addRoute()`)
- **Properties**: camelCase (`$routeCollection`)
- **Constants**: UPPER_SNAKE_CASE (`MAX_ROUTES`)
- **Interfaces**: PascalCase with `Interface` suffix (`MiddlewareInterface`)

#### Documentation

Use PHPDoc blocks for all public methods:

```php
/**
 * Register a new route.
 *
 * @param string $method HTTP method
 * @param string $path URI path
 * @param callable $handler Request handler
 * @return void
 */
public function add(string $method, string $path, callable $handler): void
{
    // ...
}
```

#### Code Organization

- Keep methods small and focused
- Use early returns to reduce nesting
- Limit line length to 120 characters
- Use blank lines to separate logical sections

**Example:**

```php
// ✅ Good - Early return
public function dispatch(ServerRequestInterface $request): ResponseInterface
{
    if (!$this->hasRoutes()) {
        return $this->notFoundResponse();
    }
    
    $route = $this->findRoute($request);
    
    if ($route === null) {
        return $this->notFoundResponse();
    }
    
    return $this->executeRoute($route, $request);
}

// ❌ Bad - Deep nesting
public function dispatch(ServerRequestInterface $request): ResponseInterface
{
    if ($this->hasRoutes()) {
        $route = $this->findRoute($request);
        if ($route !== null) {
            return $this->executeRoute($route, $request);
        } else {
            return $this->notFoundResponse();
        }
    } else {
        return $this->notFoundResponse();
    }
}
```

## Testing

### Writing Tests

- Write tests for all new features
- Maintain or improve code coverage
- Use descriptive test method names
- Follow the Arrange-Act-Assert pattern

**Example:**

```php
public function testRouteWithParameterMatchesCorrectly(): void
{
    // Arrange
    $router = new Router(new RouteCollection());
    $router->get('/users/{id}', function($request, $id) {
        return new Response(Stream::createFromString("User: {$id}"));
    });
    
    // Act
    $request = new ServerRequest('GET', new Uri('http://example.com/users/123'));
    $response = $router->dispatch($request);
    
    // Assert
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('User: 123', (string) $response->getBody());
}
```

### Test Coverage

- Aim for 80%+ code coverage
- Test both happy paths and edge cases
- Test error conditions

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
./vendor/bin/phpunit tests/RouterTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

## Documentation

### README Updates

Update README.md when:

- Adding new features
- Changing public APIs
- Adding examples

### Code Comments

- Explain "why", not "what"
- Keep comments up-to-date
- Remove commented-out code

```php
// ✅ Good - Explains why
// We sort by specificity to ensure /users/admin matches before /users/{id}
usort($routes, fn($a, $b) => $b['specificity'] <=> $a['specificity']);

// ❌ Bad - Explains what (obvious from code)
// Sort the routes array
usort($routes, fn($a, $b) => $b['specificity'] <=> $a['specificity']);
```

### CHANGELOG

Update CHANGELOG.md for all notable changes:

- Added features
- Changed behavior
- Deprecated features
- Removed features
- Fixed bugs
- Security fixes

## Pull Request Process

### Before Submitting

- [ ] Tests pass locally
- [ ] Code follows style guidelines
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] No merge conflicts
- [ ] Commits are atomic and well-described

### PR Description

Include in your PR description:

1. **Summary**: What does this PR do?
2. **Motivation**: Why is this change needed?
3. **Changes**: List of key changes
4. **Testing**: How was this tested?
5. **Screenshots**: If UI-related (N/A for this library)
6. **Related Issues**: Closes #123

**Template:**

```markdown
## Summary
[Brief description of changes]

## Motivation
[Why is this change needed?]

## Changes
- Change 1
- Change 2
- ...

## Testing
- [ ] Unit tests added/updated
- [ ] All tests pass
- [ ] Manually tested with example code

## Related Issues
Closes #123
```

### Review Process

1. Automated checks must pass (tests, code style)
2. At least one maintainer must review
3. Address review feedback
4. Maintainer will merge when approved

### After Merge

- Delete your branch
- Update your local repository:

```bash
git checkout main
git pull upstream main
```

## Release Process

### Versioning

We use [Semantic Versioning](https://semver.org/):

- **MAJOR** (1.0.0): Breaking changes
- **MINOR** (1.1.0): New features (backward compatible)
- **PATCH** (1.0.1): Bug fixes

### Release Checklist

1. Update CHANGELOG.md
2. Update version in composer.json
3. Run all tests
4. Tag release: `git tag -a v1.1.0 -m "Release v1.1.0"`
5. Push tag: `git push origin v1.1.0`
6. Create GitHub release
7. Update documentation

## Community

### Communication Channels

- **GitHub Issues**: Bug reports and feature requests
- **GitHub Discussions**: Questions and discussions
- **Email**: jorge@monkeys.cloud

### Getting Help

If you need help:

1. Check the [documentation](README.md)
2. Search [existing issues](https://github.com/monkeyscloud/MonkeysLegion-Skeleton/issues)
3. Ask in [GitHub Discussions](https://github.com/monkeyscloud/MonkeysLegion-Skeleton/discussions)
4. Email jorge@monkeys.cloud

## Recognition

Contributors will be recognized in:

- CHANGELOG.md for their contributions
- GitHub contributors page
- Release notes for significant contributions

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

## Quick Contribution Checklist

- [ ] Fork and clone repository
- [ ] Create feature branch
- [ ] Write code following standards
- [ ] Add tests
- [ ] Update documentation
- [ ] Run tests locally
- [ ] Commit with clear message
- [ ] Push to your fork
- [ ] Create Pull Request
- [ ] Address review feedback

---

Thank you for contributing to MonkeysLegion Router! 🐒

**Questions?** Open an issue or email jorge@monkeys.cloud