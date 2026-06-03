# Architecture

## Package Structure

```
ZktSn0w.Inertia/
├── Classes/
│   ├── App.php                              # Enum: Inertia HTTP header constants
│   ├── Domain/
│   │   ├── Page.php                         # Page object (component + props + meta)
│   │   └── AssetVersion/
│   │       ├── StrategyInterface.php        # Contract for versioning strategies
│   │       ├── SettingStrategy.php          # Static value from Settings.yaml
│   │       ├── FileStrategy.php             # Version read from a plain text file
│   │       └── ManifestStrategy.php         # Version from JSON manifest file
│   ├── Http/
│   │   └── Middleware/
│   │       └── InertiaMiddleware.php        # PSR-15 middleware for protocol compliance
│   ├── Service/
│   │   └── InertiaAssetVersionService.php  # Singleton: resolves configured strategy
│   └── Trait/
│       └── Inertia.php                      # Core render logic, added to controllers
└── Configuration/
    └── Settings.yaml                        # Default: SettingStrategy with placeholder value
```

## Component Map

```
Controller (user code)
  └── uses Trait\Inertia
        ├── calls renderInertia()
        │     ├── reads $this->request (ActionRequest)
        │     ├── reads $this->view (any ViewInterface impl)
        │     ├── calls InertiaAssetVersionService::getAssetVersion()
        │     │     └── instantiates configured StrategyInterface impl
        │     ├── creates Domain\Page
        │     └── returns GuzzleHttp Response (JSON or rendered HTML)
        └── injects InertiaAssetVersionService via injectAssetVersionService()

InertiaMiddleware (PSR-15)
  ├── depends on InertiaAssetVersionService
  ├── checks X-Inertia header presence
  ├── checks asset version mismatch → 409
  ├── converts 302 → 303 for mutating methods
  └── adds Vary: Accept header

InertiaBody (Fusion) — ZktSn0w.Inertia.FusionAdapter
  └── renders <div id="app" data-page="{...}">
```

## Dependency Graph

```
Trait\Inertia
  → InertiaAssetVersionService (Flow DI injection)
  → Domain\Page (direct instantiation)
  → App (enum, for header detection)

InertiaMiddleware
  → InertiaAssetVersionService (Flow DI injection)
  → App (enum, for header names)

InertiaAssetVersionService
  → StrategyInterface (instantiated from settings via class name)
  → Settings: ZktSn0w.Inertia.assetVersioning.strategy
```

## Design Decisions

**Trait instead of abstract controller**
The render logic lives in `Trait\Inertia` rather than an `AbstractInertiaController`. This allows mixing Inertia rendering into controllers that already extend other base classes (e.g. Neos backend controllers), without forcing a specific inheritance chain.

**Strategy pattern for asset versioning**
`InertiaAssetVersionService` reads a `class` + `options` config pair and instantiates the strategy at runtime. Adding a new versioning source requires only implementing `StrategyInterface` — no changes to the service or middleware.

**`App` enum for header constants**
`App::HEADER`, `App::VERSION_HEADER`, and `App::INERTIA_LOCATION_HEADER` centralize all Inertia protocol header strings. Prevents typos and makes header usage grep-able.

**`Page` as a value object**
`Domain\Page` is constructed with component + props and implements `JsonSerializable`. It is the single source of truth for the data structure passed both to the JSON response (XHR) and to the Fusion view (initial load via `data-page` attribute).

**No service class for rendering**
The previous design had an injectable `Inertia` service with a `render()` method. This was removed in favor of the trait, which avoids the overhead of a dedicated service and keeps the render logic co-located with its dependencies (request, view).
