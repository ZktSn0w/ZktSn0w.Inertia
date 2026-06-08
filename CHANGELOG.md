# Changelog

## 0.1.0 — 2026-06-08

### Added
- `Inertia` trait with `inertia()` method for any Flow ActionController
- `InertiaMiddleware` — version mismatch (409), 302→303 fix, `Vary: Accept`
- Asset versioning: `SettingStrategy`, `FileStrategy`, `ManifestStrategy`
- `StrategyInterface` for custom strategies
- `Page` domain object (JsonSerializable)
- `App` enum for header constants

## 0.2.0 — 2026-06-08

### Added
- `DeferProp` — wrap slow props in a closure to exclude them from the initial response; the Inertia client fetches them in a follow-up XHR
- `Deferrable` interface for custom deferred prop implementations
- Partial reload support — props filtered by `X-Inertia-Partial-Data` header; deferred props grouped by name in the `Page` payload
- `App::PARTIAL_COMPONENT
