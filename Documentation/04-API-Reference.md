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

The trait self-injects `InertiaAssetVersionService` via Flow's DI mechanism. You do not need to inject it manually.

### `inertia()`

```php
private function inertia(
    string $component,
    array $props = [],
    array $viewProps = []
): ResponseInterface
```

| Parameter | Type | Description |
|---|---|---|
| `$component` | `string` | Frontend component name, e.g. `'Products/Show'` |
| `$props` | `array` | Passed to the frontend component as props |
| `$viewProps` | `array` | Passed to the view via `assignMultiple()` on initial load (server-side only) |

**Returns:** `Psr\Http\Message\ResponseInterface` — either a JSON response (XHR) or an HTML response (initial load).

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

### `injectAssetVersionService()`

```php
public function injectAssetVersionService(InertiaAssetVersionService $assetVersionService): void
```

Called by Flow's DI container. Do not call manually.

### `injectSharedPropsService()`

```php
public function injectSharedPropsService(SharedPropsService $sharedPropsService): void
```

Called by Flow's DI container. Do not call manually.

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
2. Calls `$next->handle($request)` and adds `X-Inertia: true` to the response.
3. If `GET` request with `X-Inertia-Version` header that doesn't match the current asset version: returns `409 Conflict` with `X-Inertia-Location: <request-path>`.
4. If response is `302` and request method is `PUT`, `PATCH`, or `DELETE`: converts to `303 See Other`.
5. Adds `Vary: Accept` to the response.

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

### `Page::create()`

```php
public static function create(string $component, array $props): self
```

Named constructor. Equivalent to `new Page($component, $props)`.

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

### `jsonSerialize()`

```php
public function jsonSerialize(): array
```

Returns the array used by `json_encode()`:

```json
{
  "component": "Products/Show",
  "props": {},
  "version": "abc123",
  "url": "/products/42"
}
```

`version` and `url` keys are omitted if not set.

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

## `InertiaBody` Fusion Prototype

> Provided by the `ZktSn0w.Inertia.FusionAdapter` package. Install `zktsn0w/inertia-fusionadapter` to use this prototype.

**File:** `ZktSn0w.Inertia.FusionAdapter/Resources/Private/Fusion/Content/InertiaBody.fusion`

```fusion
prototype(ZktSn0w.Inertia:InertiaBody) < prototype(Neos.Fusion:Component) {
  @propTypes {
    id = ${PropTypes.string}
    page = ${PropTypes.any}
  }

  id = "app"
  page = null

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
| `page` | `any` | `null` | The `Page` object from the controller; JSON-serialized into `data-page` |

**Usage in Fusion:**

```fusion
body.content.main = ZktSn0w.Inertia:InertiaBody {
  page = ${page}
}
```

The Inertia client bootstraps by reading `document.getElementById('app').dataset.page`.
