# Inertia Protocol Implementation

Reference: [Inertia Protocol Docs](https://inertiajs.com/docs/v2/core-concepts/the-protocol)

## Request Types

The Inertia protocol distinguishes two request types based on the presence of the `X-Inertia: true` request header.

| Request Type | `X-Inertia` header | Server response |
|---|---|---|
| Initial page load | absent | Full HTML document (view-rendered) |
| Subsequent XHR navigation | present | JSON `Page` object |

---

## Initial Page Load Flow

### Trait API

1. Browser requests a URL with no Inertia headers.
2. `InertiaMiddleware` sees no `X-Inertia` header → passes through to the controller unchanged.
3. Controller calls `inertia($component, $props)`.
4. Trait calls `PageFactory::create()` which builds a `Page` object (component, props, version, URL, shared props).
5. Trait assigns the `Page` to the view as `inertiaPage`.
6. View renders the full HTML document with `InertiaBody` mount point (`<div id="app" data-page="...">`).
8. The Inertia client library reads `data-page`, bootstraps the frontend framework, and mounts the component.

```
Browser GET /products
  → InertiaMiddleware: no X-Inertia → pass through
  → ProductsController::indexAction()
  → inertia('Products/Index', [...])
  → PageFactory::create('Products/Index', [...])
  → $view->assign('inertiaPage', $page)
  → calls view->render() → returns rendered result
  → Response 200, Content-Type: text/html
```

### Fusion API

1. Browser requests a URL with no Inertia headers.
2. `InertiaMiddleware` sees no `X-Inertia` header → passes through.
3. Controller calls `PageFactory::create()`, assigns `Page` to view as `inertiaPage`.
4. Fusion renders `InertiaPage` prototype (extends `Neos.Neos:Page`).
5. `@process.inertiaResponse`: no X-Inertia header → passes through full HTML.
6. Full Neos page renders with `InertiaBody` mount point.

```
Browser GET /rootcase/index
  → InertiaMiddleware: no X-Inertia → pass through
  → RootCaseController::indexAction()
  → PageFactory::create('RootCase/Index', [...])
  → $view->assign('inertiaPage', $page)
  → Fusion: InertiaPage → @process passes through → full Neos HTML
  → Response 200, Content-Type: text/html
```

---

## Subsequent XHR Navigation Flow

1. Inertia client intercepts a link click and sends an XHR with `X-Inertia: true` and `X-Inertia-Version: <current-asset-version>`.
2. `InertiaMiddleware` detects `X-Inertia` header, sets `Content-Type: application/json`.
3. Middleware checks version: if `X-Inertia-Version` ≠ current server version → returns `409 Conflict` with `X-Inertia-Location: <url>` header.
4. If version matches, the controller runs normally.

### Trait API

5. `inertia()` detects `X-Inertia` header and returns a JSON response directly. Fusion never runs.

```
Inertia XHR GET /products/42
  Headers: X-Inertia: true, X-Inertia-Version: abc123
  → InertiaMiddleware: Content-Type → application/json, version OK
  → ProductsController::showAction()
  → inertia('Products/Show', ['product' => ...])
  → PageFactory::create() with version + URL
  → returns JSON Response: {"component":"Products/Show","props":{...},"version":"abc123","url":"/products/42"}
  → Response 200, Content-Type: application/json
```

### Fusion API

5. Controller assigns `inertiaPage` to view. Fusion runs.
6. `InertiaPage` prototype's `@process.inertiaResponse` detects `X-Inertia` header → replaces entire HTML output with `Json.stringify(inertiaPage)`.

```
Inertia XHR GET /rootcase/index
  Headers: X-Inertia: true, X-Inertia-Version: poaaaaa
  → InertiaMiddleware: Content-Type → application/json, version OK
  → RootCaseController::indexAction()
  → PageFactory::create('RootCase/Index', [...])
  → $view->assign('inertiaPage', $page)
  → Fusion renders InertiaPage → @process replaces HTML with JSON
  → Response 200, Content-Type: application/json
    Body: {"component":"RootCase/Index","props":{...},"version":"poaaaaa","url":"/rootcase/index"}
```

---

## InertiaMiddleware Step-by-Step

**File:** `Classes/Http/Middleware/InertiaMiddleware.php`

```
process(request, next):
  1. if request has no X-Inertia header:
       return next.handle(request)          ← non-Inertia request, skip all logic

  2. response = next.handle(request)        ← let controller run

  3. response.addHeader(X-Inertia, 'true')            ← mark response as Inertia
     response.addHeader(Content-Type, 'application/json')  ← set JSON content type

  4. if GET && X-Inertia-Version header present && version != server version:
       return response.withStatus(409)
                      .addHeader(X-Inertia-Location, request.uri.path)
                      ← client will reload

  5. if current version is set:
       response.addHeader(X-Inertia-Version, version)  ← echo version back to client

  6. if response.status == 302 && method in [PUT, PATCH, DELETE]:
       response = response.withStatus(303)  ← browser will GET the redirect target

  7. response.addHeader(Vary, 'Accept')     ← cache differentiation

  8. return response
```

---

## `inertia()` (Trait API) Step-by-Step

**File:** `Classes/Trait/Inertia.php`

```
inertia(component, props):
  1. assert $this->request is set and is ActionRequest
  2. httpRequest = request.getHttpRequest()
  3. page = PageFactory::create(component, props, httpRequest)
       → resolves props (closures, deferred, shared)
       → sets version from InertiaAssetVersionService
       → sets url from request URI
  4. if httpRequest has X-Inertia header:
       return new Response(200, [], json_encode(page))
       (headers set by InertiaMiddleware)
     else:
       view.assign('inertiaPage', page)
       return wrapRenderResult(view.render())  ← renders full page
```

---
## `InertiaPage` (Fusion API) Step-by-Step

**File:** `Resources/Private/Fusion/Prototypes/InertiaPage.fusion`

```
InertiaPage rendering (extends Neos.Neos:Page):
  1. Neos.Neos:Page renders full HTML (head + body)
  2. @process.inertiaResponse runs after full render:
       @process.inertiaResponse = ${Inertia.isInertiaRequest(request.httpRequest) ? Json.stringify(inertiaPage) : value}
       → X-Inertia present: replaces entire output with JSON
       → X-Inertia absent: passes through full HTML unchanged
```

---

## `Page` Domain Object

**File:** `Classes/Domain/Page.php`

Implements `JsonSerializable`. JSON output:

```json
{
  "component": "Products/Show",
  "props": { "product": { ... } },
  "version": "abc123",
  "url": "/products/42"
}
```

`version` and `url` are omitted if not set.

| Field | Type | Description |
|---|---|---|
| `component` | `string` | Frontend component identifier |
| `props` | `array` | Data passed to the frontend component |
| `version` | `string\|null` | Current asset version (optional) |
| `url` | `string\|null` | Current request URL (optional) |

---

## `App` Enum — Header Constants

**File:** `Classes/App.php`

| Case | Value | Usage |
|---|---|---|
| `App::HEADER` | `X-Inertia` | Detect/mark Inertia requests and responses |
| `App::VERSION_HEADER` | `X-Inertia-Version` | Client sends current asset version |
| `App::INERTIA_LOCATION_HEADER` | `X-Inertia-Location` | Server sends redirect target on 409 |

---

## `InertiaBody` Fusion Component

**File:** `Resources/Private/Fusion/Prototypes/InertiaBody.fusion`

Built-in Fusion prototype — no separate package needed.

Renders:
```html
<div id="app" data-page="{JSON-encoded Page object}"></div>
```

Props:
- `id` (string, default `"app"`) — the `id` attribute on the div
- `page` (any, default `${inertiaPage}`) — the `Page` object; serialized via `Json.stringify()`

The Inertia client reads `document.getElementById('app').dataset.page` to bootstrap.

---
## `InertiaPage` Fusion Prototype

**File:** `Resources/Private/Fusion/Prototypes/InertiaPage.fusion`

Extends `Neos.Neos:Page`. Renders a full Neos page for initial loads, returns JSON for XHR:

```fusion
prototype(ZktSn0w.Inertia:InertiaPage) < prototype(Neos.Neos:Page) {
  @process.inertiaResponse = ${Inertia.isInertiaRequest(request.httpRequest) ? Json.stringify(inertiaPage) : value}
}
```
