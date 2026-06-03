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

1. Browser requests a URL with no Inertia headers.
2. `InertiaMiddleware` sees no `X-Inertia` header → passes through to the controller unchanged.
3. Controller calls `inertia($component, $props, $viewProps)`.
4. Trait builds a `Page` object (component, props, version, URL).
5. Trait assigns the `Page` to the view as `page`.
6. Any `$viewProps` are assigned to the view as additional variables.
7. View renders the full HTML document. The mount point (`<div id="app" data-page="...">`) must be present — use `ZktSn0w.Inertia:InertiaBody` (via `ZktSn0w.Inertia.FusionAdapter`) for Fusion, or render it manually with any other view.
8. The Inertia client library reads `data-page`, bootstraps the frontend framework, and mounts the component.

```
Browser GET /products
  → InertiaMiddleware: no X-Inertia → pass through
  → ProductsController::indexAction()
  → inertia('Products/Index', [...])
  → Page::create('Products/Index', [...])
  → $view->assign('page', $page)
  → $view->render() → full HTML
  → Response 200, Content-Type: text/html
```

---

## Subsequent XHR Navigation Flow

1. Inertia client intercepts a link click and sends an XHR with `X-Inertia: true` and `X-Inertia-Version: <current-asset-version>`.
2. `InertiaMiddleware` detects `X-Inertia` header, lets the request proceed.
3. Middleware checks version: if `X-Inertia-Version` ≠ current server version → returns `409 Conflict` with `X-Inertia-Location: <url>` header. Client performs a full page reload to that URL.
4. If version matches, the controller runs normally.
5. `inertia()` detects the `X-Inertia` header on the request and returns a JSON response instead of HTML.
6. Middleware appends `X-Inertia: true` and `Vary: Accept` to the response.

```
Inertia XHR GET /products/42
  Headers: X-Inertia: true, X-Inertia-Version: abc123
  → InertiaMiddleware: detects Inertia request
    → version check: abc123 == server version? → yes, continue
  → ProductsController::showAction()
  → inertia('Products/Show', ['product' => ...])
  → Page::create(...) with version + URL set
  → json_encode($page)
  → Response 200, Content-Type: application/json
    Body: {"component":"Products/Show","props":{...},"version":"abc123","url":"/products/42"}
  → InertiaMiddleware appends X-Inertia: true, Vary: Accept
```

---

## InertiaMiddleware Step-by-Step

**File:** `Classes/Http/Middleware/InertiaMiddleware.php`

```
process(request, next):
  1. if request has no X-Inertia header:
       return next.handle(request)          ← non-Inertia request, skip all logic

  2. response = next.handle(request)        ← let controller run

  3. response.addHeader(X-Inertia, 'true')  ← mark response as Inertia

  4. if GET && X-Inertia-Version header present && version != server version:
       return response.withStatus(409)
                      .addHeader(X-Inertia-Location, request.uri.path)
                      ← client will reload

  5. if response.status == 302 && method in [PUT, PATCH, DELETE]:
       response = response.withStatus(303)  ← browser will GET the redirect target

  6. response.addHeader(Vary, 'Accept')     ← cache differentiation

  7. return response
```

---

## `inertia()` Step-by-Step

**File:** `Classes/Trait/Inertia.php`

```
inertia(component, props, viewProps):
  1. assert $this->request is set and is ActionRequest
  2. url = request.httpRequest.uri
  3. assetVersion = InertiaAssetVersionService.getAssetVersion()
  4. page = Page::create(component, props)
  5. if assetVersion set: page.setVersion(assetVersion)
  6. if url set: page.setUrl(url)
  7. if request has X-Inertia header:
       headers = [X-Inertia-Version: assetVersion, X-Inertia: true, Vary: X-Inertia, Content-Type: application/json]
       body = json_encode(page)
     else:
       view.assign('page', page)
       view.assignMultiple(viewProps)
       rendered = view.render()
       body = (string) rendered
  8. return new GuzzleHttp Response(200, headers, body)
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

> Provided by `ZktSn0w.Inertia.FusionAdapter`. Only relevant for Fusion-based setups.

**File:** `ZktSn0w.Inertia.FusionAdapter/Resources/Private/Fusion/Content/InertiaBody.fusion`

Renders:
```html
<div id="app" data-page="{JSON-encoded Page object}"></div>
```

Props:
- `id` (string, default `"app"`) — the `id` attribute on the div
- `page` (any) — the `Page` object from the controller; serialized via `Json.stringify()`

The Inertia client reads `document.getElementById('app').dataset.page` to bootstrap.
