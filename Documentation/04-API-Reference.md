# API Reference

## `Trait\Inertia`

**File:** `Classes/Trait/Inertia.php`  
**Namespace:** `ZktSn0w\Inertia\Trait`

Add this trait to any Neos Flow action controller to gain the `inertia()` method.

### Prerequisites

The trait requires two properties to be set on the controller before `inertia()` is called:
- `$this->request` — must be a `Neos\Flow\Mvc\ActionRequest`
- `$this->view` — any `Neos\Flow\Mvc\View\ViewInterface` implementation (`FusionView`, `TemplateView`, etc.)

Both are provided automatically by `Neos\Flow\Mvc\Controller\ActionController`.

The trait self-injects `PageFactory` and `SharedPropsService` via Flow's DI mechanism. You do not need to inject them manually.

### `inertia()`

```php
private function inertia(
    string $component,
    array $props = []
): ?ResponseInterface
```

| Parameter | Type | Description |
|---|---|---|
| `$component` | `string` | Frontend component name, e.g. `'Products/Show'` |
| `$props` | `array` | Passed to the frontend component as props |

**Returns:** `Psr\Http\Message\ResponseInterface|null` — JSON response for XHR, `null` for initial load (FusionView renders).

**Throws:**
- `\Exception` if `$this->request` is not set
- `\Exception` if `$this->request` is not a `Neos\Flow\Mvc\ActionRequest`

### `share()`

```php
protected function share(array $properties): void
```

Populates shared props that are automatically merged into every `inertia()` response within the same request. Call from middleware, `initializeAction()`, or any action — props persist across sub-actions and forwards.

```php
$this->share(['auth' => ['user' => $this->getCurrentUser()]]);
```

### `injectPageFactory()`

```php
public function injectPageFactory(PageFactory $pageFactory): void
```

Called by Flow's DI container. Do not call manually.

### `injectSharedPropsService()`

```php
public function injectSharedPropsService(SharedPropsService $sharedPropsService): void
```

Called by Flow's DI container. Do not call manually.

---

## `PageFactory`

**File:** `Classes/Factory/PageFactory.php`  
**Namespace:** `ZktSn0w\Inertia\Factory`

Creates fully resolved `Page` objects. Used by both the Trait API and Fusion API.

### `create()`

```php
public function create(string $component, array $props, ServerRequestInterface $httpRequest): Page
```

| Parameter | Type | Description |
|---|---|---|
| `$component` | `string` | Frontend component name |
| `$props` | `array` | Raw props (may contain `Closure`, `DeferProp`, or plain values) |
| `$httpRequest` | `ServerRequestInterface` | PSR-7 request, used for URL detection, partial reload filtering, etc. |

**What it does:**
1. Detects partial reloads via `X-Inertia-Partial-Component` / `X-Inertia-Partial-Data` headers
2. Resolves props: evaluates closures, defers `DeferProp` instances, filters for partial reloads
3. Merges shared props from `SharedPropsService`
4. Sets asset version from `InertiaAssetVersionService`
5. Sets URL from request URI
6. Returns fully resolved `Page` object

---

## `InertiaMiddleware`

**File:** `Classes/Http/Middleware/InertiaMiddleware.php`  
**Namespace:** `ZktSn0w\Inertia\Http\Middleware`  
**Implements:** `Psr\Http\Server\MiddlewareInterface`

PSR-15 middleware. Must be registered in `Settings.yaml` under `Neos.Flow.http.middlewares`.

### `process()`

```php
public function process(
    ServerRequestInterface $request,
    RequestHandlerInterface $next
): ResponseInterface
```

**Behavior:**
1. If no `X-Inertia` request header: returns `$next->handle($request)` unchanged.
2. Calls `$next->handle($request)`, adds `X-Inertia: true` and `Content-Type: application/json` to the response.
3. If `GET` request with `X-Inertia-Version` header that doesn't match the current asset version: returns `409 Conflict` with `X-Inertia-Location: <request-path>`.
4. If response is `302` and request method is `PUT`, `PATCH`, or `DELETE`: converts to `303 See Other`.
5. Adds `Vary: Accept` to the response.

---

## `InertiaErrorMiddleware`

**File:** `Classes/Http/Middleware/InertiaErrorMiddleware.php`  
**Namespace:** `ZktSn0w\Inertia\Http\Middleware`  
**Implements:** `Psr\Http\Server\MiddlewareInterface`

Catches unhandled exceptions during Inertia XHR requests. Returns JSON error responses instead of exposing raw PHP stack traces to the Inertia client.

Must be placed **before** `InertiaMiddleware` in the middleware chain.

**Configuration** (`Settings.yaml`):

```yaml
ZktSn0w:
  Inertia:
    errorHandling:
      errorComponent: null        # Inertia component for error pages (default: "Error")
      showDetailedErrors: true    # Include file/line/trace in error responses
```

**Behavior:**
1. Wraps `$next->handle($request)` in try/catch.
2. Non-Inertia requests: re-throws exception — Flow's default error handling applies.
3. Inertia XHR requests: catches `\Throwable`, builds Inertia `Page` with error props, returns JSON response with appropriate HTTP status code.
4. `StopActionException` (Flow internal redirect/forward): always re-throws.

---

## `SharedPropsService`

**File:** `Classes/Service/SharedPropsService.php`  
**Namespace:** `ZktSn0w\Inertia\Service`  
**Scope:** Flow singleton (`#[Flow\Scope('singleton')]`)

Per-request bag that holds shared Inertia props. Populated by middleware or controllers via `share()`, automatically merged into every `inertia()` response.

### `getProps()`

```php
public function getProps(): array
```

Returns all shared props. Called internally by the trait — you typically don't call this directly.

### `share()`

```php
public function share(array $props): void
```

Merges the given props into the shared bag. Later keys with the same name overwrite earlier ones.

---

## `AbstractSharedPropsMiddleware`

**File:** `Classes/Http/Middleware/Abstract/AbstractSharedPropsMiddleware.php`  
**Namespace:** `ZktSn0w\Inertia\Http\Middleware\Abstract`  
**Extends:** — *(abstract, implements `Psr\Http\Server\MiddlewareInterface`)*

Base PSR-15 middleware for cross-cutting shared data. Extend this class, implement `getSharedProps()`, and register the middleware in `Settings.yaml` after `inertia`. The shared props are automatically merged into every Inertia response.

```php
final class MySharedProps extends AbstractSharedPropsMiddleware
{
    protected function getSharedProps(ServerRequestInterface $request): array
    {
        return ['auth' => ['user' => $this->someService->getCurrentUser()]];
    }
}
```

### `getSharedProps()`

```php
abstract protected function getSharedProps(ServerRequestInterface $request): array
```

Implement to return the array of shared props. Receives the current PSR-7 request.

### `process()`

```php
public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
```

Calls `getSharedProps()` and feeds the result to `SharedPropsService::share()`, then delegates to the next handler. Pre-built — do not override.

---

## `InertiaAssetVersionService`

**File:** `Classes/Service/InertiaAssetVersionService.php`  
**Namespace:** `ZktSn0w\Inertia\Service`  
**Scope:** Flow singleton (`#[Flow\Scope('singleton')]`)

### `getAssetVersion()`

```php
public function getAssetVersion(): ?string
```

Reads `ZktSn0w.Inertia.assetVersioning.strategy` from Flow settings, instantiates the configured `StrategyInterface` class with its options, and returns the version string.

**Returns:** version string, or `null` if no strategy is configured or `getVersion()` returns null.

**Throws:** `\InvalidArgumentException` if the configured class does not implement `StrategyInterface`.

---

## `Domain\Page`

**File:** `Classes/Domain/Page.php`  
**Namespace:** `ZktSn0w\Inertia\Domain`  
**Implements:** `JsonSerializable`

Value object representing an Inertia page.

### Constructor

```php
public function __construct(string $component, array $props)
```

### `setVersion()`

```php
public function setVersion(string $version): void
```

Sets the asset version. Included in JSON output when set.

### `setUrl()`

```php
public function setUrl(string $url): void
```

Sets the current request URL. Included in JSON output when set.

### `setErrors()`

```php
public function setErrors(array $errors): void
```

Sets validation or error-bag data (Inertia error protocol). Keys map to form field names; values are arrays of error messages. An empty array serializes to `{}` (empty JSON object).

### `setDeferredProps()`

```php
public function setDeferredProps(array $deferredProps): void
```

Sets the deferred props map. Each entry is a group name → array of prop keys.

### `jsonSerialize()`

```php
public function jsonSerialize(): array
```

Returns the array used by `json_encode()`:

```json
{
  "component": "Products/Show",
  "props": {},
  "errors": {},
  "version": "abc123",
  "url": "/products/42",
  "deferredProps": { "default": ["stats"] }
}
```

`version`, `url`, and `deferredProps` keys are omitted if not set. `errors` defaults to `{}` (empty JSON object).

---

## `InertiaHelper` (Eel Helper)

**File:** `Classes/Eel/InertiaHelper.php`  
**Namespace:** `ZktSn0w\Inertia\Eel`  
**Implements:** `Neos\Eel\ProtectedContextAwareInterface`

Registered as `Inertia` in Fusion's default context via `Settings.yaml`:

```yaml
Neos:
  Fusion:
    defaultContext:
      Inertia: 'ZktSn0w\Inertia\Eel\InertiaHelper'
```

### `isInertiaRequest()`

```php
public function isInertiaRequest(ServerRequestInterface $request): bool
```

Returns `true` if the request has the `X-Inertia` header. Use in Fusion conditions:

```fusion
root.isInertiaRequest {
  condition = ${Inertia.isInertiaRequest(request.httpRequest)}
  renderer = ${Json.stringify(inertiaPage)}
}
```

---

## `App` Enum

**File:** `Classes/App.php`  
**Namespace:** `ZktSn0w\Inertia`  
**Backed type:** `string`

| Case | String value | Purpose |
|---|---|---|
| `App::HEADER` | `X-Inertia` | Detect Inertia requests; mark Inertia responses |
| `App::VERSION_HEADER` | `X-Inertia-Version` | Client sends current asset version; server echoes it |
| `App::INERTIA_LOCATION_HEADER` | `X-Inertia-Location` | Server sends redirect URL on 409 version mismatch |

Access the string value via `App::HEADER->value`.

---

## `Domain\AssetVersion\StrategyInterface`

**File:** `Classes/Domain/AssetVersion/StrategyInterface.php`  
**Namespace:** `ZktSn0w\Inertia\Domain\AssetVersion`

```php
interface StrategyInterface {
    public function getVersion(): ?string;
}
```

Implement this to create a custom asset versioning strategy. The constructor must accept `array $options` — this is how `InertiaAssetVersionService` passes the `options` from config.

---

## `InertiaPage` Fusion Prototype

**File:** `Resources/Private/Fusion/Prototypes/InertiaPage.fusion`  
**Extends:** `Neos.Neos:Page`

Fusion API core. Renders a full Neos page for initial loads, replaces with JSON for XHR requests.

```fusion
prototype(ZktSn0w.Inertia:InertiaPage) < prototype(Neos.Neos:Page) {
  @process.inertiaResponse = ${Inertia.isInertiaRequest(request.httpRequest) ? Json.stringify(inertiaPage) : value}
}
```

**How it works:**
- `Neos.Neos:Page` renders the full HTML document (head + body).
- `@process.inertiaResponse` runs after rendering: if `X-Inertia` header present, replaces entire output with `Json.stringify(inertiaPage)`. Otherwise passes through the HTML unchanged.

**Usage in Fusion:**

```fusion
Your.Package.PageController.index = ZktSn0w.Inertia:InertiaPage {
  head {
    stylesheets.site = afx`<link rel="stylesheet" href={StaticResource.uri('Your.Package', 'Public/assets/main.css')} />`
    javascripts.site = afx`<script type="module" defer src={StaticResource.uri('Your.Package', 'Public/assets/main.js')}></script>`
  }
  body = ZktSn0w.Inertia:InertiaBody
}
```

---

## `InertiaBody` Fusion Prototype

**File:** `Resources/Private/Fusion/Prototypes/InertiaBody.fusion`  
**Extends:** `Neos.Fusion:Component`

Built-in Fusion prototype — no separate package needed. Renders the Inertia mount point.

```fusion
prototype(ZktSn0w.Inertia:InertiaBody) < prototype(Neos.Fusion:Component) {
  id = "app"
  page = ${inertiaPage}

  renderer = Neos.Fusion:Tag {
    tagName = "div"
    attributes {
      id = ${props.id}
      data-page = ${Json.stringify(props.page)}
    }
  }
}
```

**Props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `id` | `string` | `"app"` | HTML `id` attribute on the div |
| `page` | `any` | `${inertiaPage}` | The `Page` object; JSON-serialized into `data-page`. Defaults to the `inertiaPage` Fusion context variable |

**Usage in Fusion:**

```fusion
body = ZktSn0w.Inertia:InertiaBody {
  page = ${inertiaPage}
}

# Or with no explicit page — defaults to ${inertiaPage} context variable:
body = ZktSn0w.Inertia:InertiaBody
```

The Inertia client bootstraps by reading `document.getElementById('app').dataset.page`.
