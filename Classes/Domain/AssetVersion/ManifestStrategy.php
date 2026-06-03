<?php

namespace ZktSn0w\Inertia\Domain\AssetVersion;

class ManifestStrategy implements StrategyInterface
{
    public function __construct(private readonly array $options) {}

    public function getVersion(): ?string
    {
        $path = $this->options['path'] ?? null;

        if (!$path || !file_exists($path)) {
            return null;
        }

        $manifest = json_decode(file_get_contents($path), true);

        return isset($manifest['version']) ? (string) $manifest['version'] : null;
    }
}
