# Asset Versioning

## Why It Exists

When frontend assets (JS/CSS bundles) are redeployed, the Inertia client may still hold a cached version of the page component. Asset versioning lets the server signal "assets changed — do a full reload" rather than swapping in a stale component via XHR.

The client sends `X-Inertia-Version: <version>` with every XHR request. If this doesn't match the server's current version, `InertiaMiddleware` responds with `409 Conflict` + `X-Inertia-Location` header. The Inertia client performs a full hard navigation to that URL, picking up the new assets.

---

## How It Works

1. `InertiaAssetVersionService` is a Flow singleton.
2. On boot, it reads `ZktSn0w.Inertia.assetVersioning.strategy` from settings — a `class` + `options` pair.
3. When `getAssetVersion()` is called (by `renderInertia()` and `InertiaMiddleware`), it instantiates the configured strategy class with the options array and calls `getVersion()`.
4. If the class doesn't implement `StrategyInterface`, it throws `\InvalidArgumentException`.
5. If `getVersion()` returns `null`, no version header is sent — version checking is effectively disabled.

---

## `StrategyInterface`

**File:** `Classes/Domain/AssetVersion/StrategyInterface.php`

```php
interface StrategyInterface {
    public function getVersion(): ?string;
}
```

Return `null` to disable versioning. Return any non-empty string to enable it.

---

## Built-in Strategies

### `SettingStrategy`

**File:** `Classes/Domain/AssetVersion/SettingStrategy.php`

Reads version from the `value` option in config. Suitable for manually bumped version strings or CI-injected env var substitutions.

Returns `null` if `value` is empty or unset.

```yaml
ZktSn0w:
  Inertia:
    assetVersioning:
      strategy:
        class: \ZktSn0w\Inertia\Domain\AssetVersion\SettingStrategy
        options:
          value: '2.1.0'
```

---

### `FileStrategy`

**File:** `Classes/Domain/AssetVersion/FileStrategy.php`

Reads the version from a plain text file (trimmed). Useful when your build pipeline writes a content hash to a file.

Returns `null` if `path` is missing, the file doesn't exist, or the file is empty.

```yaml
ZktSn0w:
  Inertia:
    assetVersioning:
      strategy:
        class: \ZktSn0w\Inertia\Domain\AssetVersion\FileStrategy
        options:
          path: '/var/www/html/public/version.txt'
```

Expected file content: a single trimmed string, e.g. `a3f2c9b`.

---

### `ManifestStrategy`

**File:** `Classes/Domain/AssetVersion/ManifestStrategy.php`

Reads the `version` key from a JSON file. Suitable for Vite or Webpack manifest files that include a version field.

Returns `null` if `path` is missing, the file doesn't exist, or the JSON has no `version` key.

```yaml
ZktSn0w:
  Inertia:
    assetVersioning:
      strategy:
        class: \ZktSn0w\Inertia\Domain\AssetVersion\ManifestStrategy
        options:
          path: '/var/www/html/public/manifest.json'
```

Expected JSON structure:
```json
{
  "version": "a3f2c9b",
  "files": { ... }
}
```

---

## Custom Strategy

1. Implement `ZktSn0w\Inertia\Domain\AssetVersion\StrategyInterface`.
2. Accept `array $options` in the constructor.
3. Configure in `Settings.yaml`:

```php
namespace Your\Package\Domain\AssetVersion;

use ZktSn0w\Inertia\Domain\AssetVersion\StrategyInterface;

class RedisStrategy implements StrategyInterface
{
    public function __construct(private readonly array $options) {}

    public function getVersion(): ?string
    {
        // Read from Redis, environment variable, database, etc.
        return getenv($this->options['env_var']) ?: null;
    }
}
```

```yaml
ZktSn0w:
  Inertia:
    assetVersioning:
      strategy:
        class: \Your\Package\Domain\AssetVersion\RedisStrategy
        options:
          env_var: 'ASSET_VERSION'
```

---

## Configuration Reference

```yaml
ZktSn0w:
  Inertia:
    assetVersioning:
      strategy:
        class: '\Fully\Qualified\ClassName'   # must implement StrategyInterface
        options:                               # passed as array to constructor
          key: value
```

The `options` key is optional — pass an empty array if the strategy needs none.
