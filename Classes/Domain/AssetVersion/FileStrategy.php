<?php

namespace ZktSn0w\Inertia\Domain\AssetVersion;

class FileStrategy implements StrategyInterface
{
    public function __construct(private readonly array $options) {}

    public function getVersion(): ?string
    {
        $path = $this->options['path'] ?? null;

        if (!$path || !file_exists($path)) {
            return null;
        }

        return trim(file_get_contents($path)) ?: null;
    }
}
