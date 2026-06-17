# Changelog

## 2.0.0 — 2026-06-12

### Breaking
- **Inertia.js v3 protocol.** Initial page data now delivered via `<script type="application/json">` tag instead of `data-page` attribute on the mount div
- `InertiaBody` Fusion prototype now renders both the script tag and mount div (`Neos.Fusion:Join` instead of single `Neos.Fusion:Tag`)
- **`inertia()` no longer returns `null`.** Calls `view->render()` and returns the rendered result. Return type: `ResponseInterface`
- **Header logic moved to middleware.** Trait and `InertiaErrorMiddleware` no longer set protocol headers. `InertiaMiddleware` is now outermost and handles `X-Inertia`, `Content-Type`, `X-Inertia-Version` for all XHR responses (including errors)
- **Middleware order changed.** `InertiaMiddleware` wraps `InertiaErrorMiddleware` (`position: 'after inertia'` instead of `'before inertia'`)
- Package version jumps from 1.x to 2.x to match Inertia.js v3

### Added
- `Page::setClearHistory()` and `Page::setEncryptHistory()` — only included in JSON output when `true` (v3 protocol)
- `$request` and `$view` documented as trait `@property` for static analysis

## 1.0.0 — 2026-06-12

### Added
- First stable release targeting Inertia.js v2 protocol
- `data-page` attribute on mount div for initial page data delivery

## 0.3.1 — 2026-06-08

### Fixed
- Lazy props (closures) were evaluated on initial load instead of only on partial reloads that explicitly request them

## 0.3.0 — 2026-06-08

### Added
- `SharedPropsService` — per-request singleton for shared Inertia props
- `share()` method on the `Inertia` trait — populate shared props from controllers or `initializeAction()`
- `AbstractSharedPropsMiddleware` — base PSR-15 middleware for cross-cutting shared data
- Shared props automatically merged into every `inertia()` response

## 0.2.1 — 2026-06-08

### Fixed
- Page `url` now uses `getUri()->getPath()` instead of the full URI, matching the Inertia protocol spec (path only)

## 0.2.0 — 2026-06-08

### Added
- `DeferProp` — wrap slow props in a closure to exclude them from the initial response; the Inertia client fetches them in a follow-up XHR
- `Deferrable` interface for custom deferred prop implementations
- Partial reload support — props filtered by `X-Inertia-Partial-Data` header; deferred props grouped by name in the `Page` payload
- `App::PARTIAL_COMPONENT` and `App::PARTIAL_DATA` header constants

## 0.1.0 — 2026-06-08

### Added
- `Inertia` trait with `inertia()` method for any Flow ActionController
- `InertiaMiddleware` — version mismatch (409), 302→303 fix, `Vary: Accept`
- Asset versioning: `SettingStrategy`, `FileStrategy`, `ManifestStrategy`
- `StrategyInterface` for custom strategies
- `Page` domain object (JsonSerializable)
- `App` enum for header constants


