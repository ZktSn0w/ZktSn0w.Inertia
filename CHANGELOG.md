# Changelog

## 0.1.0 — 2026-06-08

### Added
- `Inertia` trait with `inertia()` method for any Flow ActionController
- `InertiaMiddleware` — version mismatch (409), 302→303 fix, `Vary: Accept`
- Asset versioning: `SettingStrategy`, `FileStrategy`, `ManifestStrategy`
- `StrategyInterface` for custom strategies
- `Page` domain object (JsonSerializable)
- `App` enum for header constants
