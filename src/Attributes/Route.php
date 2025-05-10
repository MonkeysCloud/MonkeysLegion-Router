<?php

declare(strict_types=1);

namespace MonkeysLegion\Router\Attributes;

use Attribute;

/**
 * #[Route('GET','/users/{id}')]
 * applied to controller methods to register routes.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Route
{
    public function __construct(
        public string $method,
        public string $path
    ) {}
}