# Architecture

## Package Structure

```
ZktSn0w.Inertia/
├── Classes/
│   ├── App.php                                  # Enum: Inertia HTTP header constants
│   ├── Domain/
│   │   ├── Page.php                             # Page value object (component + props + meta)
│   │   ├── Prop/
│   │   │   ├── DeferProp.php                    # Deferred prop wrapper
│   │   │   └── Deferrable.php                   # Interface for deferrable props
│   │   └── AssetVersion/
│   │       ├── StrategyInterface.php            # Contract for versioning strategies
│   │       ├── SettingStrategy.php              # Static value from Settings.yaml
│   │       ├── FileStrategy.php                 # Version read from a plain text file
│   │       └── ManifestStrategy.php             # Version from JSON manifest file
│   ├── Eel/
│   │   └── InertiaHelper.php                    # Eel helper: Inertia.isInertiaRequest()
│   ├── Factory/
│   │   └── PageFactory.php                      # Creates fully resolved Page objects
│   ├── Http/
│   │   └── Middleware/
│   │       ├── AbstractSharedPropsMiddleware.php # Base for shared prop middleware
│   │       ├── InertiaErrorMiddleware.php        # Catches exceptions, returns JSON errors
│   │       └── InertiaMiddleware.php             # PSR-15 middleware for protocol compliance
│   ├── Service/
│   │   ├── InertiaAssetVersionService.php       # Singleton: resolves configured strategy
│   │   └── SharedPropsService.php               # Per-request bag for shared Inertia props
│   └── Trait/
│       └── Inertia.php                          # Trait API: inertia() render logic
├── Configuration/
│   └── Settings.yaml                            # Asset versioning, error handling, Eel registration
└── Resources/
    └── Private/
        └── Fusion/
            ├── Root.fusion                      # root.isInertiaRequest condition
            └── Prototypes/
                ├── InertiaBody.fusion            # <script data-page="..."> + mount <div>
                └── InertiaPage.fusion            # Fusion API: Neos page + JSON switching
```

## Component Map

### Trait API Path

```
Controller (user code)
  └── uses Trait\Inertia
        ├── calls inertia()
        │     ├── reads $this->request (ActionRequest)
        │     ├── calls PageFactory::create()
        │     │     ├── resolves props (closures, deferred, shared)
        │     │     ├── calls InertiaAssetVersionService::getAssetVersion()
        │     │     └── returns Domain\Page
        │     ├── X-Inertia header present → returns JSON Response
        │     └── no X-Inertia header → assigns inertiaPage to view, calls view->render()
        └── injects PageFactory, SharedPropsService via Flow DI

FusionView (initial load only)
  └── renders InertiaBody prototype
        └── <script data-page="app" type="application/json">{Json.stringify(page)}</script><div id="app">
```

### Fusion API Path

```
Controller (user code)
  ├── injects PageFactory via Flow DI
  ├── calls PageFactory::create() → Domain\Page
  ├── assigns page to view as inertiaPage
  └── returns void (Fusion handles everything)

Fusion (InertiaPage prototype)
  ├── extends Neos.Neos:Page
  ├── @process.inertiaResponse
  │     ├── X-Inertia header present → Json.stringify(inertiaPage)
  │     └── no X-Inertia header → pass through full HTML
  └── body = InertiaBody { page = ${inertiaPage} }
        └── <script data-page="app" type="application/json">{Json.stringify(page)}</script><div id="app">
```

### Middleware Chain

```
InertiaErrorMiddleware (outermost)
  ├── try/catch around entire chain
  ├── X-Inertia present + exception → JSON error response
  └── no X-Inertia → re-throw for Flow's default handler

InertiaMiddleware
  ├── detects X-Inertia header
  ├── sets Content-Type: application/json, X-Inertia: true for all XHR responses
  ├── echoes X-Inertia-Version on non-409 responses
  ├── version mismatch → 409 + X-Inertia-Location
  ├── converts 302 → 303 for mutating methods
  └── adds Vary: Accept header
```

## Dependency Graph

```
Trait\Inertia
  → PageFactory (Flow DI injection)
  → SharedPropsService (Flow DI injection)
  → App (enum, for header detection)

PageFactory
  → SharedPropsService (Flow DI injection)
  → InertiaAssetVersionService (Flow DI injection)
  → Domain\Page (direct instantiation)

InertiaMiddleware
  → InertiaAssetVersionService (Flow DI injection)
  → App (enum, for header names)

InertiaErrorMiddleware
  → InertiaAssetVersionService (Flow DI injection)
  → Domain\Page (error response construction)
  → App (enum, for header detection)

InertiaHelper (Eel)
  → App (enum, for header name)

InertiaAssetVersionService
  → StrategyInterface (instantiated from settings via class name)
  → Settings: ZktSn0w.Inertia.assetVersioning.strategy
```

## Design Decisions

**Dual API — Trait + Fusion**
Two integration styles for different use cases. The Trait API is controller-centric: call `inertia()` and get a response. The Fusion API is Fusion-centric: assign a `Page` to the view, let the `InertiaPage` prototype handle JSON/HTHL switching via `@process`. Both use `PageFactory` internally — same protocol output, different developer experience.

**Trait instead of abstract controller**
The render logic lives in `Trait\Inertia` rather than an `AbstractInertiaController`. This allows mixing Inertia rendering into controllers that already extend other base classes (e.g. Neos backend controllers), without forcing a specific inheritance chain.

**Fusion API for Neos CMS integration**
The `InertiaPage` prototype extends `Neos.Neos:Page` and uses `@process` to conditionally transform the rendered output. When the request has `X-Inertia` header, the full HTML page is replaced with JSON. This allows Inertia to work within Neos CMS page rendering where Fusion always runs.

**`PageFactory` — shared between both APIs**
Both the Trait API and Fusion API use `PageFactory::create()` to build `Page` objects. This centralizes URL detection, asset version injection, shared props merging, deferred prop resolution, and partial reload filtering. No duplicated logic.

**Strategy pattern for asset versioning**
`InertiaAssetVersionService` reads a `class` + `options` config pair and instantiates the strategy at runtime. Adding a new versioning source requires only implementing `StrategyInterface` — no changes to the service or middleware.

**`App` enum for header constants**
`App::HEADER`, `App::VERSION_HEADER`, and `App::INERTIA_LOCATION_HEADER` centralize all Inertia protocol header strings. Prevents typos and makes header usage grep-able.

**`Page` as a value object**
`Domain\Page` is constructed with component + props and implements `JsonSerializable`. It is the single source of truth for the data structure passed both to the JSON response (XHR) and to the Fusion view (initial load via `<script type="application/json">` tag, v3 protocol).

**Error handling via middleware**
`InertiaErrorMiddleware` wraps the entire request in try/catch. On Inertia XHR requests, exceptions are converted to JSON error responses with proper status codes. Non-Inertia requests pass through to Flow's default error handler. This prevents raw PHP stack traces from reaching the Inertia client.
