# ZktSn0w.Inertia

An Inertia.js server-side adapter for the Neos Flow Framework, using Fusion as the templating engine. This package lets you build modern single-page applications with frontend frameworks like Svelte, React, or Vue while leveraging Neos Flow for server-side routing, controllers, and data handling — without building a separate API.

## How It Works

On the initial page load, the server responds with a full HTML document rendered through Fusion. Subsequent navigations (Inertia Routes only!) are intercepted by the Inertia client-side library, which makes XHR requests and receives JSON responses containing the component name and props. This enables SPA-like navigation without full page reloads.

[See the Inertia Protocol](https://inertiajs.com/docs/v2/core-concepts/the-protocol)


The package provides:

- **`AbstractInertiaController`** — Base controller that sets up the FusionView and Inertia service for your action controllers.
- **`Inertia` service** — Provides the actual render method which renders either a full HTML page (initial visit) or a JSON response (Inertia XHR) depending on the request type.
- **`InertiaMiddleware`** — HTTP middleware that handles asset versioning checks and response status code adjustments for Inertia requests.
- **`InertiaBody` Fusion component** — Renders the root `<div>` with the `data-page` attribute that the Inertia client-side library reads to bootstrap your frontend.
- **`InertiaAssetVersionService`** — Manages asset versioning to trigger full page reloads when your frontend assets change.

The `AbstractInertiaController` provides an injected `Inertia` Service which contains a `render` method you can call.
```php
public function render(Request $request, string $component, array $props = [], array $viewProps = [], FusionView $view)
```

**Method Parameters**
`$request` -> The HTTP Request **required**

`$component` -> The Frontend Component (Svelte/Vue/React/...) **required**

`$props` -> All props that should be passed from the backend to the **Frontend** Component

`$viewProps` -> All props that should be passed from the backend to the **Fusion** Component

`$view` -> The Controllers FusionView (configured in AbstractInertiaController)


## Installation

Require the package via Composer:

```bash
composer require zktsn0w/inertia
```

## Configuration

Configure the package in your site package's `Settings.yaml` (e.g. `Configuration/Settings.ZktSn0w.Inertia.yaml`):

### Asset Versioning

Asset versioning ensures that when your frontend assets change, Inertia triggers a full page reload instead of an XHR swap.

**`SETTING`** (default) — Provide a static version string or number directly in settings:

```yaml
ZktSn0w:
    Inertia:
      assetVersioning:
        strategy:
          class: \ZktSn0w\Inertia\Domain\AssetVersion\SettingVersionStrategy
          options:
            value: 'foobar'
```
#### Coming soon...
**`FILE`** — Read the version from a file (useful for CI-generated version hashes) **(WIP)**:

```yaml
ZktSn0w:
  Inertia:
    assetVersioning:
      strategy: 'FILE'
      filePath: 'resource://Public/assetVersion'
```

### Fusion Path Patterns

Configure which Fusion packages are loaded for the Inertia controllers Fusion View. Add your own site package to the list:

```yaml
ZktSn0w:
  Inertia:
    fusion:
      fusionPathPatterns:
        - 'resource://Neos.Fusion/Private/Fusion'
        - 'resource://Neos.Neos/Private/Fusion'
        - 'resource://Neos.Fusion.Form/Private/Fusion'
        - 'resource://ZktSn0w.Inertia/Private/Fusion'
        - 'resource://Your.Package/Private/Fusion' # Your Package
```

## Usage

### 1. Create a Controller

Extend `AbstractInertiaController` and use the injected `$this->inertia` service to render responses.
```php
<?php
namespace Your\Package\Controller;

use GuzzleHttp\Psr7\Response;
use ZktSn0w\Inertia\Controller\AbstractInertiaController;

class ProductsController extends AbstractInertiaController
{
    public function indexAction(): Response
    {
        return $this->inertia->render(
            $this->request->getHttpRequest(),
            'Home',
            [],
            [],
            $this->view
        );
    }

    public function dashboardAction(): Response
    {
        return $this->inertia->render(
            $this->request->getHttpRequest(),
            'Dashboard',
            ['foo' => 'bar'],
            [],
            $this->view
        );
    }
}
```

### 2. Define the Fusion App Entry Point

Create a Fusion file that defines the `App` path. This is what Inertia renders as HTML on the initial full-page load. Use the `ZktSn0w.Inertia:InertiaBody` component to output the apps `<div>` which is inertias mount point:

```fusion
App = Your.Package:Document.Page {
  body.content.main >
  body.content.main = ZktSn0w.Inertia:InertiaBody {
    page = ${page}
  }
}
```

### 3. Set Up the Client Side

Install the Inertia client-side adapter for your framework of choice (e.g. `@inertiajs/svelte`, `@inertiajs/react`, `@inertiajs/vue3`) and initialize it to resolve page components by name.

### More information
Check out the [Inertia Documentation](https://inertiajs.com/docs/v2/getting-started/index) to get a better understanding of the mechanics of this Protocol.
