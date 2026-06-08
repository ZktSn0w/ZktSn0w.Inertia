# Deferred Props

## Why It Exists

Some props take time to compute — database-heavy queries, external API calls, permission checks. Deferred props let the initial page render immediately with fast data, while slow data is fetched in a follow-up XHR after the page is already visible.

The result: the user sees the page instantly. Slow data slots in once ready. No artificial loading states on the whole page.

---

## How It Works

### 1 — Initial Load

When `inertia()` encounters a `DeferProp` in the props array, it does not evaluate the callback. Instead, the key is collected into a `deferredProps` map and excluded from `props`. The client receives:

```json
{
  "component": "Products/Index",
  "props": {
    "products": [...]
  },
  "deferredProps": {
    "default": ["permissions"]
  },
  "version": "abc123",
  "url": "/products"
}
```

The page renders immediately with `products`. The Inertia client reads `deferredProps` and knows it must fetch `permissions` separately.

### 2 — Deferred Fetch

The client fires one partial-reload XHR per group (in parallel). For the `"default"` group:

```
GET /products
X-Inertia: true
X-Inertia-Partial-Component: Products/Index
X-Inertia-Partial-Data: permissions
```

The server hits the same controller action, resolves only the requested props (evaluating the `DeferProp` callback this time), and returns:

```json
{
  "component": "Products/Index",
  "props": {
    "permissions": [...]
  },
  "version": "abc123",
  "url": "/products"
}
```

The client merges the result into the live component. The `<Deferred>` wrapper swaps the fallback for the real content.

---

## Grouping

Props sharing a group name are fetched together in one request. Props without a group use `"default"` and are fetched alone.

```php
return $this->inertia('Dashboard', [
    'user'        => $this->currentUser(),
    'permissions' => new DeferProp(fn() => Permission::findAll()),           // group: default
    'teams'       => new DeferProp(fn() => Team::findAll(), 'sidebar'),      // group: sidebar
    'invites'     => new DeferProp(fn() => Invite::findPending(), 'sidebar'),// group: sidebar
]);
```

Result: two follow-up requests run in parallel.
- Request A: `X-Inertia-Partial-Data: permissions`
- Request B: `X-Inertia-Partial-Data: teams,invites`

`user` is in the initial props. `permissions`, `teams`, `invites` arrive after render.

---

## Usage

### Server — `DeferProp`

**Namespace:** `ZktSn0w\Inertia\Domain\Prop\DeferProp`

```php
use ZktSn0w\Inertia\Domain\Prop\DeferProp;

public function indexAction(): ResponseInterface
{
    return $this->inertia('Products/Index', [
        'products'    => Product::findAll(),                        // immediate
        'permissions' => new DeferProp(fn() => Permission::all()), // deferred, default group
        'teams'       => new DeferProp(fn() => Team::all(), 'sidebar'), // deferred, sidebar group
    ]);
}
```

**Constructor:**

```php
new DeferProp(callable $callback, string $group = 'default')
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$callback` | `\Closure` | — | Evaluated only when the client requests this prop |
| `$group` | `string` | `'default'` | Props with the same group are fetched in one request |

### Client — `<Deferred>` (Svelte)

```svelte
<script>
    import { Deferred } from '@inertiajs/svelte'
    let { products, permissions } = $props()
</script>

<ProductList {products} />

<Deferred data="permissions">
    {#snippet fallback()}
        <p>Loading permissions…</p>
    {/snippet}

    <PermissionList {permissions} />
</Deferred>
```

For multiple props from the same group:

```svelte
<Deferred data={['teams', 'invites']}>
    {#snippet fallback()}<p>Loading…</p>{/snippet}

    <TeamList {teams} />
    <InviteList {invites} />
</Deferred>
```

For Vue and React, see the [Inertia client-side docs](https://inertiajs.com/deferred-props).

---

## `DeferProp` Class

**File:** `Classes/Domain/Prop/DeferProp.php`  
**Namespace:** `ZktSn0w\Inertia\Domain\Prop`  
**Implements:** `Deferrable`

| Method | Returns | Description |
|---|---|---|
| `__construct(\Closure $callback, string $group = 'default')` | — | Creates deferred prop |
| `__invoke()` | `mixed` | Evaluates the callback — called by `resolveProps()` during deferred fetch |
| `shouldDefer()` | `bool` | Always `true` |
| `group()` | `string` | Returns the group name |

## `Deferrable` Interface

**File:** `Classes/Domain/Prop/Deferrable.php`  
**Namespace:** `ZktSn0w\Inertia\Domain\Prop`

```php
interface Deferrable
{
    public function shouldDefer(): bool;
    public function group(): string;
}
```

Implement this to create custom deferred prop types (e.g. with caching, rescue handling, or lazy evaluation strategies).

---

## How `resolveProps()` Works

**File:** `Classes/Trait/Inertia.php`

Called on every `inertia()` invocation. Walks the props array and splits values based on type and request mode:

| Value type | Initial load | Deferred fetch (key requested) | Deferred fetch (key not requested) |
|---|---|---|---|
| `DeferProp` | Excluded — added to `deferredProps` map | Callback evaluated, included in `props` | Skipped |
| `\Closure` | Evaluated immediately | Evaluated | Skipped |
| scalar / array | Passed through | Passed through | Skipped |

A request is detected as a deferred fetch when both conditions are true:
- `X-Inertia-Partial-Component` header matches the current `$component`
- `X-Inertia-Partial-Data` header is present and non-empty
