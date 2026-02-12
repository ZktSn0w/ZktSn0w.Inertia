# ZktSn0w.Inertia - Roadmap

## Phase 1: MVP - Stable & Complete Inertia Protocol

> Goal: Get the adapter fully working with all core Inertia protocol features and proper error handling.

### Core Protocol

- [ ] **Shared Data** - Implement `Inertia::share()` to make data available globally across all responses (auth user, flash messages, app config) without passing them in every controller action
- [ ] **Validation Error Handling** - Automatically share server-side validation errors as props so frontend forms can display field-level errors (follow Inertia convention of passing `errors` prop) (Unsure if this is needed for a working core adapter)
- [ ] **Flash Messages** - Support sharing session flash messages as Inertia props, so redirects with flash data work seamlessly
- [ ] **Partial Reloads** - Support `X-Inertia-Partial-Data` and `X-Inertia-Partial-Component` headers so the client can request only specific props instead of the full page payload
- [ ] **Lazy / Optional Props** - Allow props to be marked as lazy (closures that are only evaluated when explicitly requested via partial reload) to reduce initial page load data

### Asset Versioning
- [ ] **Setting strategy** - Finish the implementation
- [ ] **FILE strategy** - Finish the FILE-based asset versioning strategy (currently stubbed/commented out)
- [ ] **MANIFEST strategy** - Implement JSON manifest-based versioning (read `version` key from a manifest file)
- [ ] **Version mismatch handling** - Respond with `409 Conflict` + `X-Inertia-Location` header when asset versions don't match, triggering a full page reload on the client

### CSRF Protection

- [ ] **CSRF token forwarding** - Ensure CSRF tokens from Neos Flow are available to the Inertia client (e.g. via cookie or shared data), so forms and AJAX requests include the token automatically
- [ ] **Token mismatch handling** - On CSRF mismatch, redirect back with a flash message ("Page expired, please try again") instead of showing a raw error page

### Redirects

- [ ] **POST/PUT/PATCH/DELETE redirect fix** - Verify and test that `302 -> 303` status code conversion works correctly for all non-GET methods (partially implemented in middleware)
- [ ] **External redirects** - Support `Inertia::location()` for full page visits to external URLs or non-Inertia routes

### Error Handling

- [ ] **Error responses** - Provide proper error page rendering for 4xx/5xx errors that works with the Inertia client (modal or dedicated error component)
- [ ] **Development error page** - In development mode, show detailed error information; in production, show a user-friendly error page

### Forms & File Uploads

- [ ] **Form method spoofing** - Support `_method` field for PUT/PATCH/DELETE via POST (standard for HTML forms)
- [ ] **Multipart file uploads** - Ensure file uploads via `multipart/form-data` work correctly through the Inertia middleware

### Testing & Documentation

- [ ] **Unit tests** - Test Inertia service (render, shared data, redirects), middleware (version check, status codes, headers), and asset versioning strategies
- [ ] **Integration tests** - Test full request/response cycle for both initial page loads and subsequent XHR requests
- [ ] **Updated README** - Document all MVP features with usage examples

---

## Phase 2: Custom Root View Support

> Goal: Allow developers to define their own root Fusion view per controller or per action, instead of being locked into a single hardcoded `App` Fusion path.

### Problem

Currently `Inertia::render()` hardcodes `$view->setFusionPath('App')`, meaning every Inertia response uses the same Fusion root prototype. This prevents:
- Different layouts for different sections (admin vs. public)
- Multiple independent Inertia apps on the same site
- Mixing Inertia pages with traditional Fusion-rendered pages

### Implementation

- [ ] **`setRootView()` method** - Add `Inertia::setRootView(string $fusionPath)` to override the default root Fusion path, matching the convention from other Inertia adapters (Laravel's `Inertia::setRootView()`)
- [ ] **Per-controller root view** - Allow setting the root view in the controller (e.g. via a property or method on `AbstractInertiaController`)
- [ ] **Per-render root view** - Accept an optional root view parameter in `Inertia::render()` for per-action overrides
- [ ] **Default root view config** - Make the default root Fusion path configurable via `Settings.yaml` instead of hardcoding `'App'`
- [ ] **Documentation** - Document root view configuration with examples for multi-layout setups

---

## Phase 3: Server-Side Rendering (SSR)

> Goal: Enable server-side rendering of Inertia pages for improved SEO, faster first paint, and better performance on slow connections.

### Implementation

- [ ] **SSR service** - Create a service that sends the page object to a Node.js SSR server and receives the rendered HTML
- [ ] **SSR middleware integration** - Modify the response flow to inject SSR-rendered HTML into the root `<div>` on initial page loads instead of leaving it empty for client-side hydration
- [ ] **SSR toggle** - Allow enabling/disabling SSR via `Settings.yaml` configuration
- [ ] **SSR head management** - Support `<Head>` component rendering so SSR can inject `<title>`, `<meta>`, and other head tags into the Fusion document head
- [ ] **Node.js SSR server setup** - Provide a preconfigured SSR server entry point and build configuration (Vite-based)
- [ ] **Fallback behavior** - Gracefully fall back to client-side rendering when the SSR server is unavailable
- [ ] **Documentation** - Document SSR setup, configuration, and deployment considerations

---

## Future Considerations

Items that may be addressed after the initial three phases:

- **Deferred Props** - Support `Inertia::defer()` for props that load after the initial page render (v2 feature)
- **Merge Props** - Support `Inertia::merge()` / `deepMerge()` for infinite scroll and paginated data (v2 feature)
- **History Encryption** - Support encrypted history state for sensitive page data (v2 feature)
- **Prefetching** - Support link prefetching hints for faster navigation (v2 feature)
- **Polling** - Built-in polling support for real-time data updates (v2 feature)
- **When Visiting** - Reactive helpers for detecting active navigation (v2 feature)
