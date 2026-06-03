# ZktSn0w.Inertia — Roadmap

## Done

- **Trait-based render** — `Trait\Inertia` with `renderInertia()` replaces the old `AbstractInertiaController` + injectable service
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

- [ ] **Partial reload support** — handle `X-Inertia-Partial-Data` and `X-Inertia-Partial-Component` request headers. When present, filter props to only the requested keys and skip evaluating others. Prerequisite for lazy props.
- [ ] **Lazy props** — allow props to be wrapped as closures that are only evaluated during a partial reload that explicitly requests them. Reduces initial payload size.

### Redirects

- [ ] **`Inertia::location()`** — return a `409 Conflict` response with `X-Inertia-Location` header pointing to an external URL or non-Inertia route, triggering a full browser navigation from the client side.

### Error Handling

- [ ] **Error responses** — provide a way to render 4xx/5xx errors as Inertia pages (or fall back to standard Fusion error pages) rather than exposing raw PHP stack traces to Inertia clients.

### Testing

- [ ] **Unit tests** — middleware (header logic, version check, status codes), trait render logic (JSON vs HTML path), asset versioning strategies, `Page` serialization
- [ ] **Integration tests** — full request/response cycle for initial loads and XHR navigations

---

## Phase 2: Custom Root View

Currently `renderInertia()` hardcodes `$view->setFusionPath('App')`, so every Inertia response uses the same Fusion root. This prevents different layouts per controller or multiple Inertia apps on one site.

- [ ] **`setRootView(string $fusionPath)`** — method on the trait or a service to override the default root Fusion path
- [ ] **Per-controller root view** — property or method on the using controller
- [ ] **Per-render override** — optional parameter in `renderInertia()`
- [ ] **Configurable default** — make `'App'` overridable via `Settings.yaml`

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

- **Deferred props** — `Inertia::defer()` — load props after initial render
- **Merge props** — `Inertia::merge()` / `deepMerge()` — for infinite scroll and pagination
- **History encryption** — encrypted history state for sensitive page data
- **Prefetching** — link prefetch hints for faster navigation
- **Polling** — built-in polling for real-time data updates
