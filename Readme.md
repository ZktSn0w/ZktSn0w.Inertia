# ZktSn0w.Inertia

An Inertia.js server-side adapter for the Neos Flow Framework. Build modern single-page applications with Svelte, React, or Vue while leveraging Neos Flow for routing, controllers, and data — without building a separate API.

Choose your integration style: the **Trait API** for controller-centric rendering, or the **Fusion API** for Fusion-first setups with Neos CMS page rendering support. Both produce the same Inertia protocol responses — use whichever fits your architecture.

## Versioning

This package follows [Semantic Versioning](https://semver.org/). Current version: **1.0.0**

> **Important:** Version 1.0 targets **Inertia.js v2** (protocol v2). This adapter is not compatible with Inertia.js v1.x clients.

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
| `Inertia` Trait | `ZktSn0w\Inertia\Trait\Inertia` | Trait API: adds `inertia()` to any controller |
| `InertiaPage` Prototype | `ZktSn0w.Inertia:InertiaPage` | Fusion API: renders Neos page, switches JSON/HTHL via `@process` |
| `InertiaBody` Prototype | `ZktSn0w.Inertia:InertiaBody` | Fusion: renders `<div data-page="...">` mount point |
| `PageFactory` | `ZktSn0w\Inertia\Factory\PageFactory` | Creates fully resolved `Page` objects (both APIs) |
| `InertiaHelper` (Eel) | `ZktSn0w\Inertia\Eel\InertiaHelper` | Eel helper: `Inertia.isInertiaRequest()` for Fusion conditions |
| Middleware | `ZktSn0w\Inertia\Http\Middleware\InertiaMiddleware` | Asset version checks, status code fixes, Content-Type |
| Error Middleware | `ZktSn0w\Inertia\Http\Middleware\InertiaErrorMiddleware` | Catches exceptions on XHR, returns JSON errors |
| Asset Version Service | `ZktSn0w\Inertia\Service\InertiaAssetVersionService` | Resolves the configured versioning strategy |
| Shared Props Service | `ZktSn0w\Inertia\Service\SharedPropsService` | Per-request bag for shared Inertia props |
| Shared Props Middleware | `ZktSn0w\Inertia\Http\Middleware\AbstractSharedPropsMiddleware` | Base middleware for cross-cutting shared data |
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

## Usage — Two APIs

This package provides two APIs for rendering Inertia responses. Both produce the same protocol output — use whichever matches your architecture.

| | Trait API | Fusion API |
|---|---|---|
| **Style** | Controller-centric | Fusion-centric |
| **Controller** | Uses `Inertia` trait, returns `inertia()` | Uses `PageFactory` directly, assigns `Page` to view |
| **PageFactory** | Used internally by the trait | Used directly by controller |
| **XHR JSON** | Trait returns JSON Response | `InertiaPage` prototype's `@process` replaces HTML with JSON |
| **Initial load** | Trait assigns Page, calls `view->render()` | Fusion renders full Neos page |
| **Best for** | Standalone controllers, simple setups | Neos CMS integration, Fusion-heavy projects |

Both APIs use `PageFactory` to build `Page` objects — it's the shared core for URL detection, asset version, shared props merging, deferred prop resolution, and partial reload filtering.

### Trait API

Add the `Inertia` trait to any Flow action controller. Call `inertia()` in your actions — it returns a JSON Response for XHR requests, or calls `view->render()` and returns the rendered result for initial page loads. The trait uses `PageFactory` internally to build the `Page` object. HTTP headers (Content-Type, Vary, X-Inertia-Version) are handled by `InertiaMiddleware`.

**Controller:**

```php
<?php
namespace Your\Package\Controller;

use Neos\Flow\Mvc\Controller\ActionController;
use Psr\Http\Message\ResponseInterface;
use ZktSn0w\Inertia\Trait\Inertia;

class ProductsController extends ActionController
{
    use Inertia;

    public function indexAction(): ResponseInterface
    {
        return $this->inertia('Products/Index', [
            'greeting' => 'Hello from Inertia!',
        ]);
    }
}
```

**`inertia()` signature:**

```php
inertia(string $component, array $props = []): ?ResponseInterface
```

| Parameter | Description |
|---|---|
| `$component` | Frontend component name (e.g. `'Home'`, `'Dashboard'`) |
| `$props` | Data passed to the frontend component |

**Fusion view** (e.g. `inertia` path):

```fusion
inertia = Your.Package:Document.Page {
  head.titleTag >
  body.menu >
  body.content.main >
  body.content.main = ZktSn0w.Inertia:InertiaBody
}
```

The trait assigns the `Page` object to the view as `inertiaPage`. `InertiaBody` reads it from the Fusion context (defaults to `${inertiaPage}`).

### Fusion API

Use `PageFactory` in your controller to create a `Page` object, assign it to the view, and let the `InertiaPage` Fusion prototype handle the JSON/HTHL switching via its `@process` directive. No trait needed.

**Controller:**

```php
<?php
namespace Your\Package\Controller;

use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Fusion\View\FusionView;
use ZktSn0w\Inertia\Factory\PageFactory;
use Neos\Flow\Annotations as Flow;

class PageController extends ActionController
{
    protected $defaultViewObjectName = FusionView::class;

    #[Flow\Inject]
    protected PageFactory $pageFactory;

    #[Flow\InjectConfiguration(path: "fusion.fusionPathPatterns", package: "Your.Package")]
    protected array $fusionPathPatterns;

    protected function initializeView(ViewInterface $view): void
    {
        if ($view instanceof FusionView) {
            $view->setFusionPathPatterns($this->fusionPathPatterns);
        }
    }

    public function indexAction(): void
    {
        $page = $this->pageFactory->create('Home', [
            'greeting' => 'Hello from Fusion API!',
        ], $this->request->getHttpRequest());

        $this->view->assign('inertiaPage', $page);
    }
}
```

**Fusion** — Flow auto-resolves the Fusion path to `<PackageKey>.<ControllerName>Controller.<ActionName>`:

```fusion
Your.Package.PageController.index = ZktSn0w.Inertia:InertiaPage {
  head {
    stylesheets.site = afx`
        <link rel="stylesheet" href={StaticResource.uri('Your.Package', 'Public/assets/main.css')} />`
    javascripts.site = afx`
        <script type="module" defer src={StaticResource.uri('Your.Package', 'Public/assets/main.js')}></script>`
  }
  body = ZktSn0w.Inertia:InertiaBody {
    page = ${inertiaPage}
  }
}
```

| Prototype | Purpose |
|---|---|
| `ZktSn0w.Inertia:InertiaPage` | Extends `Neos.Neos:Page`. Uses `@process.inertiaResponse` to return JSON for XHR or full HTML for initial loads |
| `ZktSn0w.Inertia:InertiaBody` | Renders `<div id="app" data-page="...">` mount point. Defaults `page` from `${inertiaPage}` context variable |

**How `InertiaPage` works:**

```
Request → InertiaMiddleware (sets Content-Type) → Controller (assigns inertiaPage) → Fusion
  ├─ X-Inertia header present → @process replaces rendered HTML with JSON.stringify(inertiaPage)
  └─ No X-Inertia header      → @process passes through full HTML page
```

### Render the Mount Point

The Inertia client needs a `<div id="app" data-page="...">` element. Use the built-in `InertiaBody` prototype:

```fusion
body.content.main = ZktSn0w.Inertia:InertiaBody
```

`InertiaBody` defaults `page` from the `${inertiaPage}` context variable — no explicit prop needed in simple cases.

**With Fluid** — serialize the Page object manually:

```html
<div id="app" data-page="{inertiaPage -> f:format.json()}"></div>
```

### Set Up the Client Side

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

## Fusion Auto-Include

Enable Neos CMS auto-include so Inertia prototypes are available globally:

```yaml
Neos:
  Neos:
    fusion:
      autoInclude:
        ZktSn0w.Inertia: true
```

Register middleware (both packages):

```yaml
Neos:
  Flow:
    http:
      middlewares:
        'inertiaError':
          position: 'before inertia'
          middleware: 'ZktSn0w\Inertia\Http\Middleware\InertiaErrorMiddleware'
        'inertia':
          position: 'before dispatch'
          middleware: 'ZktSn0w\Inertia\Http\Middleware\InertiaMiddleware'
```

See [`Documentation/01-Architecture.md`](Documentation/01-Architecture.md) for the full architecture and [`Documentation/04-API-Reference.md`](Documentation/04-API-Reference.md) for all classes and prototypes.
