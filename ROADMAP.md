# ZktSn0w.Inertia — Roadmap

## Done

- **Trait-based render** — `Trait\Inertia` with `inertia()` replaces the old `AbstractInertiaController` + injectable service
- **`App` enum** — header constants centralized, no more magic strings
- **`Page` domain object** — `JsonSerializable`, holds component/props/version/URL
- **`InertiaMiddleware`** — version mismatch → 409, 302 → 303 for mutating methods, `Vary: Accept`
- **Asset versioning (all 3 strategies)** — `SettingStrategy`, `FileStrategy`, `ManifestStrategy`
- **`StrategyInterface`** — clean extension point for custom versioning
- **Neos Flow ^9.0 requirement**

---

## Phase 1: MVP — Core Protocol Completion

### Shared Data

- [ ] **`Inertia::share()`** — make data available globally across all responses (auth user, flash messages, app config) without passing them in every controller action. Store in a singleton service, merge into props on every `renderInertia()` call.

### Partial Reloads

- [ ] **Partial reload support** — handle `X-Inertia-Partial-Data` and `X-Inertia-Partial-Component` request headers. When present, filter props to only the requested keys and skip evaluating others. Prerequisite for deferred props.
- [ ] **Lazy props** — allow props to be wrapped as closures that are only evaluated during a partial reload that explicitly requests them. Reduces initial payload size.
- [ ] **Deferred props** — `DeferProp` wrapper class; props excluded from initial load and fetched in follow-up partial-reload XHRs grouped by name. See `Documentation/SpecSheets/DeferredProps.md`.

### Redirects

- [ ] **`Inertia::location()`** — return a `409 Conflict` response with `X-Inertia-Location` header pointing to an external URL or non-Inertia route, triggering a full browser navigation from the client side.

### Error Handling

- [ ] **Error responses** — provide a way to render 4xx/5xx errors as Inertia pages (or fall back to standard Fusion error pages) rather than exposing raw PHP stack traces to Inertia clients.

### Testing

- [ ] **Unit tests** — middleware (header logic, version check, status codes), trait render logic (JSON vs HTML path), asset versioning strategies, `Page` serialization
- [ ] **Integration tests** — full request/response cycle for initial loads and XHR navigations

---

## Phase 2: Custom Root View

Goal: support different layouts per controller or multiple Inertia apps on one site. Currently `setFusionPath()` is called by the consuming controller in `initializeView()` — the trait itself has no concept of a root view. How to provide this generically is open.

Possible directions:
- [ ] **Configurable default** — make the default Fusion path overridable via `Settings.yaml` so the trait can set it without controller boilerplate
- [ ] **Per-render override** — optional `$rootView` parameter in `inertia()` for one-off overrides
- [ ] **Per-controller property** — `$inertiaRootView` property on the using controller that the trait reads in `initializeView()`
- [ ] **Trait method** — `setRootView(string $fusionPath)` callable before `inertia()` to change the active root for that request

---

## Phase 3: Server-Side Rendering (SSR)

Render Inertia page components on the server for improved SEO and first-paint performance.

- [ ] **SSR service** — sends the `Page` object to a Node.js SSR server, receives rendered HTML
- [ ] **SSR integration** — inject SSR-rendered HTML into the root `<div>` on initial page loads
- [ ] **SSR toggle** — enable/disable via `Settings.yaml`
- [ ] **Head management** — support `<Head>` component output so SSR can inject `<title>` and `<meta>` tags into the Fusion document head
- [ ] **Graceful fallback** — client-side rendering when SSR server is unavailable

---

## Future (Post-Phase 3)

Inertia v2 protocol features:

- **Merge props** — `Inertia::merge()` / `deepMerge()` — for infinite scroll and pagination
- **History encryption** — encrypted history state for sensitive page data
- **Prefetching** — link prefetch hints for faster navigation
- **Polling** — built-in polling for real-time data updates
