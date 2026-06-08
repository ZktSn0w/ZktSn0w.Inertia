# ZktSn0w.Inertia

An Inertia.js server-side adapter for the Neos Flow Framework. Build modern single-page applications with Svelte, React, or Vue while leveraging Neos Flow for routing, controllers, and data — without building a separate API.

Works with any Flow view (`FusionView`, `TemplateView`, etc.). Render the `<div data-page="...">` mount point using your view of choice. The optional `ZktSn0w.Inertia.FusionAdapter` package provides an `InertiaBody` Fusion prototype for Fusion-based setups.

## Versioning

This package follows [Semantic Versioning](https://semver.org/). Current version: **0.3.0**

| Increment | When |
|---|---|
| **Major** | Breaking changes in the Inertia Protocol or Neos Flow compatibility |
| **Minor** | New Inertia or Neos Flow features |
| **Patch** | Bug fixes |

## Requirements

- `neos/flow: ^9.0`

## How It Works

On the initial page load, the server responds with a full HTML document rendered through your configured view (Fusion, Fluid, or any other Flow-compatible view). Subsequent navigations are intercepted by the Inertia client library, which makes XHR requests and receives JSON containing the component name and props — enabling SPA-like navigation without full page reloads.

[Inertia Protocol Documentation](https://inertiajs.com/docs/v2/core-concepts/the-protocol)

## Package Components

| Component | Class | Purpose |
|---|---|---|
| `Inertia` Trait | `ZktSn0w\Inertia\Trait\Inertia` | Adds `inertia()` to any controller |
| Middleware | `ZktSn0w\Inertia\Http\Middleware\InertiaMiddleware` | Asset version checks, status code fixes, headers |
| Asset Version Service | `ZktSn0w\Inertia\Service\InertiaAssetVersionService` | Resolves the configured versioning strategy |
| Shared Props Service | `ZktSn0w\Inertia\Service\SharedPropsService` | Per-request bag for shared Inertia props |
| Abstract Shared Props Middleware | `ZktSn0w\Inertia\Http\Middleware\Abstract\AbstractSharedPropsMiddleware` | Base middleware for cross-cutting shared data |
| `SettingStrategy` | `ZktSn0w\Inertia\Domain\AssetVersion\SettingStrategy` | Static version string from config |
| `FileStrategy` | `ZktSn0w\Inertia\Domain\AssetVersion\FileStrategy` | Version read from a file |
| `ManifestStrategy` | `ZktSn0w\Inertia\Domain\AssetVersion\ManifestStrategy` | Version from a JSON manifest file |

## Installation

```bash
composer require zktsn0w/inertia
```

Register the middleware in your site package's `Configuration/Settings.yaml`:

```yaml
Neos:
  Flow:
    http:
      middlewares:
        'inertia':
          position: 'after routing'
          middleware: 'ZktSn0w\Inertia\Http\Middleware\InertiaMiddleware'
```

## Configuration

### Asset Versioning

Asset versioning ensures Inertia triggers a full page reload when frontend assets change. Configure a strategy in your site package's `Configuration/Settings.yaml`.

**Setting strategy** — static version string:

```yaml
ZktSn0w:
  Inertia:
    assetVersioning:
      strategy:
        class: \ZktSn0w\Inertia\Domain\AssetVersion\SettingStrategy
        options:
          value: '1.0.0'
```

**File strategy** — version read from a file (useful for CI-generated hashes):

```yaml
ZktSn0w:
  Inertia:
    assetVersioning:
      strategy:
        class: \ZktSn0w\Inertia\Domain\AssetVersion\FileStrategy
        options:
          path: '/path/to/version.txt'
```

**Manifest strategy** — reads the `version` key from a JSON file:

```yaml
ZktSn0w:
  Inertia:
    assetVersioning:
      strategy:
        class: \ZktSn0w\Inertia\Domain\AssetVersion\ManifestStrategy
        options:
          path: '/path/to/manifest.json'
```

**Custom strategy** — implement `ZktSn0w\Inertia\Domain\AssetVersion\StrategyInterface` and configure:

```yaml
ZktSn0w:
  Inertia:
    assetVersioning:
      strategy:
        class: \Your\Package\Domain\AssetVersion\MyStrategy
        options:
          someOption: 'value'
```

> **Note:** `fusionPathPatterns` are no longer configured in this package. Register Fusion path patterns in your own site package.

## Usage

### 1. Add the Trait to Your Controller

Use the `ZktSn0w\Inertia\Trait\Inertia` trait in any Flow action controller. The trait requires `$this->request` (a Neos `ActionRequest`) and `$this->view` (any `ViewInterface` implementation — `FusionView`, `TemplateView`, etc.) to be set — both are provided automatically by Neos Flow's `ActionController` base class.

```php
<?php
namespace Your\Package\Controller;

use Neos\Flow\Mvc\Controller\ActionController;
use ZktSn0w\Inertia\Trait\Inertia;

class ProductsController extends ActionController
{
    use Inertia;

    public function indexAction(): ResponseInterface
    {
        return $this->inertia('Products/Index');
    }

    public function showAction(string $id): ResponseInterface
    {
        return $this->inertia(
            'Products/Show',
            ['product' => $this->productRepository->findById($id)], // frontend props
            ['pageTitle' => 'Product Detail']                        // view props
        );
    }
}
```

**`inertia()` signature:**

```php
inertia(string $component, array $props = [], array $viewProps = []): ResponseInterface
```

| Parameter | Description |
|---|---|
| `$component` | Frontend component name (e.g. `'Home'`, `'Dashboard'`) |
| `$props` | Data passed to the frontend component |
| `$viewProps` | Data passed to the view via `assignMultiple()` (server-side only, initial load only) |

### 2. Render the Mount Point

The Inertia client needs a `<div id="app" data-page="...">` element in the initial HTML. How you render it depends on your view:

**With Fusion** — install `zktsn0w/inertia-fusionadapter` and use the `InertiaBody` prototype:

```fusion
App = Your.Package:Document.Page {
  body.content.main >
  body.content.main = ZktSn0w.Inertia:InertiaBody {
    page = ${page}
  }
}
```

**With Fluid** — render the `page` variable directly in your template:

```html
<div id="app" data-page="{page -> f:format.json()}"></div>
```

**Any other view** — assign `page` via `$viewProps` and serialize it to `data-page` yourself.

### 3. Set Up the Client Side

Install the Inertia client adapter for your framework:

```bash
# Svelte
npm install @inertiajs/svelte

# React
npm install @inertiajs/react

# Vue
npm install @inertiajs/vue3
```

Initialize Inertia with a component resolver in your frontend entry point. See the [Inertia client-side setup docs](https://inertiajs.com/docs/v2/getting-started/index).

## Deferred Props

Wrap slow props in `DeferProp` to exclude them from the initial response. The Inertia client fetches them in a follow-up XHR after the page renders.

```php
use ZktSn0w\Inertia\Domain\Prop\DeferProp;

public function indexAction(): ResponseInterface
{
    return $this->inertia('Dashboard', [
        'user'        => $this->currentUser(),                              // immediate
        'permissions' => new DeferProp(fn() => Permission::findAll()),      // deferred
        'teams'       => new DeferProp(fn() => Team::findAll(), 'sidebar'), // deferred, grouped
        'invites'     => new DeferProp(fn() => Invite::findPending(), 'sidebar'),
    ]);
}
```

Props sharing a group name are fetched in one request. Props without a group use `"default"` and fetch alone. On the client, wrap the deferred content in `<Deferred data="propName">` with a fallback slot.

See [`Documentation/05-Deferred-Props.md`](Documentation/05-Deferred-Props.md) for full reference.

## Shared Props

Shared props are automatically merged into every `inertia()` response — no need to pass them in each controller action. Useful for auth user, flash messages, app config, etc.

### Via Middleware (recommended)

Extend `AbstractSharedPropsMiddleware` and register it **after** the Inertia middleware:

```php
<?php
namespace Your\Package\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use ZktSn0w\Inertia\Http\Middleware\Abstract\AbstractSharedPropsMiddleware;

final class MySharedProps extends AbstractSharedPropsMiddleware
{
    protected function getSharedProps(ServerRequestInterface $request): array
    {
        return [
            'auth' => ['user' => $this->authService->getCurrentUser()],
        ];
    }
}
```

```yaml
Neos:
  Flow:
    http:
      middlewares:
        'mySharedProps':
          position: 'after inertia'
          middleware: 'Your\Package\Http\Middleware\MySharedProps'
```

### Via Controller

Call `share()` directly — props persist across forwards and sub-actions within the same request:

```php
public function initializeAction(): void
{
    $this->share(['appName' => 'My App']);
}
```

Shared props are merged after page props, so shared keys can be overridden by controller props of the same name.

---

## Fusion Adapter

For Fusion-based setups, the [`ZktSn0w.Inertia.FusionAdapter`](https://github.com/ZktSn0w/ZktSn0w.Inertia.FusionAdapter) package provides the `ZktSn0w.Inertia:InertiaBody` prototype that renders the `<div data-page="...">` mount point.

```bash
composer require zktsn0w/inertia-fusionadapter
```

See the [FusionAdapter README](https://github.com/ZktSn0w/ZktSn0w.Inertia.FusionAdapter) for usage.
