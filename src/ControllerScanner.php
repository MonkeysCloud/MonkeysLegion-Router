<?php
declare(strict_types=1);

namespace MonkeysLegion\Router;

use MonkeysLegion\Router\Attributes\ApiResource;
use MonkeysLegion\Router\Attributes\Middleware as MiddlewareAttribute;
use MonkeysLegion\Router\Attributes\Route as RouteAttribute;
use MonkeysLegion\Router\Attributes\RoutePrefix;
use MonkeysLegion\Router\Attributes\Throttle;
use MonkeysLegion\Router\Attributes\WithoutMiddleware;

use ReflectionClass;

/**
 * MonkeysLegion Framework — Router Package
 *
 * Auto-discovers controllers in a directory by scanning for #[Route]
 * and #[ApiResource] attributes. Zero-config controller registration.
 *
 * Usage:
 *   $scanner = new ControllerScanner($router);
 *   $scanner->scan('/path/to/app/Controller');
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ControllerScanner
{
    public function __construct(
        private readonly Router $router,
    ) {}

    /**
     * Scan a directory for controller classes.
     *
     * @param string $directory  Absolute path to controller directory.
     * @param string $namespace  PSR-4 namespace for the directory.
     */
    public function scan(string $directory, string $namespace = 'App\\Controller'): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->resolveClassName($file->getPathname(), $directory, $namespace);

            if ($className === null || !class_exists($className)) {
                continue;
            }

            $ref = new ReflectionClass($className);

            if ($ref->isAbstract() || $ref->isInterface()) {
                continue;
            }

            // Check for ApiResource or Route attributes
            $hasRouteAttributes = false;

            if ($ref->getAttributes(ApiResource::class) !== []) {
                $this->registerApiResource($ref);
                $hasRouteAttributes = true;
            }

            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getAttributes(RouteAttribute::class) !== []) {
                    $hasRouteAttributes = true;
                    break;
                }
            }

            if ($hasRouteAttributes) {
                $this->registerController($ref);
            }
        }
    }

    /**
     * Register a controller's #[Route] annotated methods.
     */
    private function registerController(ReflectionClass $ref): void
    {
        $controllerPrefix = '';
        $controllerMiddleware = [];

        // Controller-level prefix
        foreach ($ref->getAttributes(RoutePrefix::class) as $attr) {
            $prefix = $attr->newInstance();
            $controllerPrefix = $prefix->prefix;
            $controllerMiddleware = [...$controllerMiddleware, ...$prefix->middleware];
        }

        // Controller-level middleware
        foreach ($ref->getAttributes(MiddlewareAttribute::class) as $attr) {
            $mw = $attr->newInstance();
            $controllerMiddleware = [...$controllerMiddleware, ...$mw->middleware];
        }

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodMiddleware = $controllerMiddleware;

            // Method-level middleware (with only/except filtering)
            foreach ($ref->getAttributes(MiddlewareAttribute::class) as $attr) {
                $mw = $attr->newInstance();
                if (!$mw->appliesTo($method->getName())) {
                    $methodMiddleware = array_values(array_diff($methodMiddleware, $mw->middleware));
                }
            }

            // Additional method-level middleware
            foreach ($method->getAttributes(MiddlewareAttribute::class) as $attr) {
                $mw = $attr->newInstance();
                $methodMiddleware = [...$methodMiddleware, ...$mw->middleware];
            }

            // WithoutMiddleware exclusions
            foreach ($method->getAttributes(WithoutMiddleware::class) as $attr) {
                $without = $attr->newInstance();
                $methodMiddleware = array_values(
                    array_diff($methodMiddleware, $without->excluded),
                );
            }

            // Route attributes
            foreach ($method->getAttributes(RouteAttribute::class) as $attr) {
                $route = $attr->newInstance();

                $fullPath = $controllerPrefix . ($route->path === '' ? '' : '/' . ltrim($route->path, '/'));
                $fullPath = $fullPath !== '' ? (rtrim($fullPath, '/') ?: '/') : '/';
                $middleware = [...$methodMiddleware, ...$route->middleware];

                $meta = [
                    'summary'     => $route->summary,
                    'tags'        => $route->tags,
                    'description' => $route->description,
                    'handler'     => [$ref->getName(), $method->getName()],
                    ...$route->meta,
                ];

                // Throttle attribute → meta
                foreach ($method->getAttributes(Throttle::class) as $throttleAttr) {
                    $throttle = $throttleAttr->newInstance();
                    $meta['throttle'] = [
                        'max' => $throttle->max,
                        'per' => $throttle->per,
                        'by'  => $throttle->by,
                    ];
                }

                foreach ($route->methods as $httpMethod) {
                    $this->router->addRaw(
                        $httpMethod,
                        $fullPath,
                        [$ref->getName(), $method->getName()],
                        $route->name,
                        $middleware,
                        $route->where,
                        $route->defaults,
                        $route->domain,
                        $meta,
                    );
                }
            }
        }
    }

    /**
     * Register #[ApiResource] auto-CRUD routes.
     */
    private function registerApiResource(ReflectionClass $ref): void
    {
        foreach ($ref->getAttributes(ApiResource::class) as $attr) {
            $resource = $attr->newInstance();

            $prefix = $resource->prefix !== ''
                ? '/' . trim($resource->prefix, '/')
                : '/' . $this->deriveResourceName($ref->getShortName());

            $param     = $resource->parameter;

            $actionMap = [
                'index'   => ['method' => 'GET',    'suffix' => ''],
                'show'    => ['method' => 'GET',    'suffix' => "/{{$param}:{$resource->constraint}}"],
                'store'   => ['method' => 'POST',   'suffix' => ''],
                'update'  => ['method' => 'PUT',    'suffix' => "/{{$param}:{$resource->constraint}}"],
                'destroy' => ['method' => 'DELETE', 'suffix' => "/{{$param}:{$resource->constraint}}"],
            ];

            $resourceName = $this->deriveResourceName($ref->getShortName());

            foreach ($resource->actions as $action) {
                if (!isset($actionMap[$action])) {
                    continue;
                }

                $spec = $actionMap[$action];
                $path = $prefix . $spec['suffix'];
                $name = $resourceName . '.' . $action;

                $this->router->addRaw(
                    $spec['method'],
                    $path,
                    [$ref->getName(), $action],
                    $name,
                    $resource->middleware,
                    meta: ['handler' => [$ref->getName(), $action]],
                );
            }
        }
    }

    /**
     * Resolve PSR-4 class name from file path.
     */
    private function resolveClassName(string $filePath, string $baseDir, string $baseNamespace): ?string
    {
        // Normalize directory separators for cross-platform compatibility
        $filePath = str_replace(DIRECTORY_SEPARATOR, '/', $filePath);
        $baseDir  = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $baseDir), '/') . '/';

        if (!str_starts_with($filePath, $baseDir)) {
            return null;
        }

        $relative = substr($filePath, strlen($baseDir));
        $relative = str_replace('/', '\\', $relative);

        if (!str_ends_with($relative, '.php')) {
            return null;
        }

        $relative = substr($relative, 0, -4); // strip .php
        return $baseNamespace . '\\' . $relative;
    }

    /**
     * Derive resource name: UserController → users
     */
    private function deriveResourceName(string $shortName): string
    {
        $name = preg_replace('/Controller$/', '', $shortName);
        // PascalCase → kebab-case (handles consecutive uppercase, e.g. HTTPClient → http-client)
        $kebab = strtolower(preg_replace(
            ['/([A-Z]+)([A-Z][a-z])/', '/([a-z0-9])([A-Z])/'],
            ['$1-$2', '$1-$2'],
            lcfirst($name),
        ));
        return $kebab . 's';
    }
}
