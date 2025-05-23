# MonkeysLegion Router Component

The **MonkeysLegion Router** provides an easy, attribute-based way to define and register HTTP routes in your application. It powers both request dispatching and live OpenAPI documentation.

---

## ✨ Key Features

* **Attribute‑driven** route definitions (`#[Route]`) on controller methods
* **Multi‑verb support**: define one or more HTTP methods per route
* **Auto‑scan** controllers for routes at boot (no manual route lists)
* **Imperative API** for dynamic routes via `$router->add()`
* Exposes a **`RouteCollection`** iterable for downstream consumers (dispatch, docs, CLI)
* Seamlessly integrates with the **OpenAPI generator** and **`route:list`** CLI

---

## 📦 Installation

Require the router component (if separated), or ensure your skeleton has it installed:

```bash
composer require monkeyscloud/monkeyslegion-router:^1.0@dev
```

Make sure your `composer.json` includes:

```jsonc
{
  "autoload": {
    "psr-4": {
      "MonkeysLegion\\Router\\": "src/Router/"
    }
  }
}
```

Then run:

```bash
composer dump-autoload
```

---

## 🚦 Defining Routes with Attributes

Use the `#[Route]` attribute on **public controller methods** to declare routes:

```php
namespace App\Controller;

use MonkeysLegion\Router\Attributes\Route;
use Psr\Http\Message\ResponseInterface;

final class UserController
{
    #[Route('GET', '/users', summary: 'List users', tags: ['User'])]
    public function index(): ResponseInterface { /* … */ }

    #[Route(['GET','POST'], '/users', name: 'user_create')]
    public function create(): ResponseInterface { /* … */ }

    #[Route('DELETE', '/users/{id}', summary: 'Remove a user')]
    public function delete(string $id): ResponseInterface { /* … */ }
}
```

| Parameter | Type                 | Description                                                                |
| --------- | -------------------- | -------------------------------------------------------------------------- |
| `methods` | `string`\|`string[]` | HTTP verb(s) to match (`"GET"` or `[`"GET","POST"`]`).                     |
| `path`    | `string`             | URI template with `{parameter}` syntax. Leading slash added automatically. |
| `name`    | `string`             | Optional route name / OpenAPI `operationId`. Auto-generated if omitted.    |
| `summary` | `string`             | One-line description for docs.                                             |
| `tags`    | `string[]`           | Grouping tags for OpenAPI / Swagger UI.                                    |

---

## 🧩 Imperative Routes

For dynamic or non-controller routes, call:

```php
$router->add(
    methods: ['GET'],
    path:    '/healthz',
    handler: [HealthController::class, 'probe'],
    name:    'health_check',
    summary: 'Kubernetes health endpoint',
    tags:    ['Ops']
);
```

This merges seamlessly into the same `RouteCollection` used for dispatch and docs.

---

## ⚙️ Container Integration

Register the router services in your DI config (`config/app.php`):

```php
use MonkeysLegion\Router\{RouteCollection, Router};

RouteCollection::class => fn() => new RouteCollection(),
Router::class          => fn($c) => new Router(
    $c->get(RouteCollection::class),
    $c
),
```

In your bootstrap (e.g. `RouteLoader`), scan controllers:

```php
$router = $container->get(Router::class);
$router->registerController(App\Controller\UserController::class);
// …repeat for each controller namespace…
```

Downstream, inject `RouteCollection` or `Router` into middleware, CLI, or OpenAPI generator.

---

## 📝 Available Classes

* **`MonkeysLegion\Router\Attributes\Route`** – the PHP attribute for routes
* **`MonkeysLegion\Router\Route`** – value object representing one route (methods, path, handler, metadata)
* **`MonkeysLegion\Router\RouteCollection`** – iterable collection of `Route` objects
* **`MonkeysLegion\Router\Router`** – scanner & registrar of attribute routes; also supports `add()`

---

## 🔧 CLI Commands

Once registered with your `CliKernel`, use:

```bash
php vendor/bin/ml route:list       # Display a table of all routes
php vendor/bin/ml openapi:export   # Export OpenAPI spec from RouteCollection
```

---

## 🛠️ Extending

* **Path parameters**: future support for parameter-level metadata (e.g. regex constraints).
* **Versioning**: add `version` or `prefix` options on the attribute or in `Router`.
* **Middleware per-route**: store and apply route-specific middleware stacks.

Contributions welcome! Please submit issues or PRs on the GitHub repository.

---

## 📄 License

Released under the MIT License © 2025 MonkeysCloud
